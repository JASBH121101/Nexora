<?php

use App\Modules\Setup\Http\Controllers\SetupController;
use App\Modules\Site\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;

Route::get('/setup', SetupController::class)->name('setup.show');
Route::get('/', PublicSiteController::class)->name('site.home');
