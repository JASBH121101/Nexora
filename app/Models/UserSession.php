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
        'revoked_at',
    ];

    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return [
            'last_activity' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $session): void {
            $session->id ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
