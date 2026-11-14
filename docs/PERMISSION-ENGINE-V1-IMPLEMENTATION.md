# NEXORA Permission Engine v1 — Registro de implementación

**Rama:** `feature/nexora-permission-engine-v1`  
**Base:** `docs/nexora-approved-upgrades-2026-11`  
**Fecha:** 14 de noviembre de 2026  
**Estado:** implementación inicial preparada para validación; no desplegada.

## Objetivo

Ampliar el sistema actual de empleados/permisos sin duplicarlo. NEXORA Core mantiene el catálogo y la evaluación de permisos; NEXORA Admin ofrece la gestión de roles y excepciones por empleado. Los endpoints Laravel comprueban autorización en el servidor.

## Qué incorpora esta entrega

- Catálogo granular de módulos y acciones: productos, categorías, plantillas/campos, medios, inventario básico (permisos previstos), exportaciones (permisos previstos), empleados, configuración y reportes.
- Roles predefinidos de partida: Administrador, Editor de catálogo y Solo consulta.
- Creación y modificación de roles personalizados.
- Asignación de rol a cada empleado.
- Excepciones individuales con tres estados: heredar, permitir y denegar.
- Permisos por campo de producto para lectura y edición, además de reglas iniciales para cantidad y disponibilidad de inventario.
- Permiso de lectura requerido además del permiso de edición, para evitar actualizaciones ciegas de campos no visibles.
- Mapeo de fotografías al módulo Media: ver, subir, reemplazar y eliminar.
- Compatibilidad de lectura con permisos heredados (`catalog.view`, `catalog.manage`, `categories.manage`, `fields.manage`, `media.manage`, `reports.view`) para no romper cuentas existentes.
- Rutas de productos, categorías y campos con permisos más específicos.
- Ocultación de campos restringidos en las vistas administrativas y comprobación de solicitudes manipuladas en el servidor.
- Conservación de valores no editables/ocultos al actualizar otros campos del producto.
- Limpieza de roles y sus registros de demostración al restablecer una instalación demo.

## Persistencia y evolución del esquema

La migración `2026_11_14_000000_create_permission_engine_tables.php` crea:

- `roles`: nombre, slug, descripción, permisos JSON, permisos por campo JSON y marca de rol de sistema.
- `users.role_id`: relación opcional con el rol.
- `users.permission_overrides`: excepciones individuales JSON.

Se conserva `users.permissions` para compatibilidad con las asignaciones anteriores. Los empleados sin rol y sin permisos explícitos quedan denegados por defecto.

## Ejemplo de configuración

Un rol «Auxiliar de bodega» puede tener acceso a consulta de productos e inventario básico. Para un producto se puede permitir consultar nombre y cantidad, denegar lectura/edición de precios y denegar cambios de descripción. Las operaciones de inventario no se implementan con esta entrega; quedan preparados sus identificadores de autorización para el módulo básico posterior.

## Archivos principales

- `app/Core/Authorization/PermissionCatalog.php`
- `app/Core/Authorization/RoleCatalog.php`
- `app/Models/Role.php`
- `app/Models/User.php`
- `app/Http/Middleware/RequirePermission.php` (middleware existente reutilizado)
- `app/Modules/Admin/Http/Controllers/RoleController.php`
- `app/Modules/Admin/Http/Controllers/EmployeeController.php`
- `app/Modules/Admin/Http/Controllers/ProductController.php`
- `resources/views/admin/roles/`
- `resources/views/admin/employees/index.blade.php`
- `tests/Feature/PermissionEngineTest.php`

## Validación pendiente antes de considerar certificada la entrega

- Ejecutar Pint y PHPUnit en SQLite.
- Ejecutar migraciones y pruebas en PostgreSQL 16.
- Confirmar renderizado de vistas Blade y rutas en Laravel.
- Revisar operaciones de medios en create/update/delete.
- Revisar las excepciones de lectura/edición en productos y campos dinámicos.
- Confirmar que el restablecimiento demo conserva el esquema y elimina roles personalizados.
- Revisar el resultado del análisis JB Expert QA en esta rama.

Esta entrega no implementa aún MFA, gestión de sesiones activas, auditoría de actividad, respaldos, inventario persistente, Export Engine ni actualizaciones híbridas. Tampoco introduce multi-tenancy compartido: se mantiene el modelo documentado de instalación independiente por negocio mientras se decide la topología futura.
