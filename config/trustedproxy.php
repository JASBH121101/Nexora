<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    |
    | Configure only the IP addresses or CIDR ranges of load balancers that
    | are directly in front of this installation. An empty value trusts no
    | forwarded headers. Do not use "*" unless the origin is network-restricted
    | so that only trusted proxy infrastructure can connect to it.
    |
    */
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
