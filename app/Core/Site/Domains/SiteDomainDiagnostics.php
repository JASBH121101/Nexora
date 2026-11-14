<?php

namespace App\Core\Site\Domains;

final class SiteDomainDiagnostics
{
    public function __construct(
        private readonly DnsRecordResolver $dns,
        private readonly TlsCertificateInspector $tls,
    ) {
    }

    /**
     * @return array{
     *   status:string,
     *   checked_at:string,
     *   dns_addresses:list<string>,
     *   expected_target_match:bool,
     *   txt_record_name:string,
     *   txt_match:bool,
     *   tls:array{status:string, checked_at:string, valid_from:?string, valid_until:?string}|null
     * }
     */
    public function run(string $hostname, string $token): array
    {
        $checkedAt = now()->toIso8601String();
        $expectedTargets = array_values(array_unique(array_filter(
            (array) config('nexora.site_dns_targets', []),
            static fn ($ip): bool => is_string($ip)
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false,
        )));
        $txtRecordName = '_nexora-verification.'.$hostname;

        $empty = [
            'checked_at' => $checkedAt,
            'dns_addresses' => [],
            'expected_target_match' => false,
            'txt_record_name' => $txtRecordName,
            'txt_match' => false,
            'tls' => null,
        ];

        if ($expectedTargets === []) {
            return ['status' => 'target_not_configured'] + $empty;
        }

        $addresses = $this->dns->resolveAddresses($hostname);
        $publicAddresses = array_values(array_unique(array_filter(
            $addresses,
            static fn ($ip): bool => is_string($ip)
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false,
        )));

        $targetMatch = $this->intersects($publicAddresses, $expectedTargets);

        $empty['dns_addresses'] = $publicAddresses;
        $empty['expected_target_match'] = $targetMatch;

        if (! $targetMatch) {
            return ['status' => 'dns_target_mismatch'] + $empty;
        }

        $txtRecords = $this->dns->resolveTxt($txtRecordName);
        $txtMatch = in_array($token, $txtRecords, true);
        $empty['txt_match'] = $txtMatch;

        if (! $txtMatch) {
            return ['status' => 'ownership_pending'] + $empty;
        }

        $tls = $this->tls->inspect($hostname, $publicAddresses);
        $empty['tls'] = $tls;

        return [
            'status' => ($tls['status'] ?? null) === 'valid' ? 'ready' : 'tls_invalid',
        ] + $empty;
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
