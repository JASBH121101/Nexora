<?php

namespace Tests\Unit\Site;

use App\Core\Site\Domains\DnsRecordResolver;
use App\Core\Site\Domains\SiteDomainDiagnostics;
use App\Core\Site\Domains\TlsCertificateInspector;
use Tests\TestCase;

class SiteDomainDiagnosticsTest extends TestCase
{
    public function test_missing_vps_target_stops_before_dns_or_tls_checks(): void
    {
        config(['nexora.site_dns_targets' => []]);
        $dns = new FakeDomainDns(['1.1.1.1'], []);
        $tls = new FakeDomainTls('valid');

        $result = (new SiteDomainDiagnostics($dns, $tls))->run('shop.example.com', 'token');

        $this->assertSame('target_not_configured', $result['status']);
        $this->assertSame(0, $dns->addressLookups);
        $this->assertSame(0, $tls->calls);
    }

    public function test_wrong_dns_target_is_rejected_before_ownership_or_tls_checks(): void
    {
        config(['nexora.site_dns_targets' => ['1.1.1.1']]);
        $dns = new FakeDomainDns(['8.8.8.8'], ['_nexora-verification.shop.example.com' => ['token']]);
        $tls = new FakeDomainTls('valid');

        $result = (new SiteDomainDiagnostics($dns, $tls))->run('shop.example.com', 'token');

        $this->assertSame('dns_target_mismatch', $result['status']);
        $this->assertFalse($result['expected_target_match']);
        $this->assertSame(0, $tls->calls);
    }

    public function test_txt_ownership_must_match_before_tls_is_probed(): void
    {
        config(['nexora.site_dns_targets' => ['1.1.1.1']]);
        $dns = new FakeDomainDns(['1.1.1.1'], ['_nexora-verification.shop.example.com' => ['wrong-token']]);
        $tls = new FakeDomainTls('valid');

        $result = (new SiteDomainDiagnostics($dns, $tls))->run('shop.example.com', 'expected-token');

        $this->assertSame('ownership_pending', $result['status']);
        $this->assertFalse($result['txt_match']);
        $this->assertSame(0, $tls->calls);
    }

    public function test_dns_txt_and_tls_success_mark_domain_ready_and_pin_tls_to_configured_ips(): void
    {
        config(['nexora.site_dns_targets' => ['1.1.1.1']]);
        $dns = new FakeDomainDns(
            ['192.0.2.10', '8.8.8.8', '1.1.1.1'],
            ['_nexora-verification.shop.example.com' => ['expected-token']],
        );
        $tls = new FakeDomainTls('valid');

        $result = (new SiteDomainDiagnostics($dns, $tls))->run('shop.example.com', 'expected-token');

        $this->assertSame('ready', $result['status']);
        $this->assertTrue($result['expected_target_match']);
        $this->assertTrue($result['txt_match']);
        $this->assertSame(['1.1.1.1'], $tls->lastAddresses);
        $this->assertSame('valid', $result['tls']['status']);
    }

    public function test_domain_is_not_ready_when_tls_certificate_is_invalid(): void
    {
        config(['nexora.site_dns_targets' => ['1.1.1.1']]);
        $dns = new FakeDomainDns(['1.1.1.1'], ['_nexora-verification.shop.example.com' => ['expected-token']]);
        $tls = new FakeDomainTls('invalid');

        $result = (new SiteDomainDiagnostics($dns, $tls))->run('shop.example.com', 'expected-token');

        $this->assertSame('tls_invalid', $result['status']);
        $this->assertSame('invalid', $result['tls']['status']);
    }
}

final class FakeDomainDns implements DnsRecordResolver
{
    public int $addressLookups = 0;

    public function __construct(
        private readonly array $addresses,
        private readonly array $txtRecords,
    ) {
    }

    public function resolveAddresses(string $hostname): array
    {
        $this->addressLookups++;

        return $this->addresses;
    }

    public function resolveTxt(string $recordName): array
    {
        return $this->txtRecords[$recordName] ?? [];
    }
}

final class FakeDomainTls implements TlsCertificateInspector
{
    public int $calls = 0;

    public array $lastAddresses = [];

    public function __construct(private readonly string $status)
    {
    }

    public function inspect(string $hostname, array $publicAddresses): array
    {
        $this->calls++;
        $this->lastAddresses = $publicAddresses;

        return [
            'status' => $this->status,
            'checked_at' => '2026-11-14T12:00:00+00:00',
            'valid_from' => $this->status === 'valid' ? '2026-11-01T00:00:00+00:00' : null,
            'valid_until' => $this->status === 'valid' ? '2027-11-01T00:00:00+00:00' : null,
        ];
    }
}
