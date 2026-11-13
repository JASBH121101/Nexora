<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Installation\InstallationState;
use App\Core\Installation\ServerRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SetupController
{
    public function __invoke(
        InstallationState $installation,
        ServerRequirements $requirements,
    ): View|RedirectResponse {
        if ($installation->isCompleted()) {
            return redirect()->route('site.home');
        }

        $checks = $requirements->checks();

        return view('setup.index', [
            'checks' => $checks,
            'requirementsPassed' => collect($checks)->every(
                static fn (array $check): bool => $check['passed'],
            ),
            'schemaReady' => $installation->hasSchema(),
        ]);
    }
}
