<?php

namespace App\Modules\Setup\Services;

final class LanguageCatalog
{
    /**
     * Languages currently offered as NEXORA installation defaults.
     * Additional languages must be enabled after their interface translations exist.
     */
    public static function all(): array
    {
        return [
            'es' => 'Español',
            'en' => 'English',
        ];
    }
}
