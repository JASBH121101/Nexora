# NEXORA — estado de implementación Phase 2 (2026-11-14)

## Integrado en la cadena de ramas de trabajo

### Permission Engine v1
- Roles personalizables con permisos por módulo y acción.
- Excepciones por empleado: heredar, permitir y denegar.
- Restricciones por campo para lectura y edición.
- Denegación por defecto para empleados inactivos o sin asignación válida.
- Compatibilidad con permisos heredados.
- Verificación de autorización en el servidor y preservación de campos protegidos.

### Library Engine 0.2.0
- Catálogos compartidos de país, moneda, código de llamada, idioma y unidades.
- Normalización telefónica con código explícito.
- Monedas y unidades con identificadores estables, etiquetas localizadas y precisión decimal.
- Precios con cuatro decimales y migración aditiva compatible.

### Inventory Basic
- Cantidad disponible persistida en producto.
- Validación que rechaza cantidades negativas.
- Permisos diferenciados para consultar y modificar cantidad.
- Rol auxiliar con acceso mínimo.
- No incluye libro de movimientos, órdenes de compra, almacenes, transferencias ni valoración contable.

### Export Engine
- CSV UTF-8 con BOM y neutralización de fórmulas.
- JSON estructurado.
- XML escapado.
- ZIP condicional según disponibilidad de PHP ZipArchive, con medios originales validados.
- Filtros, permisos por campo e historial de ejecuciones.
- PDF y XLSX no están implementados.

## Validación automatizada

- PR #7 — Library, Inventory y Export: Laravel Pint, PHPUnit sobre SQLite/PostgreSQL 16 y check de JB Expert QA aprobados antes de su merge en `feature/nexora-permission-engine-v1`.
- PR #6 — Permission Engine + cadena Phase 2: la validación actual de Laravel Pint y PHPUnit está aprobada en el head de trabajo.
- No se ha fusionado a `main` ni se ha desplegado a producción.
- Revisión visual/manual del Setup y del panel, y una instalación desechable del paquete, siguen pendientes antes de producción.

## Pendiente y no incluido en esta tanda

- PDF/XLSX y selección de bibliotecas compatibles con el VPS.
- Libro de movimientos, almacenes, transferencias, costeo y valoración de inventario.
- MFA/TOTP y políticas de enrolamiento.
- Gestión de sesiones activas y revocación individual.
- Auditoría de actividad con política de retención/inmutabilidad.
- Backups, cifrado, destino, retención y ensayo de restauración.
- Firma y distribución del actualizador, claves del publicador y política de rollback.
- NEXORA Site Engine: dominios, DNS, subdominios y HTTPS/TLS, reservado para la siguiente fase.

## Límites operativos

Todo permanece en ramas de trabajo. No se introducen secretos, servicios externos de pago, ni dependencias para PDF/XLSX. La rama `main` y producción permanecen sin cambios.
