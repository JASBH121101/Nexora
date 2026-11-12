# NEXORA — Modelo de datos

**Estado:** diseño lógico previo a implementación.

## 1. Principio

Una instalación representa un solo negocio. El MVP reutiliza las entidades nativas de WordPress y no crea tablas propias. Las claves de metadatos usan prefijo `_nexora_` y las opciones usan `nexora_`.

## 2. Producto

Tipo de contenido: `nexora_product`.

| Campo | Persistencia | Regla |
|---|---|---|
| Nombre | `post_title` | Obligatorio, texto |
| Descripción | `post_content` | Opcional, contenido permitido y saneado |
| Resumen | `post_excerpt` | Opcional |
| Estado | `post_status` | `draft` o `publish` |
| Slug | `post_name` | Gestionado por WordPress |
| Precio | `_nexora_price` | Decimal no negativo, opcional |
| Precio anterior | `_nexora_old_price` | Decimal y mayor al precio vigente |
| SKU | `_nexora_sku` | Opcional, texto corto |
| Destacado | `_nexora_featured` | Booleano |
| Atributos | `_nexora_attributes` | Lista limitada etiqueta/valor |
| Galería | `_nexora_gallery_ids` | Lista ordenada de IDs de adjuntos |

La imagen principal usa la imagen destacada nativa `_thumbnail_id`.

## 3. Categorías

Taxonomía: `nexora_category`.

Se almacenan en las tablas nativas de términos, taxonomías y relaciones. Cada término tiene nombre, slug y descripción opcional. Orden manual, si es necesario, se guarda en un metadato de término con prefijo Nexora.

## 4. Perfil comercial

Opción: `nexora_business_profile` en `wp_options`.

| Clave | Tipo | Validación |
|---|---|---|
| business_name | Texto | Obligatorio para publicar |
| tagline | Texto | Opcional |
| logo_attachment_id | ID adjunto | Debe ser imagen válida |
| primary_color / accent_color | Hex | Validación estricta |
| whatsapp_number | Texto | Dígitos con código de país, 8–15 |
| contact_message | Texto | Longitud limitada |
| email | Email | Validación de formato |
| address | Texto | Opcional |
| currency | Código | Moneda de presentación |
| show_prices | Booleano | Visibilidad de precio |
| social_links | Lista | Solo URL HTTP/HTTPS permitidas |

No guardar contraseñas, tokens ni claves de acceso en esta opción.

## 5. Usuarios y roles

WordPress guarda cuentas en `wp_users` y capacidades/roles en `wp_usermeta`.

- `administrator`: administración técnica del sitio.
- `nexora_catalog_manager`: personal del negocio con capacidades mínimas de catálogo.

Cada persona autorizada debe tener su propia cuenta.

Capacidades conceptuales:
- `edit_nexora_products`
- `edit_others_nexora_products`
- `publish_nexora_products`
- `delete_nexora_products`
- `manage_nexora_categories`
- `manage_nexora_profile`

La implementación debe probar la asignación real para crear, editar, publicar, borrar y administrar perfil.

## 6. Medios

Cada imagen es un adjunto WordPress y un archivo en uploads. La imagen destacada es nativa; la galería contiene IDs; el logo es un ID almacenado en el perfil. No guardar binarios en MySQL ni aceptar URLs arbitrarias como almacenamiento de imágenes.

Al eliminar una imagen, comprobar que no esté referenciada desde otro producto o perfil.

## 7. Atributos por sector

Pares etiqueta/valor adaptables:

- Moda: talla, color, material.
- Tecnología: RAM, almacenamiento, modelo.
- Vehículos: año, kilometraje, transmisión.
- Repuestos: compatibilidad, marca, referencia.

Limitar cantidad y longitud. Rechazar HTML y scripts. No permitir shortcodes arbitrarios ni consultas. Los atributos son descriptivos; no representan inventario.

## 8. Precios y moneda

Guardar valores decimales normalizados, sin símbolo ni separadores de miles. La moneda se configura por instalación. El MVP no calcula impuestos ni convierte divisas automáticamente.

## 9. Versionado

- Mantener versión del plugin y opción `nexora_db_version`.
- Ejecutar migraciones idempotentes al actualizar.
- No borrar ajustes ni metadatos al desactivar.
- No eliminar datos del cliente al desinstalar sin consentimiento explícito y respaldo.
- Probar actualizaciones sobre copia antes de producción.

## 10. Fuera del modelo

No se almacenan pedidos, pagos, carritos, compradores, facturas, stock, movimientos, costos, proveedores ni contabilidad.
