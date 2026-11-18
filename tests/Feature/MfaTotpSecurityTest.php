<?php

namespace Tests\Feature;

use App\Core\Security\TotpService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MfaTotpSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_enrollment_encrypts_the_secret_and_generates_one_time_recovery_codes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00'));
        $user = $this->installOwner();

        $this->post(route('admin.security.mfa.enrollment'), [
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.security.mfa'));

        $settings = $user->mfaSettings()->firstOrFail();
        $this->assertNotNull($settings->pending_secret);
        $this->assertNotSame(
            $settings->pending_secret,
            DB::table('user_mfa_settings')->where('user_id', $user->id)->value('pending_secret'),
        );

        $totp = app(TotpService::class);
        $counter = intdiv(now()->timestamp, 30);
        $code = $totp->codeForCounter($settings->pending_secret, $counter);

        $this->post(route('admin.security.mfa.confirm'), ['code' => $code])
            ->assertRedirect(route('admin.security.mfa'))
            ->assertSessionHas('mfa_recovery_codes');

        $settings->refresh();
        $this->assertNotNull($settings->enabled_at);
        $this->assertSame(10, $settings->recoveryCodes()->count());
        $this->assertSame($counter, $settings->last_used_counter);
        $this->assertNotSame(
            $settings->secret,
            DB::table('user_mfa_settings')->where('user_id', $user->id)->value('secret'),
        );

        foreach ($settings->recoveryCodes()->get() as $recoveryCode) {
            $this->assertNotSame('ABCD-EFGH-IJKL-MNOP-QRST', $recoveryCode->code_hash);
            $this->assertNull($recoveryCode->used_at);
        }
    }

    public function test_login_requires_a_fresh_totp_code_and_rejects_replay(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00'));
        $user = $this->installOwner();
        $this->enableMfa($user);

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), [
            'username' => 'mfaqaowner',
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.mfa.challenge'));

        $this->get(route('admin.home'))
            ->assertRedirect(route('admin.mfa.challenge'));

        $settings = $user->mfaSettings()->firstOrFail();
        $totp = app(TotpService::class);
        $sameCounterCode = $totp->codeForCounter($settings->secret, intdiv(now()->timestamp, 30));

        $this->post(route('admin.mfa.challenge.verify'), ['code' => $sameCounterCode])
            ->assertSessionHasErrors('code');

        Carbon::setTestNow(now()->addSeconds(30));
        $freshCode = $totp->codeForCounter($settings->secret, intdiv(now()->timestamp, 30));

        $this->post(route('admin.mfa.challenge.verify'), ['code' => $freshCode])
            ->assertRedirect(route('admin.home'));

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), [
            'username' => 'mfaqaowner',
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.challenge.verify'), ['code' => $freshCode])
            ->assertSessionHasErrors('code');
    }

    public function test_recovery_code_can_be_used_only_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00'));
        $user = $this->installOwner();
        $codes = $this->enableMfa($user);
        $recoveryCode = $codes[0];

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), [
            'username' => 'mfaqaowner',
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.challenge.verify'), ['code' => $recoveryCode])
            ->assertRedirect(route('admin.home'));

        $recoveryRecord = $user->mfaSettings()->firstOrFail()
            ->recoveryCodes()
            ->whereNotNull('used_at')
            ->firstOrFail();
        $this->assertNotNull($recoveryRecord->used_at);

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), [
            'username' => 'mfaqaowner',
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.challenge.verify'), ['code' => $recoveryCode])
            ->assertSessionHasErrors('code');
    }

    public function test_disabling_mfa_requires_password_and_an_unused_totp_counter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00'));
        $user = $this->installOwner();
        $this->enableMfa($user);

        $this->post(route('admin.security.mfa.disable'), [
            'password' => 'incorrect password',
            'code' => '123456',
        ])->assertSessionHasErrors('password');

        Carbon::setTestNow(now()->addSeconds(30));
        $settings = $user->mfaSettings()->firstOrFail();
        $code = app(TotpService::class)->codeForCounter(
            $settings->secret,
            intdiv(now()->timestamp, 30),
        );

        $this->post(route('admin.security.mfa.disable'), [
            'password' => 'Strong-Password-2026!',
            'code' => $code,
        ])->assertRedirect(route('admin.security.mfa'));

        $settings->refresh();
        $this->assertNull($settings->enabled_at);
        $this->assertNull($settings->secret);
        $this->assertSame(0, $settings->recoveryCodes()->count());
    }

    private function enableMfa(User $user): array
    {
        $this->post(route('admin.security.mfa.enrollment'), [
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.security.mfa'));

        $settings = $user->mfaSettings()->firstOrFail();
        $code = app(TotpService::class)->codeForCounter(
            $settings->pending_secret,
            intdiv(now()->timestamp, 30),
        );

        $this->post(route('admin.security.mfa.confirm'), ['code' => $code])
            ->assertRedirect(route('admin.security.mfa'));

        return session('mfa_recovery_codes');
    }

    private function installOwner(): User
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Tienda MFA QA',
            'business_type' => 'perfumery',
            'owner_name' => 'Owner MFA QA',
            'owner_username' => 'mfaqaowner',
            'owner_email' => 'mfa-qa@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Prueba MFA.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '1',
        ])->assertRedirect(route('admin.home'));

        return User::query()->where('username', 'mfaqaowner')->firstOrFail();
    }
}
