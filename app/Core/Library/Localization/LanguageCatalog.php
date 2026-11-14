<?php

namespace App\Core\Library\Localization;

final class LanguageCatalog
{
    /** First-release interface locales for NEXORA. */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function all(string $locale = 'es-419'): array
    {
        $isEnglish = str_starts_with(strtolower(str_replace('_', '-', $locale)), 'en');

        return [
            'es-419' => $isEnglish ? 'Spanish (Latin America)' : 'Español (Latinoamérica)',
            'en' => 'English',
        ];
    }

    public static function normalize(string $locale): ?string
    {
        $normalized = strtolower(str_replace('_', '-', trim($locale)));

        if ($normalized === 'es' || str_starts_with($normalized, 'es-')) {
            return 'es-419';
        }

        if ($normalized === 'en' || str_starts_with($normalized, 'en-')) {
            return 'en';
        }

        return null;
    }
}
