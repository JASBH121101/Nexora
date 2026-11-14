# NEXORA Site Engine

## Scope

The Site Engine is the public-facing catalogue for one independently installed NEXORA business. It is not a shared multi-tenant storefront.

### Implemented public pages

- Home catalogue with published products only, active categories, search, category filtering and pagination.
- Product detail pages with only published products and public custom fields available to the product's category.
- Public WhatsApp contact links using the configured business contacts.
- Product images remain conditional on the installation's photo setting.
- Product-card and product-detail prices use the shared CurrencyCatalog formatter. Decimal precision follows the configured ISO currency metadata; amounts remain decimal strings and are not converted to binary floats.
- Public product units use the shared Measurement Library label.

### Search and sharing metadata

- Escaped meta descriptions and page titles.
- Canonical URL for the catalogue root and each product detail.
- Open Graph fields for website/product pages and an optional product image.
- Twitter card type follows whether a public product image is available.
- /sitemap.xml lists the catalogue root and published product details only.
- /robots.txt permits public catalogue crawling, disallows /admin and /setup, and points to the XML sitemap.

The sitemap deliberately does not publish search terms, category filter query strings, draft products, admin routes, or installation routes.

## Public-data boundaries

- Draft and otherwise unpublished products must not appear in catalogue results, product detail pages, or sitemap entries.
- The product-detail controller loads only field definitions marked is_public and available for that product's category.
- Blade output remains escaped; custom field values are not emitted as raw HTML.
- Admin and setup paths are excluded from the crawler policy. This is not an authorization control: the backend middleware remains the actual access-control boundary.

## Validation

tests/Feature/PublicSiteEngineTest.php covers:

1. Published-only catalogue visibility and currency-specific price formatting.
2. Search and category filters.
3. Public custom-field rendering and private-field exclusion on product detail pages.
4. 404 behavior for unpublished product detail.
5. Sitemap membership and crawler directives.

The canonical Laravel CI workflow runs Pint plus the feature suite on SQLite and PostgreSQL 16. The PR must show both database jobs green before the Site Engine is considered certified.

## Deployment boundary

This branch does not change DNS, domain registration, hosting, TLS certificates, reverse proxies, or production configuration. HTTPS must be enabled at the selected hosting/VPS edge, and APP_URL must be set to the final HTTPS origin before generating canonical URLs and sitemap links. No public deployment has been performed by this implementation.
