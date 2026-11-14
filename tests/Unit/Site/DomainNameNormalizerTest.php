<?php

namespace Tests\Unit\Site;

use App\Core\Site\Domains\DomainNameNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DomainNameNormalizerTest extends TestCase
{
    public function test_hostname_is_normalized_to_lowercase_without_a_trailing_dot(): void
    {
        $normalizer = new DomainNameNormalizer();

        $this->assertSame('www.example.com', $normalizer->normalize(' WWW.Example.COM. '));
    }

    public function test_internationalized_hostname_is_normalized_when_intl_is_available(): void
    {
        if (! function_exists('idn_to_ascii')) {
            $this->markTestSkipped('The PHP intl extension is not installed in this test environment.');
        }

        $normalizer = new DomainNameNormalizer();

        $this->assertSame('xn--bcher-kva.example', $normalizer->normalize('bücher.example'));
    }

    public function test_protocol_path_port_ip_literal_and_local_names_are_rejected(): void
    {
        $normalizer = new DomainNameNormalizer();

        foreach ([
            'https://example.com',
            'example.com/path',
            'example.com:8443',
            '127.0.0.1',
            'localhost',
            '*.example.com',
            'shop..example.com',
            '-bad.example.com',
            'bad-.example.com',
            'shop.example.local',
        ] as $invalid) {
            try {
                $normalizer->normalize($invalid);
                $this->fail('Expected hostname to be rejected: '.$invalid);
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
