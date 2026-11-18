<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class MfaController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $settings = $user->mfaSettings()->first();
        $pending = $settings !== null
            && $settings->enabled_at === null
            && $settings->pending_secret !== null
            && $settings->pending_expires_at?->isFuture() === true;
        $unusedRecoveryCodes = $settings?->recoveryCodes()
            ->whereNull('used_at')
            ->count() ?? 0;

        return response()
            ->view('admin.security.mfa', [
                'enabled' => $settings?->enabled_at !== null,
                'pendingSecret' => $pending ? $settings->pending_secret : null,
                'provisioningUri' => $pending
                    ? app(TotpService::class)->provisioningUri(
                        $user->email,
                        (string) config('app.name', 'NEXORA'),
                        $settings->pending_secret,
                    )
                    : null,
                'unusedRecoveryCodes' => $unusedRecoveryCodes,
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache');
    }

    public function startEnrollment(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => __('security.password_invalid')]);
        }

        $settings = $user->mfaSettings()->firstOrNew();

        abort_if($settings->enabled_at !== null, 409);

        $settings->pending_secret = $totp->generateSecret();
        $settings->pending_expires_at = now()->addMinutes(10);
        $settings->secret = null;
        $settings->enabled_at = null;
        $settings->last_used_counter = null;
        $settings->save();

        return redirect()
            ->route('admin.security.mfa')
            ->with('status', __('security.enrollment_started'));
    }

    public function confirmEnrollment(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/D'],
        ]);

        $user = $request->user();
        $recoveryCodes = [];

        DB::transaction(function () use ($user, $data, $totp, &$recoveryCodes): void {
            $settings = $user->mfaSettings()->lockForUpdate()->first();

            if (
                $settings === null
                || $settings->pending_secret === null
                || $settings->pending_expires_at?->isFuture() !== true
            ) {
                throw ValidationException::withMessages([
                    'code' => __('security.enrollment_expired'),
                ]);
            }

            $counter = $totp->verify($settings->pending_secret, $data['code'], now()->timestamp);

            if ($counter === null) {
                throw ValidationException::withMessages([
                    'code' => __('security.code_invalid'),
                ]);
            }

            $settings->secret = $settings->pending_secret;
            $settings->pending_secret = null;
            $settings->pending_expires_at = null;
            $settings->enabled_at = now();
            $settings->last_used_counter = $counter;
            $settings->save();

            $settings->recoveryCodes()->delete();

            for ($index = 0; $index < 10; $index++) {
                $canonical = strtoupper(bin2hex(random_bytes(10)));
                $recoveryCodes[] = implode('-', str_split($canonical, 4));

                $settings->recoveryCodes()->create([
                    'code_hash' => Hash::make($canonical),
                ]);
            }
        });

        $request->session()->forget('nexora_mfa_pending_user_id');
        $request->session()->put('nexora_mfa_verified_user_id', $user->id);
        $request->session()->put('nexora_mfa_verified_at', now()->timestamp);
        $request->session()->regenerate();
        $request->session()->flash('mfa_recovery_codes', $recoveryCodes);

        return redirect()
            ->route('admin.security.mfa')
            ->with('status', __('security.enabled'));
    }

    public function disable(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/D'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => __('security.password_invalid')]);
        }

        DB::transaction(function () use ($user, $data, $totp): void {
            $settings = $user->mfaSettings()->lockForUpdate()->first();

            if ($settings?->enabled_at === null || $settings->secret === null) {
                throw ValidationException::withMessages([
                    'code' => __('security.not_enabled'),
                ]);
            }

            $counter = $totp->verify($settings->secret, $data['code'], now()->timestamp);
            $lastUsedCounter = $settings->last_used_counter;

            if ($counter === null || ($lastUsedCounter !== null && $counter <= $lastUsedCounter)) {
                throw ValidationException::withMessages([
                    'code' => __('security.code_invalid'),
                ]);
            }

            $settings->recoveryCodes()->delete();
            $settings->secret = null;
            $settings->pending_secret = null;
            $settings->pending_expires_at = null;
            $settings->enabled_at = null;
            $settings->last_used_counter = null;
            $settings->save();
        });

        $request->session()->forget([
            'nexora_mfa_pending_user_id',
            'nexora_mfa_verified_user_id',
            'nexora_mfa_verified_at',
        ]);
        $request->session()->regenerate();

        return redirect()
            ->route('admin.security.mfa')
            ->with('status', __('security.disabled'));
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $settings = $user->mfaSettings()->first();

        if ($settings?->enabled_at === null || $settings->secret === null) {
            return redirect()->route('admin.home');
        }

        $verifiedUserId = (int) $request->session()->get('nexora_mfa_verified_user_id', 0);
        $verifiedAt = (int) $request->session()->get('nexora_mfa_verified_at', 0);
        $verificationLifetime = max(1, (int) config('session.lifetime', 120));
        $isRecent = $verifiedAt >= now()->subMinutes($verificationLifetime)->timestamp;

        if ($verifiedUserId === (int) $user->id && $isRecent) {
            return redirect()->route('admin.home');
        }

        $request->session()->put('nexora_mfa_pending_user_id', $user->id);

        return response()
            ->view('admin.auth.mfa-challenge')
            ->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache');
    }

    public function verifyChallenge(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:32', 'regex:/^[A-Za-z0-9 -]+$/D'],
        ]);

        $user = $request->user();

        abort_unless(
            (int) $request->session()->get('nexora_mfa_pending_user_id', 0) === (int) $user->id,
            403,
        );

        DB::transaction(function () use ($user, $data, $totp): void {
            $settings = $user->mfaSettings()->lockForUpdate()->first();

            if ($settings?->enabled_at === null || $settings->secret === null) {
                throw ValidationException::withMessages([
                    'code' => __('security.not_enabled'),
                ]);
            }

            $canonical = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', strtoupper($data['code'])));
            $counter = $totp->verify($settings->secret, $canonical, now()->timestamp);
            $lastUsedCounter = $settings->last_used_counter;

            if ($counter !== null && ($lastUsedCounter === null || $counter > $lastUsedCounter)) {
                $settings->last_used_counter = $counter;
                $settings->save();

                return;
            }

            $recoveryCodes = $settings->recoveryCodes()
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            foreach ($recoveryCodes as $recoveryCode) {
                if (Hash::check($canonical, $recoveryCode->code_hash)) {
                    $recoveryCode->used_at = now();
                    $recoveryCode->save();

                    return;
                }
            }

            throw ValidationException::withMessages([
                'code' => __('security.code_invalid'),
            ]);
        });

        $request->session()->forget('nexora_mfa_pending_user_id');
        $request->session()->put('nexora_mfa_verified_user_id', $user->id);
        $request->session()->put('nexora_mfa_verified_at', now()->timestamp);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.home'));
    }

    public function cancelChallenge(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
