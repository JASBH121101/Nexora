<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_mfa_setting_id', 'code_hash', 'used_at'])]
class UserMfaRecoveryCode extends Model
{
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(UserMfaSetting::class, 'user_mfa_setting_id');
    }
}
