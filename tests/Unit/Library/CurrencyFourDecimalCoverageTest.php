<?php

namespace Tests\Unit\Library;

use App\Core\Library\Money\CurrencyCatalog;
use PHPUnit\Framework\TestCase;

final class CurrencyFourDecimalCoverageTest extends TestCase
{
    public function test_uyw_uses_four_decimal_places_without_float_conversion(): void
    {
        $this->assertSame(4, CurrencyCatalog::minorUnits('UYW'));
        $this->assertTrue(CurrencyCatalog::isValidAmount('1250.1234', 'UYW'));
        $this->assertFalse(CurrencyCatalog::isValidAmount('1250.12345', 'UYW'));
        $this->assertSame('UYW 1.234,5000 UYW', CurrencyCatalog::formatAmount('1234.5000', 'UYW', 'es'));
        $this->assertSame('0.0001', CurrencyCatalog::step('UYW'));
        $this->assertSame('0.0000', CurrencyCatalog::placeholder('UYW'));
    }
}
