<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpTemplate extends Model
{
    public const TYPE_NO_REPLY = 'no_reply';
    public const TYPE_CUSTOMER_REPLIED = 'customer_replied';

    protected $fillable = ['key', 'name', 'trigger_type', 'wait_days', 'message', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'wait_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
