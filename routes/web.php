<?php

use App\Modules\Admin\Http\Controllers\DashboardController;
use App\Modules\Admin\Http\Controllers\LoginController;
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
Route::post('/admin/logout', [LoginController::class, 'destroy'])
    ->middleware(['auth', 'owner'])
    ->name('admin.logout');

Route::middleware(['auth', 'owner'])->prefix('admin')->group(function (): void {
    Route::get('/', DashboardController::class)->name('admin.home');
});

Route::get('/', PublicSiteController::class)->name('site.home');
