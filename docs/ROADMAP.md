# NEXORA — Hoja de ruta

## Fase 0 — Arquitectura

- [x] Separar demo visual de sistema productivo.
- [x] Elegir WordPress para el MVP.
- [x] Separar plugin y tema.
- [x] Definir una instalación por cliente.
- [x] Definir MySQL/MariaDB y almacenamiento del VPS.
- [x] Excluir tienda transaccional y ERP.
- [x] Documentar datos, seguridad y despliegue.
- [ ] Confirmar capacidades reales del VPS.

**Salida:** arquitectura cerrada y hosting compatible identificado.

## Fase 1 — Esqueleto técnico

Plugin: bootstrap, versión, CPT, taxonomía, activación idempotente, rol/capacidades y traducciones.  
Tema: encabezado, funciones, soporte de logo/imágenes, plantillas públicas y estilos responsive.

**Salida:** plugin y tema instalan/activan en WordPress limpio.

## Fase 2 — Administración

- CRUD de productos y categorías.
- Precio, precio anterior, moneda y atributos.
- Imagen destacada y galería.
- Perfil comercial y WhatsApp.
- Roles, nonces, validación y carga segura.

**Salida:** datos persistentes en MySQL, no localStorage.

## Fase 3 — Catálogo público

- Inicio y catálogo responsive.
- Búsqueda y filtros.
- Ficha y galería de producto.
- Precio/atributos/contacto.
- WhatsApp.
- Personalización de identidad visual.

**Salida:** tema renderiza los datos reales del plugin.

## Fase 4 — QA y seguridad

- PHP lint y estándares de WordPress.
- Pruebas unitarias/de integración.
- Pruebas de permisos y solicitudes no autorizadas.
- Pruebas de archivos e imágenes.
- Regresión visual y responsive.
- Auditoría JB Expert QA.
- CI para plugin y tema.
- Prueba de actualización desde release anterior.

**Salida:** sin defectos críticos o bloqueantes conocidos.

## Fase 5 — Paquete y staging

- ZIP del plugin y ZIP del tema.
- Paquete exterior, changelog y hashes SHA-256.
- Instalación desde cero en WordPress limpio.
- Pruebas con datos representativos ficticios.
- Guía de instalación y aprobación.

**Salida:** release candidato verificado.

## Fase 6 — Producción

Backup inicial, instalación en VPS, HTTPS, perfil y usuario del cliente, contenido real autorizado, aceptación y registro de versión/mantenimiento.

**Salida:** catálogo real operativo.

## Fuera del MVP

Carrito, checkout, pagos, pedidos, inventario, facturación, contabilidad, nómina, multi-tenancy central, app móvil e integraciones ERP/proveedores. Cada función requerirá alcance y aceptación separados.
