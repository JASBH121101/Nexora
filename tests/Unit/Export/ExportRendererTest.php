<?php

namespace Tests\Unit\Export;

use App\Core\Export\ExportRenderer;
use PHPUnit\Framework\TestCase;

final class ExportRendererTest extends TestCase
{
    public function test_json_export_keeps_utf8_and_structured_fields(): void
    {
        $json = ExportRenderer::json([
            [
                'id' => 1,
                'name' => 'Café de prueba',
                'prices' => ['NIO' => '150.00'],
                'fields' => ['brand' => 'Marca'],
            ],
        ]);

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Café de prueba', $decoded['products'][0]['name']);
        self::assertSame('150.00', $decoded['products'][0]['prices']['NIO']);
    }

    public function test_csv_export_has_utf8_bom_and_neutralizes_formula_cells(): void
    {
        $csv = ExportRenderer::csv([
            [
                'id' => 1,
                'name' => '=HYPERLINK("https://example.invalid","click")',
                'slug' => '@cmd',
                'category' => '+SUM(1,1)',
                'status' => 'published',
                'description' => '-2+3',
                'prices' => ['NIO' => '150.00'],
                'fields' => ['brand' => 'Normal'],
            ],
        ]);

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString("'@cmd", $csv);
        self::assertStringContainsString("'+SUM", $csv);
        self::assertStringContainsString("'-2+3", $csv);
        self::assertStringContainsString('"{""NIO"":""150.00""}"', $csv);
    }

    public function test_xml_export_escapes_markup_and_serializes_nested_values(): void
    {
        $xml = ExportRenderer::xml([
            [
                'id' => 7,
                'name' => 'A & B <C>',
                'status' => 'published',
                'prices' => ['USD' => '4.50'],
                'fields' => ['brand' => 'A & B'],
            ],
        ]);

        self::assertStringContainsString('A &amp; B &lt;C&gt;', $xml);
        self::assertStringContainsString('<price currency="USD">4.50</price>', $xml);
        self::assertStringContainsString('A &amp; B', $xml);
    }
}
