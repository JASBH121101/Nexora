<?php

namespace App\Core\Site\Domains;

interface TlsCertificateInspector
{
    /**
     * Inspect HTTPS using a previously resolved and pinned public IP address.
     *
     * @param list<string> $publicAddresses
     * @return array{status:string, checked_at:string, valid_from:?string, valid_until:?string}
     */
    public function inspect(string $hostname, array $publicAddresses): array;
}
