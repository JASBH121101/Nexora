<?php

namespace App\Core\Export;

use JsonException;
use RuntimeException;

final class ExportRenderer
{
    /**
     * @param array<int, array<string, mixed>> $products
     */
    public static function json(array $products): string
    {
        try {
            return json_encode(
                ['products' => $products],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('No se pudo serializar el catálogo JSON.', previous: $exception);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    public static function csv(array $products): string
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('No se pudo preparar el flujo CSV.');
        }

        try {
            $headers = [
                'id', 'name', 'slug', 'category', 'status', 'description',
                'is_featured', 'quantity_available', 'prices_json', 'image_path', 'fields_json',
            ];
            fputcsv($handle, $headers, ',', '"', '');

            foreach ($products as $product) {
                fputcsv($handle, [
                    $product['id'] ?? '',
                    $product['name'] ?? '',
                    $product['slug'] ?? '',
                    $product['category'] ?? '',
                    $product['status'] ?? '',
                    $product['description'] ?? '',
                    isset($product['is_featured']) ? ($product['is_featured'] ? '1' : '0') : '',
                    $product['quantity_available'] ?? '',
                    json_encode($product['prices'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $product['image_path'] ?? '',
                    json_encode($product['fields'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], ',', '"', '');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            if ($contents === false) {
                throw new RuntimeException('No se pudo leer el flujo CSV.');
            }

            // UTF-8 BOM improves compatibility with common spreadsheet software.
            return "\xEF\xBB\xBF".$contents;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    public static function xml(array $products): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.'<catalog>'.PHP_EOL;

        foreach ($products as $product) {
            $xml .= "  <product id=\"".(int) ($product['id'] ?? 0)."\">".PHP_EOL;
            foreach (['name', 'slug', 'category', 'status', 'description', 'quantity_available', 'image_path'] as $key) {
                if (($product[$key] ?? null) !== null) {
                    $xml .= '    <'.str_replace('_', '-', $key).'>'
                        .self::escapeXml((string) $product[$key])
                        .'</'.str_replace('_', '-', $key).'>'.PHP_EOL;
                }
            }

            if (isset($product['is_featured'])) {
                $xml .= '    <is-featured>'.($product['is_featured'] ? 'true' : 'false').'</is-featured>'.PHP_EOL;
            }

            $xml .= "    <prices>".PHP_EOL;
            foreach (($product['prices'] ?? []) as $currency => $amount) {
                $xml .= '      <price currency="'.self::escapeXml((string) $currency).'">'
                    .self::escapeXml((string) $amount).'</price>'.PHP_EOL;
            }
            $xml .= "    </prices>".PHP_EOL";

            $xml .= "    <fields>".PHP_EOL;
            foreach (($product['fields'] ?? []) as $key => $value) {
                if ($value === null || is_array($value) || is_object($value)) {
                    continue;
                }
                $xml .= '      <field key="'.self::escapeXml((string) $key).'">'
                    .self::escapeXml(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
                    .'</field>'.PHP_EOL;
            }
            $xml .= "    </fields>".PHP_EOL";
            $xml .= "  </product>".PHP_EOL;
        }

        return $xml.'</catalog>'.PHP_EOL;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
