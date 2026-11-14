<?php

namespace App\Http\Middleware;

use App\Core\Library\Localization\LanguageCatalog;
use App\Models\InstallationSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApplyInstallationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = InstallationSettings::query()->find(1);
            $locale = data_get($settings?->configuration, 'regional.locale');

            if (is_string($locale)) {
                App::setLocale(LanguageCatalog::normalize($locale) ?? (string) config('app.locale', 'es-419'));
            }
        } catch (Throwable) {
            // The installer must remain accessible before the schema exists.
        }

        return $next($request);
    }
}
