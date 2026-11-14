# NEXORA — Especificación aprobada de mejoras
**Fecha de consolidación:** 14 de noviembre de 2026  
**Base revisada:** `feature/nexora-configurable-catalog`  
**Estado:** requisitos aprobados para planificación e implementación incremental. Este documento no certifica que las funciones descritas ya estén implementadas.

## 1. Propósito y precedencia

Este documento consolida las decisiones confirmadas en la conversación del 14 de noviembre de 2026 sobre NEXORA Export Engine, autorización, seguridad, localización, Language QA y actualizaciones híbridas.

Se utiliza como anexo aprobado de las especificaciones existentes. Cuando un punto contradiga documentación anterior, se deberá revisar y reconciliar esa documentación antes de modificar datos, migraciones o comportamiento de producción. No se debe interpretar este anexo como autorización para introducir servicios de pago, cambiar la infraestructura, alterar `main` ni desplegar una actualización en producción.

Los tres motores propios de NEXORA conservan límites lógicos separados:

- **NEXORA Core:** configuración central, políticas comunes, compatibilidad de versiones y coordinación de actualizaciones.
- **NEXORA Admin:** panel privado, administración del negocio, usuarios, roles, permisos y operación de exportaciones.
- **NEXORA Site Engine:** renderizado del catálogo público y exposición exclusiva de información autorizada y publicada.

Los componentes **Permission Engine** y **Export Engine** se especifican como capacidades lógicas de NEXORA. No se decide aquí que deban convertirse en microservicios o repositorios independientes. La arquitectura actual es Laravel y deberá conservarse modular, sin introducir complejidad distribuida innecesaria.

## 2. Alcance funcional que se mantiene

La primera entrega continúa centrada en catálogos digitales configurables. No se convierte en un ERP completo.

Se incluye inventario **básico e informativo**: cantidades disponibles y estado de disponibilidad por producto o variante cuando corresponda. En este alcance no se incluyen movimientos de almacén, entradas y salidas, compras, proveedores, órdenes, ventas, contabilidad ni nómina.

Las funciones de empleados, permisos y exportación no deben habilitar por accidente operaciones ERP que no hayan sido aprobadas.

## 3. NEXORA Export Engine

### 3.1 Formatos previstos

| Formato | Propósito |
|---|---|
| PDF | Catálogos y reportes listos para compartir o imprimir |
| XLSX | Listados e inventario editable |
| CSV | Intercambio tabular |
| JSON | Datos estructurados e integraciones |
| XML | Intercambio con sistemas compatibles |
| ZIP | Paquete de datos y archivos multimedia |

La implementación seleccionará bibliotecas compatibles con la versión de PHP/Laravel y con el entorno de alojamiento. No se añadirá una dependencia comercial obligatoria.

### 3.2 Fotografías: decisión aprobada — BOTH-ALL

Las fotografías se incluirán en todos los formatos que técnicamente permitan incorporarlas:

- **PDF:** imágenes integradas en páginas y fichas cuando se solicite.
- **XLSX:** imágenes insertadas cuando la biblioteca y el tamaño del archivo lo permitan.
- **ZIP:** originales y, cuando proceda, versiones optimizadas junto con los datos exportados.
- **CSV, JSON y XML:** referencias o rutas de imágenes. Estos formatos no incorporan imágenes binarias de forma nativa.

La interfaz debe explicar esta diferencia para no prometer fotografías incrustadas en CSV, JSON o XML. Debe permitir exportar datos y fotografías juntos mediante ZIP.

### 3.3 Datos y personalización

Los conjuntos exportables podrán incluir, de acuerdo con los permisos:

- Información básica del negocio.
- Productos y estados de publicación.
- Categorías y subcategorías.
- Variantes y campos configurables.
- Precios y monedas habilitadas.
- Cantidades de inventario básico y disponibilidad.
- Fotografías y sus referencias.
- Catálogo completo o subconjuntos filtrados.

La exportación avanzada permitirá seleccionar categorías, productos, variantes, intervalos y campos/columnas, incluir o excluir precios, cantidades y fotografías, y ordenar resultados. Debe existir una exportación rápida con valores predeterminados.

No se exportarán campos privados, credenciales, secretos, información de sesión ni datos fuera del alcance del usuario solicitante.

