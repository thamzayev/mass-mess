<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class SmtpConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'host', 'port', 'username',
        'password', 'encryption', 'from_address', 'from_name',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->user_id = Auth::id(); // Automatically set the logged-in user's ID
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailBatches(): HasMany
    {
        return $this->hasMany(EmailBatch::class);
    }
}
