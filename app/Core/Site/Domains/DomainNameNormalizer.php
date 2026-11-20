<?php

namespace App\Core\Site\Domains;

use InvalidArgumentException;

final class DomainNameNormalizer
{
    public function normalize(string $input): string
    {
        $hostname = strtolower(rtrim(trim($input), '.'));

        if (
            $hostname === ''
            || strlen($hostname) > 253
            || preg_match('/[\s\x00-\x1F\x7F]/', $hostname)
            || strpbrk($hostname, ':/?#@[]\\\\') !== false
            || str_contains($hostname, '*')
        ) {
            throw new InvalidArgumentException('Introduce un dominio válido, sin protocolo, ruta, puerto ni comodines.');
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false || in_array($hostname, ['localhost'], true)) {
            throw new InvalidArgumentException('Debes utilizar un nombre DNS, no una dirección IP ni localhost.');
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii === false) {
                throw new InvalidArgumentException('El dominio contiene caracteres internacionales no válidos.');
            }

            $hostname = strtolower($ascii);
        }

        $labels = explode('.', $hostname);

        if (count($labels) < 2) {
            throw new InvalidArgumentException('El dominio debe incluir un nombre y un sufijo DNS.');
        }

        foreach ($labels as $label) {
            if (
                strlen($label) < 1
                || strlen($label) > 63
                || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) !== 1
            ) {
                throw new InvalidArgumentException('El dominio contiene una etiqueta DNS no válida.');
            }
        }

        $suffix = end($labels);

        if (preg_match('/^(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/', $suffix) !== 1) {
            throw new InvalidArgumentException('El sufijo del dominio no tiene un formato válido.');
        }

        if (str_ends_with($hostname, '.local') || str_ends_with($hostname, '.localhost')) {
            throw new InvalidArgumentException('No se aceptan nombres DNS locales.');
        }

        return $hostname;
    }
}
