# NEXORA — estado de implementación Phase 2 (registro 2026-11-14; actualización 2026-11-20)

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

## Estado de MFA/TOTP

MFA/TOTP está implementado en el código y cubierto por pruebas automatizadas de enrolamiento, desafío, anti-replay, códigos de recuperación y desactivación. La decisión de obligatoriedad, la política definitiva de recuperación y el despliegue operativo deben definirse antes de producción.

## Backlog funcional fuera del alcance de esta certificación

- PDF/XLSX y selección de bibliotecas compatibles con el VPS.
- Libro de movimientos, almacenes, transferencias, costeo y valoración de inventario.
- Auditoría de actividad con controles de integridad y política de retención.
- Backups, cifrado, destino, retención y ensayo de restauración.
- Firma y distribución del actualizador, claves del publicador y política de rollback.


## Requisitos operativos de publicación

Para activar un dominio real, el dueño de la instalación y el proveedor de alojamiento deben suministrar/configurar la IP pública real del VPS, los registros A/AAAA y TXT, el virtual host y un certificado vigente. Después debe actualizarse `APP_URL` a la URL canónica HTTPS. Estas acciones requieren dominio y VPS reales; no se simulan como despliegue ni se ejecutan sobre infraestructura del usuario desde el laboratorio.

## Límites operativos

Todo permanece en ramas de trabajo. No se introducen secretos, servicios externos de pago, ni dependencias para PDF/XLSX. La rama `main` y producción permanecen sin cambios.

---

## Actualización de integración y estado — 2026-11-20

Esta sección actualiza el estado de integración posterior al registro histórico del 14 de noviembre. Los apartados anteriores describen correctamente la situación que existía en esa fecha; no deben interpretarse como el estado actual de `main`.

### Integración en `main`

- PR #22 — catálogo configurable y backend Laravel: integrado en `main` mediante `3ff1f367e31164ef78235b8765ae796ebf8ddf05`. Incluye aplicación Laravel 13/PHP 8.3, Setup Engine, catálogo configurable, Permission Engine, MFA/TOTP, manejo privado de imágenes, exportaciones y herramientas de diagnóstico de dominios.
- PR #23 — control de acceso de propietario inactivo: integrado mediante `946044be39d8678fdb4de889e4773905f5450ae7`. El middleware administrativo valida el estado activo también para el propietario en cada petición.
- PR #24 — salud del esquema del instalador: integrado mediante `52ab7ba9c362631b9c4f365caec7b237c87aaf99`. La comprobación de esquema exige las tablas de roles, historial de exportaciones y MFA, además de las tablas base.
- PR #25 — reconciliación de documentación: integrado en `main` mediante `0787e14fee834bca5bbac8c6b7968f6d71f8683e`.
- PR #26 — sesiones activas y revocación individual: integrado en `main` mediante `add58abe8b0000f6833abbf3b7fd113444d22e34`. Incluye registro con hash SHA-256, listado de sesiones, revocación individual, control de inactividad, rotación de identificadores y revocación de sesiones ante cambios de seguridad relevantes.

### Evidencia automatizada

- PR #22: los workflows de Laravel, JB Expert QA y GitHub Pages finalizaron correctamente en el commit integrado.
- PR #23: JB Expert QA y CI de Laravel pasaron. La suite reportó 123 pruebas y 3,000 aserciones en SQLite y PostgreSQL 16.
- PR #24: JB Expert QA y CI de Laravel pasaron. La suite reportó 127 pruebas y 3,004 aserciones en SQLite y PostgreSQL 16.
- PR #26: Pint, JB Expert QA y CI de Laravel pasaron; la suite reportó 134 pruebas y 3,052 aserciones en cada matriz SQLite y PostgreSQL 16. Tras la fusión, JB Expert QA, Laravel CI y GitHub Pages también pasaron sobre `add58abe8b0000f6833abbf3b7fd113444d22e34`.
- Estas pruebas validan el código y los contratos automatizados; no sustituyen una instalación aislada, una revisión visual/manual ni una auditoría externa.

### Estado pendiente antes de declarar producción certificada

La gestión de sesiones activas y revocación individual ya no forma parte de este pendiente: quedó integrada mediante PR #26. La matriz anterior de cambios de noviembre se conserva como registro histórico; los hashes y pruebas posteriores descritos arriba son la fuente del estado actual.

- Ejecutar una instalación limpia y desechable del paquete en un VPS compatible, incluyendo configuración de entorno, permisos, migraciones y creación del propietario.
- Realizar revisión visual/manual de Setup, login, MFA, panel y catálogo público en móvil y escritorio.
- Probar DNS, TXT, TLS, dominio canónico y redirecciones con un dominio y servidor reales. Las pruebas automatizadas usan resolutores simulados.
- Definir si MFA será obligatorio para propietarios o cuentas administrativas de privilegio elevado y documentar la recuperación segura de cuenta.

- Implementar auditoría de actividad con integridad y política de retención.
- Definir y ensayar backups y restauración de base de datos y archivos.
- Seleccionar e implementar PDF y XLSX; actualmente están disponibles CSV, JSON, XML y ZIP condicional.
- Definir distribución firmada, claves del publicador y política de rollback del actualizador.

### Límite de despliegue

La ejecución correcta de GitHub Pages acredita solamente el flujo de publicación de la superficie estática. No acredita que el backend Laravel esté instalado en un VPS, conectado a una base de datos de producción ni configurado con SMTP, almacenamiento y dominio reales. La puesta en producción de una instalación Laravel continúa requiriendo infraestructura y validación operativa independientes.
