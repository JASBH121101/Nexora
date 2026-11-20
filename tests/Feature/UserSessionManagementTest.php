<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_and_revoke_another_active_session(): void
    {
        $owner = $this->installOwner();
        $remoteSession = UserSession::query()->create([
            'user_id' => $owner->id,
            'session_hash' => hash('sha256', 'remote-session-secret'),
            'ip_address' => '192.0.2.25',
            'user_agent' => 'NEXORA QA remote browser',
            'last_activity' => now()->timestamp,
        ]);

        $this->get(route('admin.security.sessions'))
            ->assertOk()
            ->assertSee(__('security.sessions_title'))
            ->assertSee('NEXORA QA remote browser')
            ->assertSee(__('security.current_session'));

        $this->delete(route('admin.security.sessions.destroy', $remoteSession->id))
            ->assertRedirect()
            ->assertSessionHas('status', __('security.session_revoked_successfully'));

        $this->assertDatabaseMissing('user_sessions', ['id' => $remoteSession->id]);
        $this->get(route('admin.home'))->assertOk();
    }

    public function test_revoking_the_current_session_ends_its_authenticated_access(): void
    {
        $this->installOwner();
        $sessionHash = hash('sha256', session()->getId());
        $activeSession = UserSession::query()->where('session_hash', $sessionHash)->firstOrFail();

        $this->delete(route('admin.security.sessions.destroy', $activeSession->id))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', __('security.current_session_ended'));

        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    public function test_revoked_or_missing_session_registry_entry_is_rejected(): void
    {
        $this->installOwner();
        UserSession::query()->where('session_hash', hash('sha256', session()->getId()))->delete();

        $this->get(route('admin.home'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('session');
    }

    public function test_expired_session_is_rejected_and_removed(): void
    {
        $this->installOwner();
        $activeSession = UserSession::query()
            ->where('session_hash', hash('sha256', session()->getId()))
            ->firstOrFail();
        $activeSession->forceFill(['last_activity' => now()->subMinutes(121)->timestamp])->save();

        $this->get(route('admin.home'))
            ->assertRedirect(route('admin.login'))
            ->assertDatabaseMissing('user_sessions', ['id' => $activeSession->id]);
    }

    public function test_employee_cannot_open_owner_session_management(): void
    {
        $this->installOwner();
        $employee = User::query()->create([
            'name' => 'Empleado QA',
            'username' => 'employeeqa',
            'email' => 'employee-qa@example.com',
            'password' => Hash::make('Employee-Password-2026!'),
            'role' => 'employee',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        Auth::logout();
        $this->post(route('admin.login.store'), [
            'username' => $employee->username,
            'password' => 'Employee-Password-2026!',
        ])->assertRedirect(route('admin.home'));

        $this->get(route('admin.security.sessions'))->assertForbidden();
    }

    private function installOwner(): User
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Tienda de sesiones QA',
            'business_type' => 'perfumery',
            'owner_name' => 'Owner Sessions QA',
            'owner_username' => 'sessionsqaowner',
            'owner_email' => 'sessions-qa@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Pruebas de sesiones.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '1',
        ])->assertRedirect(route('admin.home'));

        return User::query()->where('username', 'sessionsqaowner')->firstOrFail();
    }
}
