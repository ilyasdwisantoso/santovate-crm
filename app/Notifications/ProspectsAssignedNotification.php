<?php

namespace App\Notifications;

use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProspectsAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ImportBatch $batch,
        private readonly int $prospectCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->prospectCount.' prospek ditugaskan kepada Anda',
            'message' => 'Admin menugaskan '.$this->prospectCount.' prospek dari import '.$this->batch->filename.' kepada Anda.',
            'count' => $this->prospectCount,
            'import_batch_id' => $this->batch->id,
            'action_url' => '/prospects?import_batch='.$this->batch->id,
        ];
    }
}
