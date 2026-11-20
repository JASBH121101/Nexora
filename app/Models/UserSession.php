<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class UserSession extends Model
{
    protected $table = 'user_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'session_hash',
        'ip_address',
        'user_agent',
        'last_activity',
    ];

    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return [
            'last_activity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $session->id ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
