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

    /**
     * @return array{response_needed:Collection<int,Prospect>,no_reply:Collection<int,Prospect>,scheduled:Collection<int,Prospect>}
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

        $noReply = $base()
            ->when($responseIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $responseIds))
            ->whereNotNull('last_outbound_at')
            ->where('last_outbound_at', '<=', now()->subDays($this->noReplyDays()))
            ->where(function (Builder $query) {
                $query->whereNull('last_customer_reply_at')
                    ->orWhereColumn('last_customer_reply_at', '<=', 'last_outbound_at');
            })
            ->orderBy('last_outbound_at')
            ->limit(100)
            ->get();

        $higherPriorityIds = $responseNeeded->pluck('id')->merge($noReply->pluck('id'))->unique()->values();

        $scheduled = $base()
            ->when($higherPriorityIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $higherPriorityIds))
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now()->endOfDay())
            ->orderBy('next_follow_up_at')
            ->limit(100)
            ->get();

        return [
            'response_needed' => $responseNeeded,
            'no_reply' => $noReply,
            'scheduled' => $scheduled,
        ];
    }

    public function payload(User $user): array
    {
        $rawQueue = $this->queue($user);
        $queue = [
            'response_needed' => $rawQueue['response_needed'],
            'no_reply' => $rawQueue['no_reply'],
            'scheduled' => $rawQueue['scheduled'],
        ];
        $templates = $this->templates();
        $replyTemplate = $templates->firstWhere('trigger_type', FollowUpTemplate::TYPE_CUSTOMER_REPLIED);
        $noReplyTemplate = $templates->firstWhere('trigger_type', FollowUpTemplate::TYPE_NO_REPLY);

        $serialized = [];
        foreach ($queue as $type => $prospects) {
            $template = $type === 'response_needed' ? $replyTemplate : $noReplyTemplate;
            $serialized[$type] = $prospects->map(function (Prospect $prospect) use ($template, $user) {
                return [
                    ...(new ProspectResource($prospect))->resolve(),
                    'suggested_message' => $template ? $this->renderTemplate($template, $prospect, $user) : '',
                ];
            })->values()->all();
        }

        return [
            'queues' => $serialized,
            'stats' => [
                'response_needed' => count($serialized['response_needed']),
                'no_reply' => count($serialized['no_reply']),
                'scheduled' => count($serialized['scheduled']),
                'total' => count($serialized['response_needed']) + count($serialized['no_reply']) + count($serialized['scheduled']),
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
        ];
    }

    public function renderTemplate(FollowUpTemplate $template, Prospect $prospect, User $user): string
    {
        $firstName = trim(explode(' ', trim($user->name))[0] ?? $user->name);

        return strtr($template->message, [
            '{contact_name}' => $prospect->contact_name ?: 'Bapak/Ibu',
            '{company_name}' => $prospect->company_name,
            '{ae_name}' => $user->name,
            '{ae_first_name}' => $firstName,
            '{service}' => $prospect->service ?: 'kebutuhan digital',
            '{portfolio_url}' => 'portofolio.santovate.com',
        ]);
    }
}
