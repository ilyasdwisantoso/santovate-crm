<?php

namespace App\Services;

use App\Models\CommercialInvoice;
use App\Models\CommissionLedgerEntry;
use App\Models\Deal;
use App\Models\DealPaymentSchedule;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\SalesCommission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public const COMMISSION_RATE = 25.0;

    public function assertInvoiceAllocation(
        Deal $deal,
        ?DealPaymentSchedule $schedule,
        float $invoiceTotal,
        ?int $ignoreInvoiceId = null
    ): array {
        if ($invoiceTotal <= 0) {
            throw ValidationException::withMessages(['subtotal'=>'Total invoice harus lebih besar dari 0.']);
        }

        // Every invoice create/issue path locks the same Deal row. This makes
        // concurrent allocation checks serialize instead of allowing two
        // invoices to pass the remaining-balance check at the same time.
        $lockedDeal = Deal::query()->lockForUpdate()->findOrFail($deal->id);

        $dealInvoices = CommercialInvoice::query()
            ->where('deal_id', $lockedDeal->id)
            ->where('status', '!=', 'void');
        if ($ignoreInvoiceId) {
            $dealInvoices->where('id', '!=', $ignoreInvoiceId);
        }

        $dealAllocated = (float) $dealInvoices->sum('total_amount');
        $dealRemaining = max(0, (float) $lockedDeal->actual_deal_value - $dealAllocated);

        if ($invoiceTotal > $dealRemaining + 0.01) {
            throw ValidationException::withMessages([
                'subtotal'=>'Total invoice melebihi sisa nilai Deal. Sisa Deal: Rp'.number_format($dealRemaining, 0, ',', '.'),
            ]);
        }

        $scheduleRemaining = null;
        if ($schedule) {
            $lockedSchedule = DealPaymentSchedule::query()
                ->where('deal_id', $lockedDeal->id)
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            $scheduleInvoices = CommercialInvoice::query()
                ->where('deal_payment_schedule_id', $lockedSchedule->id)
                ->where('status', '!=', 'void');
            if ($ignoreInvoiceId) {
                $scheduleInvoices->where('id', '!=', $ignoreInvoiceId);
            }

            $scheduleAllocated = (float) $scheduleInvoices->sum('total_amount');
            $scheduleRemaining = max(0, (float) $lockedSchedule->amount - $scheduleAllocated);

            if ($invoiceTotal > $scheduleRemaining + 0.01) {
                throw ValidationException::withMessages([
                    'subtotal'=>'Total invoice melebihi sisa payment schedule '.$lockedSchedule->label.'. Sisa schedule: Rp'.number_format($scheduleRemaining, 0, ',', '.'),
                ]);
            }
        }

        return [
            'deal_remaining'=>$dealRemaining,
            'schedule_remaining'=>$scheduleRemaining,
        ];
    }

    public function markOverdue(int $organizationId): void
    {
        if (!Schema::hasTable('commercial_invoices')) return;

        CommercialInvoice::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['issued','partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereRaw('(paid_amount - refunded_amount) < total_amount')
            ->update(['status'=>'overdue','updated_at'=>now()]);
    }

    public function syncInvoice(CommercialInvoice $invoice, array $context = []): CommercialInvoice
    {
        $invoice->refresh();

        $grossPaid = (float) PaymentTransaction::query()
            ->where('commercial_invoice_id', $invoice->id)
            ->whereIn('status', ['paid','refunded'])
            ->sum('amount');

        $refunded = (float) PaymentRefund::query()
            ->where('commercial_invoice_id', $invoice->id)
            ->where('status', 'processed')
            ->sum('amount');

        $netPaid = max(0, $grossPaid - $refunded);
        $status = $invoice->status;

        if ($status !== 'void') {
            if ($netPaid + 0.01 >= (float) $invoice->total_amount && (float) $invoice->total_amount > 0) {
                $status = 'paid';
            } elseif ($netPaid > 0) {
                $status = 'partially_paid';
            } elseif ($invoice->issued_at || $status !== 'draft') {
                $status = $invoice->due_date && $invoice->due_date->lt(now()->startOfDay()) ? 'overdue' : 'issued';
            } else {
                $status = 'draft';
            }
        }

        $invoice->update([
            'paid_amount'=>$grossPaid,
            'refunded_amount'=>$refunded,
            'status'=>$status,
        ]);

        if ($invoice->deal_payment_schedule_id) {
            $this->syncSchedule($invoice->paymentSchedule()->first());
        }

        $this->syncCommission($invoice->deal()->first(), $context);

        return $invoice->fresh();
    }

    public function syncSchedule(?DealPaymentSchedule $schedule): void
    {
        if (!$schedule) return;

        $invoices = CommercialInvoice::query()
            ->where('deal_payment_schedule_id', $schedule->id)
            ->whereNotIn('status', ['draft','void'])
            ->get(['total_amount','paid_amount','refunded_amount']);

        $invoiced = (float) $invoices->sum('total_amount');
        $netPaid = (float) $invoices->sum(fn ($invoice) => max(
            0,
            (float) $invoice->paid_amount - (float) $invoice->refunded_amount
        ));

        $status = 'scheduled';
        if ($netPaid + 0.01 >= (float) $schedule->amount && (float) $schedule->amount > 0) {
            $status = 'paid';
        } elseif ($netPaid > 0) {
            $status = 'partially_paid';
        } elseif ($invoiced > 0) {
            $status = 'invoiced';
        }

        if ($schedule->status !== $status) {
            $schedule->update(['status'=>$status]);
        }
    }

    public function ensureCommissionsForUser(User $user): void
    {
        if (!Schema::hasTable('sales_commissions')) return;

        $query = Deal::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', 'won');

        if (!$user->canManageFinance()) {
            $query->where('owner_id', $user->id);
        }

        $query->orderBy('id')->chunkById(100, function ($deals) {
            foreach ($deals as $deal) {
                $this->syncCommission($deal);
            }
        });
    }

    public function syncCommission(?Deal $deal, array $context = []): ?SalesCommission
    {
        if (!$deal || !Schema::hasTable('sales_commissions') || !Schema::hasTable('commercial_invoices')) return null;

        $commissionable = max(0, (float) $deal->commissionable_value);
        $actualDeal = max(0, (float) $deal->actual_deal_value);
        $potential = round($commissionable * self::COMMISSION_RATE / 100, 2);

        $commission = SalesCommission::firstOrNew(['deal_id'=>$deal->id]);
        $previousEarned = (float) ($commission->earned_amount ?? 0);

        $grossPaid = (float) CommercialInvoice::query()
            ->where('deal_id', $deal->id)
            ->whereNotIn('status', ['draft','void'])
            ->sum('paid_amount');

        $refunded = (float) CommercialInvoice::query()
            ->where('deal_id', $deal->id)
            ->whereNotIn('status', ['draft','void'])
            ->sum('refunded_amount');

        $netPaid = min($actualDeal, max(0, $grossPaid - $refunded));
        $earnedBasis = $actualDeal > 0
            ? min($commissionable, $netPaid * ($commissionable / $actualDeal))
            : 0;
        $earned = round($earnedBasis * self::COMMISSION_RATE / 100, 2);

        $hasIssuedInvoice = CommercialInvoice::query()
            ->where('deal_id', $deal->id)
            ->whereNotIn('status', ['draft','void'])
            ->exists();

        $paid = (float) ($commission->paid_amount ?? 0);
        $approved = max($paid, min((float) ($commission->approved_amount ?? 0), $earned));

        $status = 'estimated';
        if ($potential > 0 && $earned + 0.01 >= $potential && $paid + 0.01 >= $potential) {
            $status = 'paid';
        } elseif ($approved > $paid + 0.01) {
            $status = 'approved';
        } elseif ($potential > 0 && $earned + 0.01 >= $potential) {
            $status = 'earned';
        } elseif ($earned > 0) {
            $status = 'partially_earned';
        } elseif ($hasIssuedInvoice) {
            $status = 'pending_payment';
        }

        $commission->fill([
            'organization_id'=>$deal->organization_id,
            'user_id'=>$deal->owner_id,
            'rate'=>self::COMMISSION_RATE,
            'commissionable_value'=>$commissionable,
            'potential_amount'=>$potential,
            'earned_amount'=>$earned,
            'approved_amount'=>$approved,
            'status'=>$status,
            'last_recalculated_at'=>now(),
        ])->save();

        $delta = round($earned - $previousEarned, 2);
        if (!empty($context) && abs($delta) > 0.01) {
            CommissionLedgerEntry::create([
                'organization_id'=>$deal->organization_id,
                'sales_commission_id'=>$commission->id,
                'deal_id'=>$deal->id,
                'commercial_invoice_id'=>$context['commercial_invoice_id'] ?? null,
                'payment_transaction_id'=>$context['payment_transaction_id'] ?? null,
                'payment_refund_id'=>$context['payment_refund_id'] ?? null,
                'created_by'=>$context['user_id'] ?? null,
                'entry_type'=>$delta > 0 ? 'earned' : 'refund_adjustment',
                'amount'=>$delta,
                'reference_number'=>$context['reference_number'] ?? null,
                'notes'=>$context['notes'] ?? null,
                'occurred_at'=>now(),
            ]);
        }

        return $commission->fresh();
    }

    public function approveCommission(SalesCommission $commission, User $actor): SalesCommission
    {
        return DB::transaction(function () use ($commission, $actor) {
            $locked = SalesCommission::query()->lockForUpdate()->findOrFail($commission->id);
            $locked = $this->syncCommission($locked->deal()->first()) ?? $locked;
            $locked = SalesCommission::query()->lockForUpdate()->findOrFail($commission->id);

            $target = min((float) $locked->earned_amount, (float) $locked->potential_amount);
            $previous = (float) $locked->approved_amount;
            $delta = round(max(0, $target - $previous), 2);

            if ($delta <= 0.01) return $locked;

            $locked->update([
                'approved_amount'=>$target,
                'approved_by'=>$actor->id,
                'approved_at'=>now(),
            ]);

            CommissionLedgerEntry::create([
                'organization_id'=>$locked->organization_id,
                'sales_commission_id'=>$locked->id,
                'deal_id'=>$locked->deal_id,
                'created_by'=>$actor->id,
                'entry_type'=>'approval',
                'amount'=>$delta,
                'notes'=>'Commission approved from verified client payment.',
                'occurred_at'=>now(),
            ]);

            return $this->syncCommission($locked->deal()->first());
        });
    }

    public function payCommission(SalesCommission $commission, User $actor, ?string $reference = null, $paidAt = null): SalesCommission
    {
        return DB::transaction(function () use ($commission, $actor, $reference, $paidAt) {
            $locked = SalesCommission::query()->lockForUpdate()->findOrFail($commission->id);
            $locked = $this->syncCommission($locked->deal()->first()) ?? $locked;
            $locked = SalesCommission::query()->lockForUpdate()->findOrFail($commission->id);

            $payable = round(max(0, (float) $locked->approved_amount - (float) $locked->paid_amount), 2);
            abort_if($payable <= 0.01, 422, 'Tidak ada komisi approved yang belum dibayar.');

            $locked->update(['paid_amount'=>(float) $locked->paid_amount + $payable]);

            CommissionLedgerEntry::create([
                'organization_id'=>$locked->organization_id,
                'sales_commission_id'=>$locked->id,
                'deal_id'=>$locked->deal_id,
                'created_by'=>$actor->id,
                'entry_type'=>'payout',
                'amount'=>$payable,
                'reference_number'=>$reference,
                'notes'=>'Commission payout recorded by Administrator.',
                'occurred_at'=>$paidAt ?: now(),
            ]);

            return $this->syncCommission($locked->deal()->first());
        });
    }

    public function summaryFor(User $user): array
    {
        if (!Schema::hasTable('commercial_invoices') || !Schema::hasTable('sales_commissions')) {
            return [
                'actual_deal'=>0.0,'invoiced'=>0.0,'paid_by_client'=>0.0,'outstanding'=>0.0,
                'overdue_count'=>0,'potential_commission'=>0.0,'earned_commission'=>0.0,
                'approved_commission'=>0.0,'commission_paid'=>0.0,
            ];
        }

        $this->markOverdue((int) $user->organization_id);
        $this->ensureCommissionsForUser($user);

        $invoices = CommercialInvoice::query()
            ->visibleTo($user)
            ->whereNotIn('status', ['draft','void'])
            ->get(['total_amount','paid_amount','refunded_amount','status']);

        $commissions = SalesCommission::query()
            ->visibleTo($user)
            ->get(['potential_amount','earned_amount','approved_amount','paid_amount']);

        $dealQuery = Deal::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', 'won');

        if (!$user->canManageFinance()) {
            $dealQuery->where('owner_id', $user->id);
        }

        return [
            'actual_deal'=>(float) $dealQuery->sum('actual_deal_value'),
            'invoiced'=>(float) $invoices->sum('total_amount'),
            'paid_by_client'=>(float) $invoices->sum(fn ($invoice) => max(0, (float) $invoice->paid_amount - (float) $invoice->refunded_amount)),
            'outstanding'=>(float) $invoices->sum(fn ($invoice) => max(0, (float) $invoice->total_amount - max(0, (float) $invoice->paid_amount - (float) $invoice->refunded_amount))),
            'overdue_count'=>$invoices->where('status', 'overdue')->count(),
            'potential_commission'=>(float) $commissions->sum('potential_amount'),
            'earned_commission'=>(float) $commissions->sum('earned_amount'),
            'approved_commission'=>(float) $commissions->sum('approved_amount'),
            'commission_paid'=>(float) $commissions->sum('paid_amount'),
        ];
    }
}
