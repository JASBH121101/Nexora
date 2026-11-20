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

### Site Engine + dominios, DNS y TLS
- Catálogo público responsive con búsqueda, filtros, detalle de producto y productos publicados únicamente.
- Metadatos SEO, canonical/Open Graph, `robots.txt` y `sitemap.xml`.
- Hasta cinco dominios propios o subdominios externos administrados por el propietario.
- Verificación de propiedad mediante TXT y comprobación A/AAAA contra las IP públicas configuradas en `NEXORA_SITE_DNS_TARGETS`.
- Diagnóstico TLS en puerto 443 con SNI, certificado de confianza y hostname validado; la conexión queda fijada a las IP públicas autorizadas.
- Selección del dominio principal solo después de DNS, TXT y TLS correctos; hosts no verificados rechazados, alias redirigidos al principal y HTTP redirigido a HTTPS.
- El sistema no modifica proveedores DNS, no configura el VPS y no emite ni renueva certificados automáticamente.

## Validación automatizada

- PR #7 — Library, Inventory y Export: validado con Pint, 87 pruebas y 1,458 aserciones en SQLite y PostgreSQL 16; check de JB Expert QA aprobado. Fusionado en `feature/nexora-permission-engine-v1` mediante commit `0f80364ef3a2d997e4d6d58de3ec2c793194edc9`.
- PR #6 — Permission Engine: Pint aprobado; suite PHP en SQLite y PostgreSQL 16 aprobada según la ejecución documentada. Fusionado en `docs/nexora-approved-upgrades-2026-11` mediante commit `a1b20fe962b22f172cbb39f48585a2db0015e59c`.
- PR #9 — Public Site Engine: Pint y pruebas feature aprobados en SQLite y PostgreSQL 16. Fusionado mediante `8140262bf5dd546b5b45386615ac02f1ba452c55`.
- PR #11 — dominios, verificación DNS/TXT y TLS: Pint y pruebas feature aprobados en SQLite y PostgreSQL 16. Fusionado mediante `af586b3de74191323327b670e89c6896199137e4`.
- La suite de CI usa resolutores DNS/TLS simulados; no acredita un dominio real ni la configuración de un VPS.
- Ambas integraciones permanecen en ramas de trabajo dedicadas. `main` y producción no recibieron estos cambios.
- Revisión visual/manual del Setup y del panel, y una instalación desechable del paquete, siguen pendientes antes de producción.

## Backlog funcional fuera del alcance de esta certificación

- PDF/XLSX y selección de bibliotecas compatibles con el VPS.
- Libro de movimientos, almacenes, transferencias, costeo y valoración de inventario.
- MFA/TOTP está implementado en el código y cubierto por pruebas automatizadas de enrolamiento, desafío, anti-replay, códigos de recuperación y desactivación. La decisión de obligatoriedad y el despliegue operativo se mantienen configurables/pedientes de política.
- Gestión de sesiones activas, revocación individual y política de expiración/revocación reforzada.
- Auditoría de actividad con política de retención e integridad.
- Backups, cifrado, destino, retención y ensayo de restauración.
- Firma y distribución del actualizador, claves del publicador y política de rollback.
- Gestión de sesiones activas y revocación individual.
- Auditoría de actividad con política de retención/inmutabilidad.
- Backups, cifrado, destino, retención y ensayo de restauración.
- Firma y distribución del actualizador, claves del publicador y política de rollback.


## Requisitos operativos de publicación

Para activar un dominio real, el dueño de la instalación y el proveedor de alojamiento deben suministrar/configurar la IP pública real del VPS, los registros A/AAAA y TXT, el virtual host y un certificado vigente. Después debe actualizarse `APP_URL` a la URL canónica HTTPS. Estas acciones requieren dominio y VPS reales; no se simulan como despliegue ni se ejecutan sobre infraestructura del usuario desde el laboratorio.

## Límites operativos

Todo permanece en ramas de trabajo. No se introducen secretos, servicios externos de pago, ni dependencias para PDF/XLSX. La rama `main` y producción permanecen sin cambios.
