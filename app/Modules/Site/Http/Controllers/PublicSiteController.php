<?php

namespace App\Modules\Site\Http\Controllers;

use App\Core\Installation\InstallationState;
use App\Models\InstallationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PublicSiteController
{
    public function __invoke(InstallationState $installation): View|RedirectResponse
    {
        if (! $installation->isCompleted()) {
            return redirect()->route('setup.show');
        }

        return view('site.home', [
            'settings' => InstallationSettings::query()->findOrFail(1),
        ]);
    }
}
