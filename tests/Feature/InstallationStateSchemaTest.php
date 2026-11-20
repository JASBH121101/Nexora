<?php

namespace Tests\Feature;

use App\Core\Installation\InstallationState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstallationStateSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_health_requires_the_permission_roles_table(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::drop('roles');

        $this->assertFalse(app(InstallationState::class)->hasSchema());
    }

    public function test_schema_health_requires_the_export_history_table(): void
    {
        Schema::drop('export_histories');

        $this->assertFalse(app(InstallationState::class)->hasSchema());
    }

    public function test_schema_health_requires_the_mfa_settings_table(): void
    {
        Schema::drop('user_mfa_recovery_codes');
        Schema::drop('user_mfa_settings');

        $this->assertFalse(app(InstallationState::class)->hasSchema());
    }

    public function test_schema_health_requires_the_mfa_recovery_codes_table(): void
    {
        Schema::drop('user_mfa_recovery_codes');

        $this->assertFalse(app(InstallationState::class)->hasSchema());
    }

    public function test_schema_health_requires_the_active_user_sessions_table(): void
    {
        Schema::drop('user_sessions');

        $this->assertFalse(app(InstallationState::class)->hasSchema());
    }
}
