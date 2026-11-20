<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'secret',
    'pending_secret',
    'pending_expires_at',
    'enabled_at',
    'last_used_counter',
])]
class UserMfaSetting extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'pending_secret' => 'encrypted',
            'pending_expires_at' => 'datetime',
            'enabled_at' => 'datetime',
            'last_used_counter' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(UserMfaRecoveryCode::class);
    }
}
