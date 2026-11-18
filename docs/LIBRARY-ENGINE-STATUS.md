# Estado del NEXORA Library Engine

## Revisión 0.2.1 — endurecimiento propuesto en rama aislada

El Library Engine 0.2.0 quedó integrado en `feature/nexora-permission-engine-v1` a través de la integración de Phase 2. El PR #7 fue validado y fusionado en esa rama; el PR #6 agrupa ahora la cadena Permission Engine + Library + Inventory + Export hacia `docs/nexora-approved-upgrades-2026-11`.

### Incluido

- Catálogos compartidos de país, moneda y códigos de llamada bajo `App\\Core\\Library`.
- Normalización telefónica que requiere un código de llamada seleccionado explícitamente.
- Idiomas canónicos `es-419` y `en`, con compatibilidad para alias históricos.
- Catálogo de unidades con identificadores estables, etiquetas localizadas y conversiones dimensionales.
- Metadatos ISO de moneda, símbolos, minor units, steps y placeholders; la unidad de cuatro decimales CLF está cubierta explícitamente.
- Normalización regional explícita: los alias españoles latinoamericanos conocidos se resuelven a es-419; es-ES y locales no soportados no se etiquetan incorrectamente como español latino.
- Validación monetaria con cadenas decimales y sin cálculos de precio basados en floats.
- Precios de producto con precisión `DECIMAL(16,4)` y migración aditiva.
- Referencias estables de catálogo para unidades, preservando `unit_code` y `unit_dimension` heredados.
- Integración en Setup, plantillas, edición de campos, precios y vista pública.
- Fachadas antiguas de Setup que delegan en catálogos compartidos.

### Compatibilidad y migraciones

- No se reemplazan unidades personalizadas o desconocidas.
- Se conservan los campos heredados y los datos existentes.
- La migración monetaria no reduce precisión; el rollback se bloquea si perdería decimales significativos.
- ProductController conserva las verificaciones del Permission Engine.

### Validación

- CurrencyCatalog, UnitCatalog, LibraryCatalog y normalización telefónica: pruebas unitarias aprobadas.
- Laravel Pint: aprobado.
- PHPUnit en SQLite: aprobado.
- PHPUnit en PostgreSQL 16: aprobado en la validación integrada.
- La comprobación de Expert QA sobre el repositorio también pasó.

La validación automatizada no reemplaza una revisión visual/manual del instalador y del panel en una instalación desechable. No se desplegó a producción.

### Límites

- Catálogos estáticos, sin llamadas a registros externos.
- La validación telefónica comprueba formato, no asignación real ni disponibilidad.
- Conversión de unidades no significa conversión automática de divisas.
- No se añade un proveedor cambiario externo ni un servicio de pago.


## Revisión 0.2.1 — cambios en validación

- Se incorpora CLF con cuatro decimales de unidad menor, precisión, step y placeholder.
- Se evita colapsar automáticamente `es-ES` y locales desconocidos dentro de `es-419`.
- Esta revisión está en una rama aislada y requiere CI y revisión antes de integrarse.
