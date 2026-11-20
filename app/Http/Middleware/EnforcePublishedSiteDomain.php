<?php

namespace App\Http\Middleware;

use App\Models\InstallationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforcePublishedSiteDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuration = InstallationSettings::query()->find(1)?->configuration ?? [];

        if (! is_array($configuration)) {
            return $next($request);
        }

        $site = (array) data_get($configuration, 'site', []);
        $primaryId = $site['primary_domain_id'] ?? null;

        if (! is_string($primaryId) || $primaryId === '') {
            return $next($request);
        }

        $domains = array_values((array) ($site['domains'] ?? []));
        $primary = null;
        $current = null;
        $host = strtolower(rtrim($request->getHost(), '.'));

        foreach ($domains as $domain) {
            if (! is_array($domain) || ($domain['status'] ?? null) !== 'ready' || empty($domain['verified_at'])) {
                continue;
            }

            if (($domain['id'] ?? null) === $primaryId) {
                $primary = $domain;
            }

            if (strtolower((string) ($domain['hostname'] ?? '')) === $host) {
                $current = $domain;
            }
        }

        if ($primary === null) {
            abort(503, 'El dominio principal todavía no está verificado.');
        }

        if ($current === null) {
            abort(404);
        }

        $primaryHost = (string) $primary['hostname'];

        if ($host !== $primaryHost) {
            return redirect()->away(
                'https://'.$primaryHost.$request->getRequestUri(),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        if (! $request->isSecure()) {
            return redirect()->secure(
                $request->getRequestUri(),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        return $next($request);
    }
}
