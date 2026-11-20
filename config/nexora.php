<?php

$siteDnsTargets = preg_split('/[,;\\s]+/', (string) env('NEXORA_SITE_DNS_TARGETS', ''), -1, PREG_SPLIT_NO_EMPTY);

return [
    /*
    |--------------------------------------------------------------------------
    | Installation environment
    |--------------------------------------------------------------------------
    |
    | "demo" enables the protected demonstration reset workflow. Production
    | installations must leave this set to "production".
    |
    */
    'installation_mode' => env('NEXORA_INSTALLATION_MODE', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Site Engine DNS targets
    |--------------------------------------------------------------------------
    |
    | Public IPv4/IPv6 addresses assigned to this installation's web server.
    | Only these addresses are accepted by the domain diagnostics workflow.
    | Configure as a comma-separated list in the server environment.
    |
    */
    'site_dns_targets' => array_values(array_unique($siteDnsTargets ?: [])),
];
