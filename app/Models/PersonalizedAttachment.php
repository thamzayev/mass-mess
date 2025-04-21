<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalizedAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_batch_id', 'recipient_identifier', 'file_path', 'original_name',
    ];

    public function emailBatch(): BelongsTo
    {
        return $this->belongsTo(EmailBatch::class);
    }
}
