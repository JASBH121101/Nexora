<?php

namespace App\Core\Site\Domains;

final class SiteDomainDiagnostics
{
    public function __construct(
        private readonly DnsRecordResolver $dns,
        private readonly TlsCertificateInspector $tls,
    ) {}

    public function run(string $hostname, string $token): array
    {
        $checkedAt = now()->toIso8601String();
        $expectedTargets = array_values(array_unique(array_filter(
            (array) config('nexora.site_dns_targets', []),
            static fn ($ip): bool => is_string($ip)
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false,
        )));
        $txtRecordName = '_nexora-verification.'.$hostname;

        $result = [
            'checked_at' => $checkedAt,
            'dns_addresses' => [],
            'expected_target_match' => false,
            'txt_record_name' => $txtRecordName,
            'txt_match' => false,
            'tls' => null,
        ];

        if ($expectedTargets === []) {
            return ['status' => 'target_not_configured'] + $result;
        }

        $addresses = $this->dns->resolveAddresses($hostname);
        $publicAddresses = array_values(array_unique(array_filter(
            $addresses,
            static fn ($ip): bool => is_string($ip)
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false,
        )));
        $matchingAddresses = array_values(array_filter(
            $publicAddresses,
            fn (string $address): bool => $this->intersects([$address], $expectedTargets),
        ));

        $result['dns_addresses'] = $publicAddresses;
        $result['expected_target_match'] = $matchingAddresses !== [];

        if ($matchingAddresses === []) {
            return ['status' => 'dns_target_mismatch'] + $result;
        }

        $txtMatch = in_array($token, $this->dns->resolveTxt($txtRecordName), true);
        $result['txt_match'] = $txtMatch;

        if (! $txtMatch) {
            return ['status' => 'ownership_pending'] + $result;
        }

        // Only connect to the exact public IPs configured for this VPS, never
        // to additional addresses that the supplied hostname may also resolve to.
        $tls = $this->tls->inspect($hostname, $matchingAddresses);
        $result['tls'] = $tls;

        return ['status' => ($tls['status'] ?? null) === 'valid' ? 'ready' : 'tls_invalid'] + $result;
    }

    /** @param list<string> $left @param list<string> $right */
    private function intersects(array $left, array $right): bool
    {
        foreach ($left as $leftAddress) {
            $leftPacked = @inet_pton($leftAddress);

            if ($leftPacked === false) {
                continue;
            }

            foreach ($right as $rightAddress) {
                $rightPacked = @inet_pton($rightAddress);

                if ($rightPacked !== false && hash_equals($leftPacked, $rightPacked)) {
                    return true;
                }
            }
        }

        return false;
    }
}
