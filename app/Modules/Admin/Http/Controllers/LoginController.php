<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Installation\InstallationState;
use App\Models\InstallationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class LoginController
{
    public function create(InstallationState $installation): View|RedirectResponse
    {
        if (! $installation->isCompleted()) {
            return redirect()->route('setup.show');
        }

        if (Auth::check()) {
            return redirect()->route('admin.home');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con el propietario de esta instalación.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $ownerId = InstallationSettings::query()->whereKey(1)->value('owner_id');

        if (! $ownerId || (int) Auth::id() !== (int) $ownerId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403);
        }

        return redirect()->intended(route('admin.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
