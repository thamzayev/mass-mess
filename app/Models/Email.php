<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = [
        'email_batch_id',
        'user_id',
        'to_address',
        'cc_address',
        'bcc_address',
        'subject',
        'body',
        'attachments',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'attachments' => 'array', // Store attachments as JSON
        'sent_at' => 'datetime',
    ];

    public function email_batch()
    {
        return $this->belongsTo(EmailBatch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
