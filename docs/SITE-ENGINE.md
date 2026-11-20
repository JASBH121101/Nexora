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

## Custom domains, DNS and TLS

Owner-only domain management is implemented in [SITE-DOMAIN-DNS-TLS.md](SITE-DOMAIN-DNS-TLS.md) and integrated through PR #11. Each installation can register up to five customer-owned domains or external subdomains. Ownership is verified with a unique DNS TXT challenge; A/AAAA answers must match public IPs configured by the hosting operator in `NEXORA_SITE_DNS_TARGETS`. TLS diagnostics connect only to matching configured IPs on port 443, validate the hostname with SNI and require a trusted, currently valid certificate. A domain can become primary only after all checks pass. Verified aliases redirect to the primary hostname, and the primary public host enforces HTTPS.

The CI adapters are deterministic fakes: they validate application behavior but do not query public DNS, make external TLS connections, configure DNS providers, issue certificates or operate a VPS. The feature is code-integrated; a real publication still requires the customer's domain, actual VPS IP, DNS records, web-server virtual host, certificate and canonical `APP_URL`.

## Deployment boundary

The application does not change DNS records, register domains, configure hosting/reverse proxies, issue or renew TLS certificates, or deploy production. HTTPS must be enabled at the selected hosting/VPS edge, and `APP_URL` must be set to the final HTTPS origin before generating canonical URLs and sitemap links. No public deployment has been performed by this implementation.
