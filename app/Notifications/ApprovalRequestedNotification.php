<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ApprovalRequest $approval) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return [
            'title'=>'Approval dibutuhkan',
            'message'=>$this->approval->title,
            'action_url'=>$this->approval->approval_scope === 'platform' ? '/platform/approvals' : '/approvals',
            'approval_id'=>$this->approval->id,
        ];
    }
}
