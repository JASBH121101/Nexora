<?php

namespace App\Core\Site\Domains;

final class NativeDnsRecordResolver implements DnsRecordResolver
{
    public function resolveAddresses(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    public function resolveTxt(string $recordName): array
    {
        $records = @dns_get_record($recordName, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            $value = $record['txt'] ?? null;

            if (is_string($value)) {
                $values[] = trim($value);

                continue;
            }

            if (is_array($record['entries'] ?? null)) {
                $values[] = trim(implode('', $record['entries']));
            }
        }

        return array_values(array_filter(array_unique($values), static fn (string $value): bool => $value !== ''));
    }
}
