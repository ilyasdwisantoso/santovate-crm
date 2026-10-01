<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ApprovalRequest $approval) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return [
            'title'=>'Approval '.$this->approval->status_label,
            'message'=>$this->approval->title,
            'action_url'=>$this->approval->subject_type === \App\Models\Quotation::class && $this->approval->subject_id
                ? '/quotations/'.$this->approval->subject_id
                : '/approvals',
            'approval_id'=>$this->approval->id,
        ];
    }
}
