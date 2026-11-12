# NEXORA — Arquitectura inicial

**Estado:** propuesta técnica para la demostración MVP.  
**Principio:** monolito modular, simple de instalar en un VPS y fácil de mantener.

## 1. Objetivo

Construir una plataforma de catálogo digital reutilizable para negocios de distintos sectores. La misma base de código debe poder configurarse con identidad visual, datos de contacto, categorías y productos diferentes, sin modificar el código por cada negocio.

La demostración se limita a mostrar y administrar productos. No es una tienda en línea ni un ERP.

## 2. Stack previsto

- Laravel como aplicación backend y capa de presentación.
- PHP en una versión compatible con la versión estable de Laravel seleccionada al iniciar el proyecto.
- MySQL/MariaDB.
- Blade para vistas del servidor; HTML, CSS y JavaScript para interacción.
- GitHub para control de versiones y CI.
- JB Expert QA como herramienta externa de análisis estático durante el desarrollo.

No se añade Docker, servicios de pago ni dependencias comerciales en esta fase.

## 3. Estilo arquitectónico

Monolito modular dentro de una sola aplicación Laravel. Los límites de dominio se mantienen claros, sin introducir microservicios ni una infraestructura multi-tenant que todavía no ha sido aprobada.

Módulos propuestos:

| Módulo | Responsabilidad |
|---|---|
| Business Profile | Nombre comercial, logo, colores, WhatsApp y contacto |
| Catalog | Productos, categorías, estado y destacados |
| Product Attributes | Campos adicionales simples para adaptar fichas a distintos rubros |
| Media | Carga, validación, redimensionado, compresión y formatos de imagen |
| Admin | Autenticación y operaciones administrativas |
| Public Catalog | Presentación pública, búsqueda y filtros básicos |

## 4. Modelo de datos inicial

Entidades previstas para el MVP:

- `business_settings`: identidad y datos de contacto de la instalación.
- `users`: usuarios autorizados para administrar el catálogo.
- `categories`: categorías y orden de presentación.
- `products`: nombre, descripción, precio opcional, estado y categoría.
- `product_images`: rutas, orden y metadatos de las fotografías.
- `product_attributes`: atributos adicionales configurables, asociados a productos.

La forma definitiva de guardar atributos flexibles se validará antes de crear migraciones. No se incorporará inventario, pedidos, pagos ni contabilidad.

## 5. Separación de responsabilidades

- Los controladores HTTP validan solicitudes y delegan operaciones.
- Los modelos representan datos y relaciones.
- Las reglas de validación se centralizan en Form Requests.
- La lógica de carga y optimización se aísla en servicios de Media.
- Las vistas públicas no muestran funciones administrativas.
- El panel requiere autenticación; las operaciones de escritura requieren autorización del servidor.

No confiar en ocultar botones como mecanismo de seguridad.

## 6. Imágenes

El flujo debe validar tipo MIME real, extensión permitida, tamaño y dimensiones. Las imágenes aceptadas se redimensionarán y comprimirán; WebP será el formato de salida preferido cuando la biblioteca y el entorno lo permitan. Se conservará una estrategia de nombres aleatorios y rutas no ejecutables. Los límites concretos de tamaño se fijarán en configuración.

No almacenar imágenes binarias directamente en MySQL. Guardar archivos en el almacenamiento configurado y metadatos/rutas en la base de datos.

## 7. WhatsApp

Generar enlaces `wa.me` a partir del número configurado por el administrador. El mensaje por producto puede incluir nombre y precio cuando exista. El número y contenido se validan en servidor; no se procesa ninguna compra dentro de NEXORA.

## 8. Despliegue y configuración

La aplicación está pensada para ejecutarse en un VPS con PHP, Composer, servidor web y MySQL/MariaDB. Cloudflare puede utilizarse posteriormente como DNS/proxy/SSL delante del VPS, pero no reemplaza el runtime PHP de Laravel.

El modelo comercial y de instalación (una instalación independiente por negocio frente a una plataforma central multi-tenant) sigue pendiente de confirmación del cliente. No implementar aislamiento multi-tenant hasta que esa decisión sea explícita.

## 9. Calidad y seguridad

JB Expert QA se ejecutará en GitHub Actions como herramienta de desarrollo, no como dependencia de producción. La auditoría estática no ejecuta la aplicación ni sustituye las pruebas funcionales, revisión manual o pruebas de seguridad. Las consultas de advisories y funciones de IA permanecerán desactivadas por defecto.

Las pruebas de aplicación se incorporarán junto con cada módulo. Las credenciales y configuraciones locales nunca se guardan en el repositorio público.

## 10. Estructura prevista

```text
app/
  Http/
    Controllers/
    Requests/
  Models/
  Services/
    Media/
bootstrap/
config/
database/
  migrations/
  seeders/
public/
resources/
  css/
  js/
  views/
    admin/
    catalog/
routes/
  web.php
tests/
  Feature/
  Unit/
docs/
.github/
  workflows/
```

Esta estructura es una guía inicial; se generará el esqueleto real con Composer/Laravel en el entorno de desarrollo para evitar inventar archivos de framework manualmente.

## 11. Decisiones pendientes antes del primer módulo

1. Confirmar con el cliente el modelo de instalación y comercialización.
2. Confirmar versión PHP disponible en el VPS y requisitos del hosting.
3. Definir límites para imágenes y campos adicionales de productos.
4. Confirmar si la demostración tendrá un único administrador o varios usuarios.

Hasta validar esos puntos, mantener el alcance reducido y no añadir infraestructura que genere costos.
