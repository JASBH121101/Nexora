<?php

namespace App\Core\Site\Domains;

final class NativeTlsCertificateInspector implements TlsCertificateInspector
{
    public function inspect(string $hostname, array $publicAddresses): array
    {
        $checkedAt = now()->toIso8601String();

        if (! extension_loaded('openssl') || ! function_exists('stream_socket_client')) {
            return $this->result('unsupported', $checkedAt);
        }

        foreach ($publicAddresses as $address) {
            if (
                ! is_string($address)
                || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                continue;
            }

            $target = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                ? 'tls://['.$address.']:443'
                : 'tls://'.$address.':443';

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'peer_name' => $hostname,
                    'SNI_enabled' => true,
                    'capture_peer_cert' => true,
                ],
                'tcp' => [
                    'connect_timeout' => 3,
                ],
            ]);

            $socket = @stream_socket_client(
                $target,
                $errorCode,
                $errorMessage,
                3,
                STREAM_CLIENT_CONNECT,
                $context,
            );

            if ($socket === false) {
                continue;
            }

            try {
                $parameters = stream_context_get_params($socket);
                $certificate = $parameters['options']['ssl']['peer_certificate'] ?? null;

                if ($certificate === null) {
                    continue;
                }

                $parsed = @openssl_x509_parse($certificate);

                if (! is_array($parsed)) {
                    continue;
                }

                $validFrom = isset($parsed['validFrom_time_t'])
                    ? (int) $parsed['validFrom_time_t']
                    : null;
                $validUntil = isset($parsed['validTo_time_t'])
                    ? (int) $parsed['validTo_time_t']
                    : null;
                $now = time();

                if (
                    $validFrom === null
                    || $validUntil === null
                    || $validFrom > $now
                    || $validUntil < $now
                ) {
                    return $this->result(
                        'invalid',
                        $checkedAt,
                        $validFrom,
                        $validUntil,
                    );
                }

                return $this->result(
                    'valid',
                    $checkedAt,
                    $validFrom,
                    $validUntil,
                );
            } finally {
                fclose($socket);
            }
        }

        return $this->result('invalid', $checkedAt);
    }

    private function result(
        string $status,
        string $checkedAt,
        ?int $validFrom = null,
        ?int $validUntil = null,
    ): array {
        return [
            'status' => $status,
            'checked_at' => $checkedAt,
            'valid_from' => $validFrom === null ? null : gmdate(DATE_ATOM, $validFrom),
            'valid_until' => $validUntil === null ? null : gmdate(DATE_ATOM, $validUntil),
        ];
    }
}