### 3.4 Historial: decisión aprobada — YES

Se guardará un registro por exportación con, como mínimo:

- Fecha y hora.
- Usuario responsable.
- Formato y nombre del archivo.
- Tipo de datos exportados.
- Filtros y opciones relevantes.
- Estado de ejecución (completada o fallida).
- Tamaño final cuando esté disponible.

El historial no debe exponer públicamente archivos ni datos exportados. Los enlaces de descarga, si se conservan, deben estar protegidos por autorización y caducidad. La retención de archivos exportados y la duración del historial quedan pendientes de definir según privacidad, almacenamiento y recursos disponibles.

## 4. Permission Engine — autorización en tres niveles

**Aprobación confirmada:** los tres niveles se implementarán como parte de la especificación de NEXORA Core y serán administrables desde NEXORA Admin.

### 4.1 Nivel de módulo

El propietario podrá conceder o denegar acceso a módulos o áreas funcionales, por ejemplo:

- Catálogo y productos.
- Categorías y subcategorías.
- Campos y plantillas.
- Fotografías y archivos.
- Inventario básico.
- Exportaciones e historial.
- Usuarios y empleados.
- Configuración del negocio.
- Seguridad y administración.

La lista definitiva crecerá de manera versionada conforme se aprueben módulos nuevos.

### 4.2 Nivel de acción

Dentro de cada módulo, el sistema diferenciará acciones. El catálogo inicial de acciones contempla:

- Consultar (`view`).
- Crear (`create`).
- Editar (`edit`).
- Eliminar (`delete`).
- Publicar u ocultar (`publish`).
- Aprobar (`approve`).
- Exportar (`export`).
- Administrar (`manage`).

No se debe inferir automáticamente que permiso de edición implica permiso de eliminación, aprobación o exportación.

### 4.3 Nivel de campo

Los permisos podrán limitar campos concretos, tanto para lectura como para modificación. Ejemplo: un auxiliar puede consultar existencias y registrar una cantidad autorizada, pero no editar precios, costos, códigos o configuración del producto.

El servidor debe validar el campo y la operación, no confiar en campos ocultos o controles deshabilitados en el navegador. Los campos no autorizados no se deben filtrar al cliente cuando no exista permiso de lectura.

### 4.4 Roles personalizables y excepciones individuales

El propietario podrá crear roles como Auxiliar de bodega, Responsable de inventario, Vendedor, Supervisor o Contador, asignar permisos a cada rol y asignar personas a esos roles.

También se permitirán excepciones por usuario sin modificar el rol de todos los demás. El propietario conserva control total sobre su negocio y no puede delegar la transferencia de titularidad ni la eliminación de los controles de seguridad fundamentales.

La política de autorización utilizará **denegación por defecto**. Una concesión en un nivel no debe saltarse una denegación más específica. Los cambios de rol, permisos y estado de cuenta quedan registrados en auditoría.

### 4.5 Ejemplo aprobado como caso de aceptación

Un auxiliar de bodega puede tener acceso de consulta al inventario, pero no permiso para editar productos o eliminar registros. Otro permiso podría permitir modificar únicamente el campo de cantidad disponible, sin permitir cambiar precios o descripciones.

### 4.6 Estado actual del código revisado

La rama revisada ya contiene `PermissionCatalog`, `RequirePermission`, `User::hasPermission()` y una pantalla/controlador de empleados con permisos asignados directamente al usuario. Es una base reutilizable, pero no equivale aún al sistema aprobado:

- Solo existe una distinción básica entre propietario y empleado.
- Los permisos actuales se guardan como una lista por usuario.
- No se encontró un catálogo completo de roles personalizados.
- No se encontró la matriz de permisos por campo.
- El catálogo de permisos existente usa capacidades amplias, por lo que deberá granularizarse con migraciones y pruebas compatibles.

La siguiente implementación debe evolucionar estos componentes, no duplicarlos.

## 5. Seguridad aprobada

Se confirman los siguientes componentes de seguridad.

### 5.1 Autenticación multifactor (MFA)

MFA forma parte del alcance aprobado. La opción inicial recomendada es TOTP mediante aplicación autenticadora, con códigos de recuperación protegidos. No se dependerá de SMS o WhatsApp OTP ni de un proveedor de pago para el funcionamiento básico.

