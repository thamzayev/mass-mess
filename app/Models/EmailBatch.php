<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'smtp_configuration_id', 'csv_file_path',
        'email_to', 'email_cc', 'email_bcc',
        'email_subject', 'email_body', 'attachment_paths',
        'has_personalized_attachments', 'data_headers', 'data_rows', 'status',
        'status', 'total_recipients', 'generated_count', 'sent_count', 'failed_count', 'tracking_enabled',
    ];

    protected $casts = [
        'attachment_paths' => 'array',
        'has_personalized_attachments' => 'boolean',
        'tracking_enabled' => 'boolean',
        'data_headers' => 'array',
        'data_rows' => 'array',
        'total_recipients' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function smtpConfiguration(): BelongsTo
    {
        return $this->belongsTo(SmtpConfiguration::class);
    }

    public function personalizedAttachments(): HasMany
    {
        return $this->hasMany(PersonalizedAttachment::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(EmailTrackingEvent::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }
}
