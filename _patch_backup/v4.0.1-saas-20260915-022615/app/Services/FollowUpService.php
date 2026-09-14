<?php

namespace App\Services;

use App\Http\Resources\ProspectResource;
use App\Models\FollowUpTemplate;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FollowUpService
{
    public function templates(): Collection
    {
        return FollowUpTemplate::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function noReplyDays(): int
    {
        return (int) ($this->templates()
            ->firstWhere('trigger_type', FollowUpTemplate::TYPE_NO_REPLY)?->wait_days ?? 5);
    }

    public function leadAgeDays(): int
    {
        return (int) ($this->templates()
            ->firstWhere('trigger_type', FollowUpTemplate::TYPE_LEAD_AGE)?->wait_days ?? 3);
    }

    /**
     * @return array{
     *   response_needed:Collection<int,Prospect>,
     *   lead_age_due:Collection<int,Prospect>,
     *   no_reply:Collection<int,Prospect>,
     *   scheduled:Collection<int,Prospect>
     * }
     */
    public function queue(User $user): array
    {
        $base = fn (): Builder => Prospect::query()
            ->visibleTo($user)
            ->with('assignedUser')
            ->whereNotIn('status', ['deal', 'ditolak', 'tidak_cocok'])
            ->where(function (Builder $query) {
                $query->whereNull('follow_up_snoozed_until')
                    ->orWhere('follow_up_snoozed_until', '<=', now());
            });

        $responseNeeded = $base()
            ->whereNotNull('last_customer_reply_at')
            ->where(function (Builder $query) {
                $query->whereNull('last_outbound_at')
                    ->orWhereColumn('last_customer_reply_at', '>', 'last_outbound_at');
            })
            ->orderBy('last_customer_reply_at')
            ->limit(100)
            ->get();

        $responseIds = $responseNeeded->pluck('id');

        $leadAgeDue = $base()
            ->when($responseIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $responseIds))
            ->whereIn('status', ['baru', 'diriset'])
            ->whereNull('last_outbound_at')
            ->whereNull('last_feedback_at')
            ->where('created_at', '<=', now()->subDays($this->leadAgeDays()))
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        $leadAgeIds = $leadAgeDue->pluck('id');

        $noReply = $base()
            ->when($responseIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $responseIds))
            ->when($leadAgeIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $leadAgeIds))
            ->whereNotNull('last_outbound_at')
            ->where('last_outbound_at', '<=', now()->subDays($this->noReplyDays()))
            ->where(function (Builder $query) {
                $query->whereNull('last_customer_reply_at')
                    ->orWhereColumn('last_customer_reply_at', '<=', 'last_outbound_at');
            })
            ->orderBy('last_outbound_at')
            ->limit(100)
            ->get();

        $higherPriorityIds = $responseNeeded->pluck('id')
            ->merge($leadAgeDue->pluck('id'))
            ->merge($noReply->pluck('id'))
            ->unique()
            ->values();

        $scheduled = $base()
            ->when($higherPriorityIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $higherPriorityIds))
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now()->endOfDay())
            ->orderBy('next_follow_up_at')
            ->limit(100)
            ->get();

        return [
            'response_needed' => $responseNeeded,
            'lead_age_due' => $leadAgeDue,
            'no_reply' => $noReply,
            'scheduled' => $scheduled,
        ];
    }

    public function payload(User $user): array
    {
        $rawQueue = $this->queue($user);
        $templates = $this->templates();

        $replyTemplate = $templates->firstWhere('trigger_type', FollowUpTemplate::TYPE_CUSTOMER_REPLIED);
        $noReplyTemplate = $templates->firstWhere('trigger_type', FollowUpTemplate::TYPE_NO_REPLY);
        $leadAgeTemplate = $templates->firstWhere('trigger_type', FollowUpTemplate::TYPE_LEAD_AGE);

        $serialized = [];
        foreach ($rawQueue as $type => $prospects) {
            $suggestedTemplate = match ($type) {
                'response_needed' => $replyTemplate,
                'lead_age_due' => $leadAgeTemplate,
                default => $noReplyTemplate,
            };

            $serialized[$type] = $prospects->map(function (Prospect $prospect) use ($templates, $suggestedTemplate, $user, $type) {
                $templateMessages = $templates->mapWithKeys(
                    fn (FollowUpTemplate $template) => [
                        (string) $template->id => $this->renderTemplate($template, $prospect, $user),
                    ]
                )->all();

                return [
                    ...(new ProspectResource($prospect))->resolve(),
                    'queue_type' => $type,
                    'lead_age_days' => $prospect->created_at
                        ? max(0, (int) $prospect->created_at->diffInDays(now()))
                        : 0,
                    'feedback_required' => $type === 'lead_age_due',
                    'suggested_template_id' => $suggestedTemplate?->id,
                    'suggested_message' => $suggestedTemplate
                        ? $this->renderTemplate($suggestedTemplate, $prospect, $user)
                        : '',
                    'template_messages' => $templateMessages,
                ];
            })->values()->all();
        }

        return [
            'queues' => $serialized,
            'stats' => [
                'response_needed' => count($serialized['response_needed']),
                'lead_age_due' => count($serialized['lead_age_due']),
                'no_reply' => count($serialized['no_reply']),
                'scheduled' => count($serialized['scheduled']),
                'total' => count($serialized['response_needed'])
                    + count($serialized['lead_age_due'])
                    + count($serialized['no_reply'])
                    + count($serialized['scheduled']),
            ],
            'templates' => $templates->map(fn (FollowUpTemplate $template) => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'trigger_type' => $template->trigger_type,
                'wait_days' => $template->wait_days,
                'message' => $template->message,
            ])->values()->all(),
            'noReplyDays' => (int) ($noReplyTemplate?->wait_days ?? 5),
            'leadAgeDays' => (int) ($leadAgeTemplate?->wait_days ?? 3),
        ];
    }

    public function renderTemplate(FollowUpTemplate $template, Prospect $prospect, User $user): string
    {
        $firstName = trim(explode(' ', trim($user->name))[0] ?? $user->name);
        $signature = trim((string) $user->whatsapp_signature);

        $message = strtr($template->message, [
            '{contact_name}' => $prospect->contact_name ?: 'Bapak/Ibu',
            '{company_name}' => $prospect->company_name,
            '{ae_name}' => $user->name,
            '{ae_first_name}' => $firstName,
            '{ae_phone}' => $user->phone ?: '',
            '{service}' => $prospect->service ?: 'kebutuhan digital',
            '{lead_age_days}' => $prospect->created_at
                ? (string) max(0, (int) $prospect->created_at->diffInDays(now()))
                : '0',
            '{portfolio_url}' => 'portofolio.santovate.com',
        ]);

        if ($signature !== '' && !str_contains($message, $signature)) {
            $message .= "\n\n".$signature;
        }

        return $message;
    }
}
