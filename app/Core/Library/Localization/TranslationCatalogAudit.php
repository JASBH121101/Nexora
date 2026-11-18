<?php

namespace App\Core\Library\Localization;

final class TranslationCatalogAudit
{
    /**
     * Compare two nested Laravel translation arrays structurally.
     * This checks key/placeholder parity only; it cannot certify linguistic quality.
     */
    public static function audit(array $source, array $target): array
    {
        $sourceEntries = self::flatten($source);
        $targetEntries = self::flatten($target);
        $findings = [];

        foreach ($sourceEntries as $key => $sourceValue) {
            if (! array_key_exists($key, $targetEntries)) {
                $findings[] = [
                    'key' => $key,
                    'code' => 'translation-missing',
                    'severity' => 'high',
                ];

                continue;
            }

            $targetValue = $targetEntries[$key];

            if (get_debug_type($sourceValue) !== get_debug_type($targetValue)) {
                $findings[] = [
                    'key' => $key,
                    'code' => 'message-shape-mismatch',
                    'severity' => 'high',
                ];

                continue;
            }

            if (is_string($sourceValue) && is_string($targetValue)) {
                $sourcePlaceholders = self::placeholders($sourceValue);
                $targetPlaceholders = self::placeholders($targetValue);

                if ($sourcePlaceholders !== $targetPlaceholders) {
                    $findings[] = [
                        'key' => $key,
                        'code' => 'placeholder-mismatch',
                        'severity' => 'high',
                        'expected' => $sourcePlaceholders,
                        'actual' => $targetPlaceholders,
                    ];
                }
            }
        }

        foreach ($targetEntries as $key => $_value) {
            if (! array_key_exists($key, $sourceEntries)) {
                $findings[] = [
                    'key' => $key,
                    'code' => 'obsolete-translation',
                    'severity' => 'low',
                ];
            }
        }

        $high = count(array_filter(
            $findings,
            static fn (array $finding): bool => $finding['severity'] === 'high',
        ));

        return [
            'passed' => $high === 0,
            'source_keys' => count($sourceEntries),
            'target_keys' => count($targetEntries),
            'high_findings' => $high,
            'findings' => $findings,
        ];
    }

    private static function flatten(array $catalog, string $prefix = ''): array
    {
        $entries = [];

        foreach ($catalog as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                foreach (self::flatten($value, $path) as $nestedKey => $nestedValue) {
                    $entries[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $entries[$path] = $value;
        }

        ksort($entries);

        return $entries;
    }

    private static function placeholders(string $message): array
    {
        preg_match_all('/(?<!:):([A-Za-z][A-Za-z0-9_]*)/', $message, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
