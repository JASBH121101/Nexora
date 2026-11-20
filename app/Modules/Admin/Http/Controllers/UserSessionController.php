<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Security\UserSessionRegistry;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class UserSessionController
{
    public function index(Request $request, UserSessionRegistry $sessions): View
    {
        $sessions->pruneExpired();

        return view('admin.security.sessions', [
            'sessions' => UserSession::query()
                ->with('user:id,name,username,role')
                ->orderByDesc('last_activity')
                ->paginate(20),
            'currentSessionHash' => $sessions->hash($request),
        ]);
    }

    public function destroy(
        Request $request,
        string $sessionId,
        UserSessionRegistry $sessions,
    ): RedirectResponse {
        $session = UserSession::query()->findOrFail($sessionId);
        $isCurrentSession = hash_equals($session->session_hash, $sessions->hash($request));

        abort_unless($sessions->revokePublicId($sessionId), 404);

        if ($isCurrentSession) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->with('status', __('security.current_session_ended'));
        }

        return back()->with('status', __('security.session_revoked_successfully'));
    }
}
