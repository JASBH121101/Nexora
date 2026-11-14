<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Site\Domains\DomainNameNormalizer;
use App\Core\Site\Domains\SiteDomainDiagnostics;
use App\Models\InstallationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SiteDomainController
{
    public function __construct(
        private readonly DomainNameNormalizer $normalizer,
        private readonly SiteDomainDiagnostics $diagnostics,
    ) {
    }

    public function index(): View
    {
        $settings = InstallationSettings::query()->findOrFail(1);
        $configuration = $settings->configuration ?? [];
        $site = (array) data_get($configuration, 'site', []);

        return view('admin.site-domains.index', [
            'domains' => array_values((array) ($site['domains'] ?? [])),
            'primaryDomainId' => $site['primary_domain_id'] ?? null,
            'dnsTargets' => (array) config('nexora.site_dns_targets', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
            'type' => ['required', 'in:custom_domain,external_subdomain'],
        ]);

        try {
            $hostname = $this->normalizer->normalize($validated['hostname']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'hostname' => $exception->getMessage(),
            ]);
        }

        $domain = [
            'id' => (string) Str::uuid(),
            'hostname' => $hostname,
            'type' => $validated['type'],
            'verification' => [
                'txt_record_name' => '_nexora-verification.'.$hostname,
                'token' => Str::random(40),
            ],
            'status' => 'pending',
            'is_primary' => false,
            'created_at' => now()->toIso8601String(),
            'last_checked_at' => null,
            'verified_at' => null,
            'diagnostics' => null,
        ];

        $this->mutateConfiguration(function (array $configuration) use ($domain): array {
            $site = (array) data_get($configuration, 'site', []);
            $domains = array_values((array) ($site['domains'] ?? []));

            if (count($domains) >= 5) {
                throw ValidationException::withMessages([
                    'hostname' => 'Puedes registrar un máximo de cinco dominios por instalación.',
                ]);
            }

            foreach ($domains as $existing) {
                if (($existing['hostname'] ?? null) === $domain['hostname']) {
                    throw ValidationException::withMessages([
                        'hostname' => 'Ese dominio ya está registrado.',
                    ]);
                }
            }

            $domains[] = $domain;
            $site['domains'] = $domains;
            data_set($configuration, 'site', $site);

            return $configuration;
        });

        return redirect()
            ->route('admin.site-domains.index')
            ->with('status', 'Dominio agregado. Configura los registros DNS indicados y ejecuta la comprobación.');
    }

    public function diagnose(string $domainId): RedirectResponse
    {
        $configuration = InstallationSettings::query()->findOrFail(1)->configuration ?? [];
        $domain = $this->findDomain((array) data_get($configuration, 'site.domains', []), $domainId);

        if ($domain === null) {
            throw new NotFoundHttpException();
        }

        $verification = (array) ($domain['verification'] ?? []);
        $result = $this->diagnostics->run(
            (string) $domain['hostname'],
            (string) ($verification['token'] ?? ''),
        );
        $status = (string) $result['status'];

        $this->mutateConfiguration(function (array $configuration) use ($domainId, $result, $status): array {
            $site = (array) data_get($configuration, 'site', []);
            $domains = array_values((array) ($site['domains'] ?? []));
            $found = false;

            foreach ($domains as &$item) {
                if (($item['id'] ?? null) !== $domainId) {
                    continue;
                }

                $item['status'] = $status;
                $item['last_checked_at'] = $result['checked_at'];
                $item['verified_at'] = $status === 'ready' ? $result['checked_at'] : null;
                $item['diagnostics'] = $result;
                $found = true;
                break;
            }
            unset($item);

            if (! $found) {
                throw new NotFoundHttpException();
            }

            $site['domains'] = $domains;
            data_set($configuration, 'site', $site);

            return $configuration;
        });

        return redirect()
            ->route('admin.site-domains.index')
            ->with('status', $this->statusMessage($status));
    }

    public function makePrimary(string $domainId): RedirectResponse
    {
        $this->mutateConfiguration(function (array $configuration) use ($domainId): array {
            $site = (array) data_get($configuration, 'site', []);
            $domains = array_values((array) ($site['domains'] ?? []));
            $found = false;

            foreach ($domains as &$domain) {
                if (($domain['id'] ?? null) !== $domainId) {
                    $domain['is_primary'] = false;
                    continue;
                }

                if (($domain['status'] ?? null) !== 'ready' || empty($domain['verified_at'])) {
                    throw ValidationException::withMessages([
                        'domain' => 'Solo puedes publicar como principal un dominio con DNS, propiedad y TLS verificados.',
                    ]);
                }

                $domain['is_primary'] = true;
                $found = true;
            }
            unset($domain);

            if (! $found) {
                throw new NotFoundHttpException();
            }

            $site['domains'] = $domains;
            $site['primary_domain_id'] = $domainId;
            data_set($configuration, 'site', $site);

            return $configuration;
        });

        return redirect()
            ->route('admin.site-domains.index')
            ->with('status', 'Dominio principal actualizado.');
    }

    public function destroy(string $domainId): RedirectResponse
    {
        $this->mutateConfiguration(function (array $configuration) use ($domainId): array {
            $site = (array) data_get($configuration, 'site', []);
            $domains = array_values((array) ($site['domains'] ?? []));
            $remaining = array_values(array_filter(
                $domains,
                static fn (array $domain): bool => ($domain['id'] ?? null) !== $domainId,
            ));

            if (count($remaining) === count($domains)) {
                throw new NotFoundHttpException();
            }

            $site['domains'] = $remaining;

            if (($site['primary_domain_id'] ?? null) === $domainId) {
                unset($site['primary_domain_id']);
            }

            data_set($configuration, 'site', $site);

            return $configuration;
        });

        return redirect()
            ->route('admin.site-domains.index')
            ->with('status', 'Dominio eliminado de esta instalación.');
    }

    /**
     * Serialize JSONB updates to avoid losing concurrent configuration changes.
     *
     * @param callable(array<string, mixed>):array<string, mixed> $mutator
     */
    private function mutateConfiguration(callable $mutator): void
    {
        DB::transaction(function () use ($mutator): void {
            $settings = InstallationSettings::query()
                ->lockForUpdate()
                ->findOrFail(1);
            $configuration = (array) ($settings->configuration ?? []);
            $settings->forceFill([
                'configuration' => $mutator($configuration),
            ])->save();
        });
    }

    /** @param array<int, array<string, mixed>> $domains */
    private function findDomain(array $domains, string $domainId): ?array
    {
        foreach ($domains as $domain) {
            if (($domain['id'] ?? null) === $domainId) {
                return $domain;
            }
        }

        return null;
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'ready' => 'Dominio verificado: DNS, propiedad y certificado TLS válidos.',
            'target_not_configured' => 'Configura primero las IP públicas del VPS en NEXORA_SITE_DNS_TARGETS.',
            'dns_target_mismatch' => 'El DNS todavía no apunta a una IP pública configurada para este VPS.',
            'ownership_pending' => 'El DNS apunta al VPS, pero el registro TXT de propiedad no coincide.',
            'tls_invalid' => 'DNS y propiedad coinciden, pero HTTPS no presentó un certificado válido para este dominio.',
            default => 'No se pudo completar la comprobación del dominio.',
        };
    }
}
