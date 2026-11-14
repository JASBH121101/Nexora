# NEXORA Export Engine — first implementation

## Available formats

- **CSV:** UTF-8 with BOM, safe quoting and spreadsheet-formula neutralization for text cells.
- **JSON:** UTF-8, structured product records and dynamic fields.
- **XML:** escaped XML elements and attributes.
- **ZIP:** generated only when the PHP `ZipArchive` extension is available. It contains CSV/JSON/XML, selected filter metadata and permitted original product images.

PDF and XLSX are intentionally not enabled in this change. The source repository's current `composer.lock` contains no PDF or spreadsheet generation package. Adding either library requires a deliberate dependency choice and deployment validation against the customer's VPS/PHP version; no package is installed implicitly.

## Filtering and permissions

The export screen supports publication status, category, product IDs, dynamic field keys and explicit options for prices, basic inventory and photos. The dataset builder enforces `products.view`, per-field permissions and the existing `exports.create` / `exports.download` actions. A caller cannot request prices, inventory or photos without the relevant read permission. Dynamic fields are included only when the current user can view those fields.

The initial implementation caps the dataset at 5,000 products per export. The formal export size/retention limits remain owner decisions.

## Photos

CSV, JSON and XML contain relative media paths only. ZIP can include original images from the local public storage disk after path validation. Symlinks and paths outside the storage root are ignored.

## History and storage

Each completed or failed attempt records the actor, format, status, file name, byte size, record count, selected filters and completion time. Export files are generated per request and streamed to the requester; they are not stored as publicly accessible files. ZIP temporary files are deleted after response delivery.

The history page shows only the latest 50 executions. Access requires `exports.history.view`. No history expiry policy is applied yet because retention duration was left for owner approval. The metadata remains in the database; the generated file itself is not retained.

## Security notes

- Exports require both `exports.create` and `exports.download`.
- The dataset is rebuilt server-side; submitted field names and filters are never treated as authorization.
- Price, description and other field-level restrictions are preserved.
- CSV text cells beginning with spreadsheet formula characters are prefixed to prevent execution by spreadsheet software.
- XML output escapes reserved characters.
- Original image paths are validated before inclusion in ZIP.
- Export errors are logged without including payloads or exception messages in the UI.

## Current limits

- PDF: pending dependency decision and VPS verification.
- XLSX: pending dependency decision and VPS verification.
- ZIP: conditional on PHP ZipArchive; it is not a new mandatory server requirement.
- Format/size/retention policies still need owner approval.
- The UI shows unavailable PDF/XLSX formats as disabled rather than silently creating a different format.
