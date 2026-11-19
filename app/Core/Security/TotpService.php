<?php

namespace App\Core\Security;

use InvalidArgumentException;

final class TotpService
{
    private const PERIOD_SECONDS = 30;

    private const DIGITS = 6;

    public function generateSecret(): string
    {
        return $this->encodeBase32(random_bytes(20));
    }

    public function provisioningUri(string $account, string $issuer, string $secret): string
    {
        $issuer = trim($issuer) !== '' ? trim($issuer) : 'NEXORA';
        $label = rawurlencode($issuer.':'.$account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): ?int
    {
        if (preg_match('/^\d{6}$/D', $code) !== 1 || $window < 0 || $window > 2) {
            return null;
        }

        $currentCounter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $counter = $currentCounter + $offset;

            if ($counter < 0) {
                continue;
            }

            if (hash_equals($this->codeForCounter($secret, $counter), $code)) {
                return $counter;
            }
        }

        return null;
    }

    public function codeForCounter(string $secret, int $counter): string
    {
        if ($counter < 0) {
            throw new InvalidArgumentException('TOTP counter cannot be negative.');
        }

        $key = $this->decodeBase32($secret);
        $high = ($counter >> 32) & 0xFFFFFFFF;
        $low = $counter & 0xFFFFFFFF;
        $message = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = unpack('Nvalue', substr($hash, $offset, 4))['value'] & 0x7FFFFFFF;
        $modulo = 10 ** self::DIGITS;

        return str_pad((string) ($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function encodeBase32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', $bytes) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= $alphabet[($buffer >> $bits) & 0x1F];
                $buffer &= (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $encoded .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $encoded;
    }

    private function decodeBase32(string $secret): string
    {
        $secret = strtoupper(rtrim(trim($secret), '='));

        if ($secret === '' || preg_match('/^[A-Z2-7]+$/D', $secret) !== 1) {
            throw new InvalidArgumentException('Invalid TOTP secret.');
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $decoded = '';

        foreach (str_split($secret) as $character) {
            $value = strpos($alphabet, $character);

            if ($value === false) {
                throw new InvalidArgumentException('Invalid TOTP secret.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        if ($decoded === '') {
            throw new InvalidArgumentException('Invalid TOTP secret.');
        }

        return $decoded;
    }
}
