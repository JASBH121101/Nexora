<?php

namespace App\Core\Installation;

use App\Models\InstallationSettings;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class InstallationState
{
    public function hasSchema(): bool
    {
        try {
            return Schema::hasTable('installation_settings')
                && Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    public function isCompleted(): bool
    {
        if (! $this->hasSchema()) {
            return false;
        }

        try {
            return InstallationSettings::query()
                ->whereKey(1)
                ->whereNotNull('setup_completed_at')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
