# NEXORA product image security

## Storage boundary

Product uploads are no longer written directly to the public disk.

1. Laravel validates the upload as JPEG, PNG or WebP, with a 5 MiB limit and maximum dimensions of 4000 × 4000.
2. The original is written to the private `product_quarantine` disk.
3. The processor checks the actual decoded image MIME and dimensions, decodes it with GD, and re-encodes only pixel data into a new PNG.
4. The normalized PNG is written to `private_media`; the untrusted quarantine copy is deleted.
5. The database stores only the opaque private path `products/{uuid}.png`.

The processor refuses uploads if GD is not available. The installer preflight and CI runtime both check/enable GD.

## Access controls

- Public image routes resolve the product by slug and return an image only while the product is published and photos are enabled.
- Admin image routes require authentication, the `products.view` route permission and the field-level `products.image.view` permission.
- Image routes set `Content-Type: image/png`, `X-Content-Type-Options: nosniff`, a restrictive content security policy and explicit cache controls.
- Product image paths are served from private storage. There is no public symlink or direct URL for the new files.
- The `public` disk remains available for other public assets; product image ingestion must not use it.

## Sanitization boundary

GD decode/re-encode removes untrusted container metadata and trailing/polyglot payload bytes from the served representation. It is a media sanitization control, **not** an antivirus verdict and not proof that arbitrary uploaded files are harmless. The application accepts raster image types only. This flow does not scan PDFs, office documents, archives or arbitrary attachments. Those require the JB Security Guard upload inspector with a real versioned scanner adapter and private storage implementation.

The processor enforces:
- Input: maximum 5 MiB.
- Dimensions: maximum 4000 × 4000 and 16 million pixels.
- Output: maximum 15 MiB.
- MIME allowlist: JPEG, PNG and WebP.
- Stored format: normalized PNG with a server-generated UUID filename.

## Existing public images

Existing records may still reference legacy paths in the public disk. Migrate them after taking a database and storage backup:

1. Preview the work. This command is non-mutating by default:

   ```bash
   php artisan nexora:secure-product-images
   ```

2. Review the product IDs and missing/unsupported paths.
3. Apply the migration:

   ```bash
   php artisan nexora:secure-product-images --apply
   ```

The command backs up each original under `storage/app/private-media-archive/legacy-public/{product-id}/`, processes it through the same sanitizer, updates the product path in a database transaction and removes the old public original only after the new private path is committed. Failed files remain in the public disk for manual review; a secure private backup may already exist if processing failed after backup.

Do not delete the private archive until the migrated catalog has been checked and a separate retention decision has been made.

## Operational checks

After deployment:
- Confirm `storage/app/product-quarantine`, `storage/app/private-media` and `storage/app/private-media-archive` are writable and outside the web root.
- Confirm no web-server alias exposes those directories.
- Confirm the application does not generate `asset('storage/...')` links for product images.
- Run the Laravel feature tests and inspect the image migration dry-run output before applying.
- Check that unpublished products return 404 from public image routes and that users without image-view permission receive 403 on admin image routes.
