<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class EmployeeController
{
    public function index(): View
    {
        return view('admin.employees.index', [
            'employees' => User::query()
                ->where('role', 'employee')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => strtolower(trim((string) $request->input('username'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-z0-9._-]+$/',
                'unique:users,username',
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $temporaryPassword = Str::password(20);

        User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $temporaryPassword,
            'role' => 'employee',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        return back()->with('employee_credentials_once', [
            'username' => $data['username'],
            'password' => $temporaryPassword,
        ]);
    }

    public function updateStatus(Request $request, User $employee): RedirectResponse
    {
        abort_unless($employee->isEmployee(), 404);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $employee->forceFill([
            'is_active' => (bool) $data['is_active'],
        ])->save();

        return back()->with(
            'status',
            $employee->is_active ? 'Cuenta activada.' : 'Cuenta suspendida.',
        );
    }
}