La política inicial propuesta es MFA opcional para propietarios y requerido para cuentas administrativas con privilegios elevados. La política exacta de obligatoriedad y recuperación se validará antes de activar MFA en producción.

Los secretos MFA deben almacenarse cifrados y nunca aparecer en logs o exportaciones.

### 5.2 Sesiones

Se aprueba el manejo reforzado de sesiones:

- Expiración por inactividad.
- Cierre de sesión de la sesión actual.
- Revocación de sesiones activas desde la cuenta autorizada.
- Invalidación de sesiones al cambiar credenciales o ante incidentes relevantes.
- Regeneración del identificador de sesión tras autenticación y cambios de privilegios.

La configuración actual de Laravel define una duración de sesión, pero no demuestra por sí sola que exista una consola de sesiones activas ni revocación individual. Esa capacidad deberá construirse y probarse.

### 5.3 Auditoría

Se registrarán operaciones sensibles, al menos:

- Autenticación exitosa y fallida.
- Cierre y revocación de sesiones.
- Alta, suspensión y cambios de empleados.
- Cambios de roles y permisos.
- Creación, edición, publicación y eliminación de productos.
- Cambios en cantidades básicas de inventario.
- Exportaciones.
- Cambios de configuración y acciones de seguridad.
- Ejecución y resultado de actualizaciones y restauraciones.

Los registros no deben contener contraseñas, tokens, secretos MFA ni contenido privado innecesario. El acceso a auditoría debe estar limitado a roles autorizados y su protección contra alteraciones deberá definirse durante el diseño técnico.

### 5.4 Copias de seguridad

Se aprueba contemplar respaldos de base de datos y de fotografías/archivos. La solución debe documentar:

- Qué datos y archivos se incluyen.
- Dónde se guardan.
- Cómo se protegen.
- Cómo se restauran.
- Cómo se valida su integridad.
- Cómo se prueba periódicamente una restauración.

Frecuencia, retención, destino externo y cifrado de respaldos quedan por fijar según el VPS y los límites/costos reales. No se contratará almacenamiento externo ni servicios de pago sin aprobación.

### 5.5 Aislamiento de negocios / tenants

Se aprueba como requisito que los datos de cada negocio estén estrictamente aislados. Las verificaciones deben cubrir consultas, objetos por identificador, archivos, exportaciones, logs, trabajos asíncronos y operaciones administrativas.

**Decisión arquitectónica aún pendiente:** la documentación vigente de la rama base describe una instalación independiente por negocio, con base de datos y archivos separados; eso ofrece aislamiento físico entre instalaciones, pero no equivale a una plataforma SaaS multi-tenant compartida. La aprobación del requisito de aislamiento no autoriza por sí sola convertir NEXORA a una base de datos compartida ni introducir un SaaS centralizado.

Hasta que se elija explícitamente la topología, se conserva el modelo de entrega documentado de instalación independiente por negocio. Antes de implementar soporte multi-tenant dentro de una misma instancia se debe acordar si habrá múltiples negocios en una instalación, cómo se identifica el tenant y si se utilizarán bases de datos o esquemas separados, o aislamiento por filas. No se debe añadir `tenant_id` a todas las tablas sin ese diseño.

## 6. Actualizaciones híbridas de los tres motores NEXORA

Se aprueba el modelo híbrido para NEXORA Core, NEXORA Admin y NEXORA Site Engine:

- Cada motor podrá actualizarse de forma independiente cuando la compatibilidad lo permita.
- Correcciones pequeñas, compatibles y de bajo riesgo podrán aplicarse automáticamente bajo una política previamente aprobada.
- Cambios estructurales, migraciones, cambios que afecten datos o personalizaciones, y actualizaciones incompatibles requerirán aprobación del propietario.
- NEXORA Core coordinará versiones, dependencias y compatibilidad sin obligar a actualizar motores que no lo necesiten.
- Antes de operaciones de riesgo se evaluará respaldo, integridad y posibilidad de rollback.
- Los paquetes deberán tener manifiesto de versión y controles de integridad/autenticidad antes de ejecutarse.

