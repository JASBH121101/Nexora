<?php

namespace App\Http\Middleware;

use App\Core\Security\UserSessionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRegisteredSession
{
    public function __construct(
        private readonly UserSessionRegistry $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $this->sessions->touch($request, $user)) {
            $this->sessions->revokeCurrent($request, $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors(['session' => __('security.session_revoked')]);
        }

        return $next($request);
    }
}
