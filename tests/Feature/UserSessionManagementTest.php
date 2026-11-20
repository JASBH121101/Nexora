<?php

namespace Tests\Feature;

use App\Core\Security\UserSessionRegistry;
use App\Http\Middleware\EnsureRegisteredSession;
use App\Models\User;
use App\Models\UserSession;
use App\Modules\Admin\Http\Controllers\UserSessionController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

        $this->assertNotNull($remoteSession->fresh()->revoked_at);
        $this->get(route('admin.home'))->assertOk();
    }

    public function test_revoking_the_current_session_ends_its_authenticated_access(): void
    {
        $owner = $this->installOwner();
        $sessionId = bin2hex(random_bytes(32));
        $request = $this->authenticatedSessionRequest($sessionId, $owner);
        $activeSession = UserSession::query()->create([
            'user_id' => $owner->id,
            'session_hash' => hash('sha256', $sessionId),
            'ip_address' => '192.0.2.10',
            'user_agent' => 'NEXORA QA current browser',
            'last_activity' => now()->timestamp,
        ]);

        $response = app(UserSessionController::class)->destroy(
            $request,
            $activeSession->id,
            app(UserSessionRegistry::class),
        );

        $this->assertSame(route('admin.login'), $response->getTargetUrl());
        $this->assertNotNull($activeSession->fresh()->revoked_at);

        $replayedRequest = $this->authenticatedSessionRequest($sessionId, $owner);
        $this->assertFalse(app(UserSessionRegistry::class)->touch($replayedRequest, $owner));
    }

    public function test_setup_registers_the_initial_owner_session(): void
    {
        $owner = $this->installOwner();

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $owner->id,
            'revoked_at' => null,
        ]);
    }

    public function test_revoked_session_tombstone_cannot_be_reactivated(): void
    {
        $owner = $this->installOwner();
        $sessionId = bin2hex(random_bytes(32));
        $request = $this->authenticatedSessionRequest($sessionId, $owner);
        $activeSession = UserSession::query()->create([
            'user_id' => $owner->id,
            'session_hash' => hash('sha256', $sessionId),
            'last_activity' => now()->timestamp,
            'revoked_at' => now(),
        ]);

        $response = app(EnsureRegisteredSession::class)->handle(
            $request,
            static fn () => response('session unexpectedly accepted'),
        );

        $this->assertSame(route('admin.login'), $response->getTargetUrl());
        $this->assertNotNull($activeSession->fresh()->revoked_at);
        $this->assertFalse(app(UserSessionRegistry::class)->touch(
            $this->authenticatedSessionRequest($sessionId, $owner),
            $owner,
        ));
    }

    public function test_expired_session_is_rejected_and_marked_revoked(): void
    {
        $owner = $this->installOwner();
        $sessionId = bin2hex(random_bytes(32));
        $request = $this->authenticatedSessionRequest($sessionId, $owner);
        $activeSession = UserSession::query()->create([
            'user_id' => $owner->id,
            'session_hash' => hash('sha256', $sessionId),
            'last_activity' => now()->subMinutes(121)->timestamp,
        ]);

        $response = app(EnsureRegisteredSession::class)->handle(
            $request,
            static fn () => response('expired session unexpectedly accepted'),
        );

        $this->assertSame(route('admin.login'), $response->getTargetUrl());
        $this->assertNotNull($activeSession->fresh()->revoked_at);
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

    private function authenticatedSessionRequest(string $sessionId, User $user): Request
    {
        $session = app('session')->driver();
        $session->setId($sessionId);
        $session->start();

        $request = Request::create('/admin');
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn (): User => $user);

        return $request;
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
