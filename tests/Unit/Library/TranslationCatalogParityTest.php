<?php

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

final class TranslationCatalogParityTest extends TestCase
{
    public function test_setup_and_validation_catalogues_have_matching_keys(): void
    {
        foreach (['setup', 'validation'] as $namespace) {
            $spanish = require base_path('lang/es-419/'.$namespace.'.php');
            $english = require base_path('lang/en/'.$namespace.'.php');

            $spanishKeys = array_keys($spanish);
            $englishKeys = array_keys($english);
            sort($spanishKeys);
            sort($englishKeys);

            $this->assertSame(
                $spanishKeys,
                $englishKeys,
                'Translation keys differ in the '.$namespace.' namespace.',
            );
        }
    }

    public function test_setup_and_validation_placeholders_match_between_locales(): void
    {
        foreach (['setup', 'validation'] as $namespace) {
            $spanish = require base_path('lang/es-419/'.$namespace.'.php');
            $english = require base_path('lang/en/'.$namespace.'.php');

            foreach ($spanish as $key => $spanishMessage) {
                $this->assertArrayHasKey($key, $english);
                $this->assertIsString($spanishMessage);
                $this->assertIsString($english[$key]);

                preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $spanishMessage, $spanishMatches);
                preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $english[$key], $englishMatches);

                $spanishPlaceholders = array_unique($spanishMatches[1]);
                $englishPlaceholders = array_unique($englishMatches[1]);
                sort($spanishPlaceholders);
                sort($englishPlaceholders);

                $this->assertSame(
                    $spanishPlaceholders,
                    $englishPlaceholders,
                    'Placeholder mismatch for '.$namespace.'.'.$key.'.',
                );
            }
        }
    }
}
