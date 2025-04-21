<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_batch_id', 'recipient_identifier', 'type', 'tracked_at',
        'ip_address', 'user_agent', 'link_url',
    ];

    protected $casts = [
        'tracked_at' => 'datetime',
    ];

    public function emailBatch(): BelongsTo
    {
        return $this->belongsTo(EmailBatch::class);
    }
}
