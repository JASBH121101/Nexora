<?php

namespace Tests\Unit;

use App\Core\Security\TotpService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_totp_matches_the_rfc_6238_sha1_six_digit_vector(): void
    {
        $service = new TotpService();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $service->codeForCounter($secret, 1));
    }

    public function test_totp_verifier_accepts_only_six_digits_within_the_configured_window(): void
    {
        $service = new TotpService();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $timestamp = 60;

        $this->assertSame(2, $service->verify($secret, $service->codeForCounter($secret, 2), $timestamp, 0));
        $this->assertNull($service->verify($secret, '12345', $timestamp));
        $this->assertNull($service->verify($secret, '12A456', $timestamp));
        $this->assertNull($service->verify($secret, $service->codeForCounter($secret, 4), $timestamp, 0));
    }

    public function test_invalid_base32_secret_and_negative_counter_are_rejected(): void
    {
        $service = new TotpService();

        $this->expectException(InvalidArgumentException::class);
        $service->codeForCounter('NOT-A-BASE32-SECRET', 1);
    }

    public function test_new_secrets_have_sufficient_base32_entropy_and_a_valid_provisioning_uri(): void
    {
        $service = new TotpService();
        $secret = $service->generateSecret();
        $uri = $service->provisioningUri('owner@example.com', 'NEXORA', $secret);

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/D', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
