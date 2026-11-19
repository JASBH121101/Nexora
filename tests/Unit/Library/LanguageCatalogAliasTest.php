<?php

namespace Tests\Unit\Library;

use App\Core\Library\Localization\LanguageCatalog;
use PHPUnit\Framework\TestCase;

final class LanguageCatalogAliasTest extends TestCase
{
    public function test_supported_spanish_aliases_normalize_to_es_419(): void
    {
        $this->assertSame('es-419', LanguageCatalog::normalize('es-419'));
        $this->assertSame('es-419', LanguageCatalog::normalize('es_MX'));
        $this->assertSame('es-419', LanguageCatalog::normalize('es-NI'));
        $this->assertNull(LanguageCatalog::normalize('es-ES'));
        $this->assertNull(LanguageCatalog::normalize('es-INVALID'));
    }
}
