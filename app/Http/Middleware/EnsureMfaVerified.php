<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $settings = $user->mfaSettings()->first();

        if ($settings?->enabled_at === null) {
            return $next($request);
        }

        $verifiedUserId = (int) $request->session()->get('nexora_mfa_verified_user_id', 0);
        $verifiedAt = (int) $request->session()->get('nexora_mfa_verified_at', 0);
        $verificationLifetime = max(1, (int) config('session.lifetime', 120));
        $isRecent = $verifiedAt >= now()->subMinutes($verificationLifetime)->timestamp;

        if ($verifiedUserId !== (int) $user->id || ! $isRecent) {
            $request->session()->forget([
                'nexora_mfa_verified_user_id',
                'nexora_mfa_verified_at',
            ]);
            $request->session()->put('nexora_mfa_pending_user_id', $user->id);

            return redirect()->route('admin.mfa.challenge');
        }

        return $next($request);
    }
}
