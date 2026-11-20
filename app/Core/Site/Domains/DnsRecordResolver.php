<?php

namespace App\Core\Site\Domains;

interface DnsRecordResolver
{
    /** @return list<string> */
    public function resolveAddresses(string $hostname): array;

    /** @return list<string> */
    public function resolveTxt(string $recordName): array;
}