No se implementará un actualizador que ejecute código arbitrario descargado sin validación. La política de firma, distribución, canales de actualización y operación sin conexión queda pendiente de diseño técnico.

## 7. Idiomas e internacionalización

Para la primera versión se confirman:

- Español latinoamericano: `es-419`.
- Inglés: `en`.
- Idioma de respaldo si falta una clave: `es-419`.

No se separará inicialmente español de México y español de España. El diseño debe permitir agregar idiomas y variantes regionales después, sin duplicar lógica.

Los textos de interfaz estarán en archivos de traducción por locale, con claves estables, pluralización y variables validadas. Se separarán las traducciones de la interfaz de los textos comerciales que cada propietario escriba para sus productos. Los datos de catálogo podrán tener traducciones en varios idiomas en una fase posterior, con selección de idioma por visitante.

También deberán respetarse formatos regionales de fechas, números y monedas. La dirección de escritura RTL puede reservarse para una fase futura.

En la rama revisada existen recursos genéricos `lang/es` y `lang/en`. La estructura `es-419` y su validación aún deben incorporarse; no se debe declarar cobertura regional completa hasta probarla.

## 8. JB Expert QA — Language QA Engine

La asistencia lingüística pertenecerá a **JB Expert QA**, dentro de un módulo llamado **Language QA Engine**. La biblioteca lingüística será **Language Library** y contendrá paquetes de idioma, diccionarios, glosarios, terminología aprobada, variantes regionales y traducciones validadas.

Capacidades previstas:

- Sugerencias de traducción asistida.
- Validación gramatical, contextual y terminológica.
- Memoria de traducción para reutilizar expresiones aprobadas.
- Validación de claves, variables, placeholders, pluralización y estructura.
- Detección de traducciones faltantes, obsoletas o inconsistentes.
- Estados de traducción: pendiente, parcial, revisión requerida, aprobada y desactualizada.
- Revisión humana para expresiones ambiguas, contenido comercial sensible y textos legales.

### Restricción económica aprobada

La solución debe ser gratuita-first y no puede exigir créditos ni API de pago. No se debe activar un proveedor de pago como fallback automático ni enviar contenido a proveedores externos sin configuración y consentimiento explícitos.

La traducción local/de código abierto puede evaluarse, pero debe distinguirse de una revisión generativa contextual: un motor local de traducción no garantiza razonamiento lingüístico avanzado. Si una capacidad avanzada gratuita no está disponible, el sistema debe señalar el límite y usar glosario, reglas y revisión humana; no debe fingir que una IA ilimitada está disponible sin costo.

Language QA Engine se utiliza durante el desarrollo y mantenimiento de NEXORA. No se incluye como dependencia instalada en el producto entregado a cada cliente. NEXORA consume paquetes lingüísticos exportados y aprobados.

## 9. Mapa de responsabilidades

| Componente | Responsabilidad |
|---|---|
| NEXORA Core | Autorización central, compatibilidad, sesiones/políticas y coordinación de actualizaciones |
| NEXORA Admin | Usuarios, roles, permisos, inventario básico, interfaz de exportación e historial |
| NEXORA Site Engine | Catálogo público y control de exposición de contenido |
| NEXORA Export Engine | Generación de PDF/XLSX/CSV/JSON/XML/ZIP y registro de exportaciones |
| JB Expert QA / Language QA Engine | Calidad de código, auditoría y calidad lingüística durante el desarrollo |
| JB Security Guard QA | Evaluación de seguridad durante el desarrollo |

JB Expert QA y JB Security Guard QA son herramientas del entorno de desarrollo del proyecto; no se empaquetan como dependencias obligatorias de la instalación comercial de NEXORA. JB Malware Engine permanece fuera de NEXORA.

## 10. Auditoría del estado del repositorio base

Revisión estática de la rama `feature/nexora-configurable-catalog` el 14 de noviembre de 2026. No se ejecutaron pruebas ni se desplegó la aplicación durante esta revisión.

