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

        if (preg_match('/^es(?:-(?:419|ni|mx|gt|hn|cr|pa|sv|do|co|ar|cl|pe|uy|py|bo|ve|ec))?$/D', $normalized) === 1) {
            return 'es-419';
        }

        if (preg_match('/^en(?:-(?:[a-z]{2}|[0-9]{3}))?$/D', $normalized) === 1) {
            return 'en';
        }

        return null;
    }
}
