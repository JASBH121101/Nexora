<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\InstallationSettings;
use App\Models\Product;
use Illuminate\View\View;

final class DashboardController
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'settings' => InstallationSettings::query()->findOrFail(1),
            'productCount' => Product::query()->count(),
            'publishedCount' => Product::query()->published()->count(),
        ]);
    }
}
