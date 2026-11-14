# NEXORA Library Engine 0.2.0

## Contract

Shared reference data lives under `app/Core/Library` and is organized by domain:

- **Geo:** country codes, country-to-currency mappings and country calling codes.
- **Money:** supported currency codes, symbols, minor units, precision validation and display formatting.
- **Localization:** canonical interface locales `es-419` and `en`.
- **Measurement:** stable unit codes, localized labels and same-dimension conversion.
- **Contact:** E.164-shape normalization using an explicitly selected calling code.
- **LibraryCatalog:** version and internal cross-catalogue integrity checks.

The release marker is 0.2.0. These are Laravel application services, not independently installable Composer packages.

## Compatibility and data migration

Legacy Setup service classes remain as deprecated facades. New code should depend on `App\Core\Library`.

The `unit_catalog_code` column is additive. The legacy `unit_code` and `unit_dimension` values remain available for compatibility and custom units. The migration backfills only recognized unit/dimension combinations.

Product prices are stored as `DECIMAL(16,4)`. Validation uses decimal strings and each currency's minor-unit precision. No float conversion is used to decide whether a price is valid. Public display keeps the ISO currency code visible and applies the configured locale's separators.

The price precision migration refuses to roll back while data contains non-zero precision beyond two decimal places.

## Locale policy

The canonical locales are:

- `es-419`: Latin American Spanish, default and fallback.
- `en`: English.

The legacy code `es` and generic Spanish regional tags normalize to `es-419`; English regional tags normalize to `en`. This first release does not maintain separate `es-MX` and `es-ES` catalogues. The installer retains the legacy `lang/es` resource while adding the canonical `lang/es-419` resource.

## Phone normalization

`PhoneNumberNormalizer::toE164($callingCode, $nationalNumber)` returns an E.164-shaped number or null. It requires an explicit calling code from the supported calling-code catalogue and accepts only digits plus common presentation characters.

This validates shape only. It does not verify national numbering-plan allocation, carrier assignment, ownership or reachability. Prefix guessing is deprecated because shared calling plans make it ambiguous.

## Data integrity limitations

`LibraryCatalog::integrityReport()` checks internal uniqueness, key formats, cross-reference consistency, unit metadata and locale code shape. It is not proof that the bundled data matches the latest official ISO publication. Catalogues are static; there is no runtime network synchronization.

Currency symbols are display hints, not identifiers. The ISO code must remain visible with each amount.

## Dependencies and cost

No new Composer package, external API, paid service or local installation is required for this library.

## Validation status

Tests are included under `tests/Unit/Library` and the new unit directory is registered in `phpunit.xml`. They have not yet been executed in a Composer-enabled environment for this branch. Do not treat 0.2.0 as certified until PHPUnit and Pint pass on the current branch.
