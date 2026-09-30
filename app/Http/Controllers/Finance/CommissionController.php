<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\SalesCommission;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    public function index(Request $request, FinanceService $finance): Response
    {
        $user = $request->user();
        $finance->ensureCommissionsForUser($user);

        $rows = SalesCommission::query()
            ->visibleTo($user)
            ->with(['deal.prospect','user','approver'])
            ->orderByDesc('updated_at')
            ->paginate(30)
            ->withQueryString();

        $rows->through(fn (SalesCommission $commission) => [
            'id'=>$commission->id,
            'status'=>$commission->status,
            'rate'=>(float) $commission->rate,
            'commissionable_value'=>(float) $commission->commissionable_value,
            'potential_amount'=>(float) $commission->potential_amount,
            'earned_amount'=>(float) $commission->earned_amount,
            'approved_amount'=>(float) $commission->approved_amount,
            'paid_amount'=>(float) $commission->paid_amount,
            'payable_amount'=>$commission->payable_amount,
            'adjustment_due'=>$commission->adjustment_due,
            'approved_at'=>$commission->approved_at?->toIso8601String(),
            'approved_by'=>$commission->approver?->name,
            'deal'=>[
                'id'=>$commission->deal?->id,
                'deal_number'=>$commission->deal?->deal_number,
                'client'=>$commission->deal?->prospect?->company_name,
            ],
            'sales'=>[
                'id'=>$commission->user?->id,
                'name'=>$commission->user?->name,
            ],
        ]);

        return Inertia::render('Finance/Commissions', [
            'commissions'=>$rows,
            'summary'=>$finance->summaryFor($user),
            'canManage'=>$user->canManageFinance(),
        ]);
    }

    public function approve(Request $request, SalesCommission $commission, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);
        $this->assertOrganization($request, $commission);

        $updated = $finance->approveCommission($commission, $request->user());

        return back()->with(
            'success',
            $updated->approved_amount > 0 ? 'Komisi earned telah di-approve.' : 'Belum ada earned commission yang dapat di-approve.'
        );
    }

    public function pay(Request $request, SalesCommission $commission, FinanceService $finance): RedirectResponse
    {
        $this->assertManager($request);
        $this->assertOrganization($request, $commission);

        $data = $request->validate([
            'reference_number'=>['nullable','string','max:255'],
            'paid_at'=>['required','date'],
        ]);

        $finance->payCommission(
            $commission,
            $request->user(),
            $data['reference_number'] ?? null,
            $data['paid_at']
        );

        return back()->with('success', 'Payout komisi tercatat.');
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()->canManageFinance(), 403);
    }

    private function assertOrganization(Request $request, SalesCommission $commission): void
    {
        abort_unless((int) $commission->organization_id === (int) $request->user()->organization_id, 403);
    }
}
