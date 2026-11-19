<?php

namespace Tests\Unit\Library;

use App\Core\Library\Localization\TranslationCatalogAudit;
use PHPUnit\Framework\TestCase;

final class TranslationCatalogAuditTest extends TestCase
{
    public function test_existing_setup_and_validation_catalogs_have_structural_parity(): void
    {
        $root = dirname(__DIR__, 3);
        $spanishSetup = require $root.'/lang/es-419/setup.php';
        $englishSetup = require $root.'/lang/en/setup.php';
        $spanishValidation = require $root.'/lang/es-419/validation.php';
        $englishValidation = require $root.'/lang/en/validation.php';

        $setup = TranslationCatalogAudit::audit($spanishSetup, $englishSetup);
        $validation = TranslationCatalogAudit::audit($spanishValidation, $englishValidation);

        $this->assertTrue($setup['passed'], json_encode($setup, JSON_PRETTY_PRINT));
        $this->assertTrue($validation['passed'], json_encode($validation, JSON_PRETTY_PRINT));
        $this->assertSame(0, $setup['high_findings']);
        $this->assertSame(0, $validation['high_findings']);
    }

    public function test_audit_detects_missing_keys_shape_changes_and_placeholder_mismatches(): void
    {
        $result = TranslationCatalogAudit::audit(
            [
                'greeting' => 'Hola :name',
                'validation' => ['required' => 'El campo :attribute es obligatorio.'],
                'count' => ['one' => ':count elemento', 'other' => ':count elementos'],
            ],
            [
                'greeting' => 'Hello :user',
                'validation' => 'Required',
                'extra' => 'Unused',
            ],
        );

        $codes = array_column($result['findings'], 'code');

        $this->assertFalse($result['passed']);
        $this->assertContains('placeholder-mismatch', $codes);
        $this->assertContains('message-shape-mismatch', $codes);
        $this->assertContains('translation-missing', $codes);
        $this->assertContains('obsolete-translation', $codes);
    }

    public function test_obsolete_entries_are_reported_without_failing_structural_parity(): void
    {
        $result = TranslationCatalogAudit::audit(
            ['save' => 'Guardar'],
            ['save' => 'Save', 'legacy' => 'Old label'],
        );

        $this->assertTrue($result['passed']);
        $this->assertSame(0, $result['high_findings']);
        $this->assertSame('obsolete-translation', $result['findings'][0]['code']);
    }
}
