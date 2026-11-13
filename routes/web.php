<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\OwnerOnly;
use App\Http\Middleware\RequirePasswordChange;
use App\Modules\Admin\Http\Controllers\AccessRequestController;
use App\Modules\Admin\Http\Controllers\DashboardController;
use App\Modules\Admin\Http\Controllers\EmployeeController;
use App\Modules\Admin\Http\Controllers\LoginController;
use App\Modules\Admin\Http\Controllers\LoginHelpController;
use App\Modules\Admin\Http\Controllers\PasswordController;
use App\Modules\Admin\Http\Controllers\VerifyEmailController;
use App\Modules\Setup\Http\Controllers\SetupController;
use App\Modules\Site\Http\Controllers\PublicSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/setup', SetupController::class)->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('setup.store');

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('status', 'Se envió un nuevo enlace de verificación.');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/admin/login', [LoginController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [LoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('admin.login.store');
Route::post('/admin/login/help', [LoginHelpController::class, 'store'])
    ->middleware('throttle:3,10')
    ->name('admin.login.help');

Route::middleware(['auth', AdminAccess::class, RequirePasswordChange::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('admin.logout');
        Route::get('/password/change', [PasswordController::class, 'edit'])->name('admin.password.change');
        Route::post('/password/change', [PasswordController::class, 'update'])->name('admin.password.update');
        Route::get('/', DashboardController::class)->name('admin.home');

        Route::middleware(OwnerOnly::class)->group(function (): void {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('admin.employees.index');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('admin.employees.store');
            Route::post('/employees/{employee}/status', [EmployeeController::class, 'updateStatus'])
                ->name('admin.employees.status');

            Route::get('/access-requests', [AccessRequestController::class, 'index'])
                ->name('admin.access-requests.index');
            Route::post('/access-requests/{accessRequest}/{status}', [AccessRequestController::class, 'update'])
                ->whereIn('status', ['resolved', 'rejected'])
                ->name('admin.access-requests.update');
            Route::post('/access-requests/{accessRequest}/temporary-password', [
                AccessRequestController::class,
                'issueTemporaryPassword',
            ])->name('admin.access-requests.temporary-password');
        });
    });

Route::get('/', PublicSiteController::class)->name('site.home');