| Área | Evidencia encontrada | Estado frente a lo aprobado |
|---|---|---|
| Laravel y catálogo configurable | Estructura de aplicación, modelos, controladores, migraciones y vistas | Base existente; no equivale a certificación de producción |
| Empleados y permisos | Controlador de empleados, middleware, catálogo de permisos y permisos por usuario | Parcial; faltan roles configurables y permisos por campo |
| Export Engine | No se identificó módulo de exportación en el árbol revisado | Pendiente |
| Inventario básico | El modelo Product revisado no contiene cantidad/estado de existencias | Pendiente |
| Historial de exportaciones | No se identificó implementación | Pendiente |
| MFA | No se identificó implementación | Pendiente |
| Gestión de sesiones activas | Hay configuración estándar de sesión; no se identificó consola de revocación | Parcial |
| Auditoría de actividad | No se identificó módulo o tabla de auditoría en el árbol revisado | Pendiente |
| Respaldos y restauración | No se identificó módulo de respaldo | Pendiente |
| Aislamiento multi-tenant compartido | La arquitectura documenta una instalación independiente por negocio | Requisito aprobado; topología compartida pendiente de decisión |
| Actualizador híbrido | No se identificó coordinador de actualizaciones de los tres motores | Pendiente |
| Locales | Existen archivos genéricos de español e inglés | Parcial; debe migrarse/probarse `es-419` |
| Language QA Engine | No forma parte del producto NEXORA y debe residir en JB Expert QA | Diseño aprobado; implementación de JB pendiente |

## 11. Secuencia recomendada de implementación

La implementación debe hacerse en entregas pequeñas y revisables:

1. **Cerrar decisiones de arquitectura pendientes:** topología de tenants, política de retención, política MFA, destino/frecuencia de respaldos y límites de exportación.
2. **Permission Engine:** ampliar el sistema actual de empleados a roles personalizables y permisos por módulo, acción y campo; añadir pruebas de denegación por defecto y acceso no autorizado.
3. **Seguridad de cuentas:** MFA, sesiones activas y revocación, reforzamiento de autenticación y auditoría.
4. **Inventario básico:** campos de cantidad y disponibilidad, con permisos y pruebas; sin movimientos ni funciones ERP.
5. **Export Engine:** formatos, fotografías según BOTH-ALL, filtros, historial y control de acceso.
6. **Localización:** establecer `es-419` y `en`, fallback y validación de claves.
7. **JB Expert QA Language QA Engine:** biblioteca, validadores, memoria y flujo de revisión, manteniendo el límite gratuito.
8. **Actualizaciones híbridas y recuperación:** manifiestos, validación, compatibilidad, respaldo y rollback.
9. **Auditoría integral:** pruebas de aislamiento, autorización, exportaciones, restauración y regresión.

Cada entrega deberá conservar la funcionalidad ya existente, agregar migraciones compatibles y pruebas automatizadas. No se deben eliminar datos de productos ni personalizaciones al actualizar plantillas o módulos.

## 12. Reglas de coste y operación

- No habilitar servicios, APIs, almacenamiento o planes pagos sin autorización explícita.
- No instalar herramientas en el equipo del propietario como requisito de esta fase.
- No introducir proveedores de IA de pago ni consumo automático de créditos.
- Evitar ejecuciones innecesarias de CI y despliegues que consuman cuotas.
- Mantener los cambios en ramas de trabajo; no modificar `main` ni producción sin validación y aprobación.
- No afirmar que una función está terminada hasta que exista código, pruebas y verificación acordes con su riesgo.

## 13. Decisiones todavía pendientes

La siguiente fase deberá definir, sin cambiar unilateralmente las decisiones aprobadas:

- Topología definitiva: una instalación por negocio (modelo actual) o múltiples tenants dentro de una misma instalación.
- Frecuencia, retención y destino de respaldos.
- Retención de archivos exportados y del historial.
- Límites de tamaño, número de registros y exportaciones en segundo plano.
- Política exacta de MFA, recuperación y cuentas administrativas.
- Protección contra alteraciones y política de retención de auditoría.
- Firma de paquetes, canales y ventanas de actualización.
- Política de privacidad, eliminación y conservación de datos.
- Controles de HTTPS, cifrado en reposo y protección de archivos en el despliegue final.

Las medidas básicas contra SQL injection, XSS, CSRF, abuso de autenticación y cargas maliciosas siguen siendo requisitos de ingeniería de seguridad, pero su configuración concreta debe documentarse y verificarse en el diseño técnico de cada módulo.
