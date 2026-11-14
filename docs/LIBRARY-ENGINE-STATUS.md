# Estado del NEXORA Library Engine

## Revisión 0.2.0 — rama de trabajo

La rama `feature/nexora-phase2-approved-upgrades` contiene la extracción de catálogos compartidos y su integración gradual en el repositorio fuente de NEXORA.

### Incluido en la rama

- Catálogos de país, moneda y códigos de llamada bajo `App\Core\Library`.
- Normalización telefónica que exige un código de llamada seleccionado expresamente.
- Idiomas canónicos `es-419` y `en`, con normalización de alias históricos.
- Catálogo de unidades con identificadores estables, etiquetas localizadas y conversiones por dimensión.
- Metadatos de monedas, símbolos, minor units, steps y placeholders.
- Validación de precios por moneda usando cadenas decimales y sin decisiones basadas en floats.
- `ProductPrice` con cast de cuatro decimales y migración de precisión `DECIMAL(16,4)`.
- Columna aditiva `unit_catalog_code`, backfill limitado a unidades conocidas y compatibilidad con `unit_code`.
- Integración en Setup, plantillas, edición de campos, precios y vista pública del producto.
- Fachadas Setup deprecadas que delegan en la biblioteca compartida.
- Tests unitarios de moneda, unidad, normalización telefónica e integridad.
- Recursos de instalación español en `lang/es-419`, conservando `lang/es`.

### Reglas de compatibilidad

- No se sobrescriben valores de unidades personalizadas o desconocidas.
- Los campos anteriores `unit_code` y `unit_dimension` se conservan.
- La migración de moneda no reduce precisión en datos existentes.
- El rollback de DECIMAL(16,4) a DECIMAL(14,2) se bloquea si existen montos con decimales distintos de cero más allá de dos posiciones.
- ProductController conserva las verificaciones de Permission Engine por campo; la biblioteca solo cambia la validación monetaria.

### Límites y certificación pendiente

- Los catálogos son snapshots estáticos; no se consultan registros externos en tiempo de ejecución.
- Los códigos telefónicos validan forma E.164, no asignación o disponibilidad real.
- La conversión de unidades usa factores de catálogo; no representa conversiones de divisas.
- Los tests se añadieron, pero PHPUnit y Pint siguen pendientes de ejecutar con Composer en un entorno habilitado.
- No se ha fusionado ni desplegado esta rama.
