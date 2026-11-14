# NEXORA

**Catálogo digital configurable para negocios.**

NEXORA es un producto web reutilizable para que distintos tipos de negocio publiquen y administren sus productos desde una página pública y un panel privado. Cada cliente recibe una instalación independiente con su propia base de datos, archivos y configuración.

NEXORA **no es un ERP ni un SaaS centralizado multiempresa**. No comparte datos entre negocios y no necesita una cuenta central.

## Visión del producto

La configuración inicial identifica el tipo de negocio y carga una plantilla editable. El propietario puede personalizar grupos y campos de sus fichas de producto sin crear tablas SQL ni modificar código.

Plantillas previstas: perfumería, ropa y calzado, tecnología, gimnasio y fitness, repuestos/vehículos, alimentos/abarrotes y una plantilla libre.

## Funciones previstas para el producto

### Asistente de configuración
- Nombre comercial, propietario, correo y tipo de negocio.
- Verificación del correo y creación segura de la cuenta en producción.
- Hasta cinco números de WhatsApp, selección de número principal y control de cuáles se muestran públicamente.
- Logotipo, portada, descripción, color principal, ubicación y horario opcionales.
- Moneda principal y activación de monedas adicionales.

### Catálogo configurable
- Plantillas sectoriales editables.
- Constructor de grupos y campos personalizados.
- Tipos de campo: texto, número, lista, selección múltiple, sí/no, fecha y texto largo.
- Orden, ancho visual, obligatoriedad, visibilidad pública y filtros configurables.
- Biblioteca de unidades por magnitud: masa, volumen, longitud, cantidad, capacidad digital y área.
- Posibilidad de agregar unidades personalizadas.
- Fotografías opcionales por instalación.
- Campos fundamentales protegidos: nombre, descripción, categoría, precio y estado.

### Página pública y panel
- Página de presentación y catálogo responsive.
- Búsqueda y filtros.
- Ficha de producto organizada por grupos.
- Contacto directo con enlaces de WhatsApp generales y por producto.
- Panel privado con resumen, productos, categorías, configuración y editor de fichas.

## Arquitectura y entrega

- Código fuente común, instalación independiente por negocio.
- Laravel / PHP.
- PostgreSQL 16 como motor principal de base de datos.
- Blade, HTML, CSS y JavaScript.
- Fotografías almacenadas en el disco configurado; la base de datos conserva rutas y metadatos.
- PostgreSQL es el motor oficial. Los atributos configurables se guardan en columnas JSONB.
- En EthioDeploy, DATABASE_URL se inyecta automáticamente; NEXORA la utiliza mediante el fallback DB_URL de Laravel.
- Migraciones reproducibles, validación de servidor y pruebas automatizadas.
- Entrega objetivo: ZIP instalable, `.env.example`, migraciones y documentación.
- VPS, dominio y alojamiento contratados por cada cliente.

No se requiere la API oficial de WhatsApp para enlaces de contacto `wa.me`. Automatización de conversaciones, OTP por SMS/WhatsApp, conversión cambiaria automática y servicios externos son alcances separados que deben evaluarse por costo.

## Estado del repositorio

La rama de trabajo revisada contiene una base de aplicación Laravel, además de la demo frontend:
- `app/`, `routes/`, `database/` y `resources/`: estructura de backend, rutas, modelos, controladores, migraciones y vistas administrativas/públicas.
- La base incluye configuración inicial, catálogo configurable, cuenta del propietario y un sistema inicial de empleados/permisos.
- `demo/`: prototipo frontend HTML/CSS/JS para validar flujos y presentación. Sus cambios se guardan en `localStorage` y no representan persistencia ni seguridad de producción.
- La presencia de código backend no equivale a certificación de producción: deben ejecutarse y aprobarse pruebas funcionales, de seguridad y de despliegue antes de utilizar datos reales.
- Las mejoras de exportación, inventario básico, MFA, auditoría, respaldos y permisos completos por campo están especificadas, pero no deben considerarse implementadas hasta verificarlas en código y pruebas.

No introducir contraseñas reales, tokens ni datos personales en la demo.

## Documentación

- [Alcance funcional](docs/PRODUCT-SCOPE.md)
- [Arquitectura técnica](docs/ARCHITECTURE.md)
- [Motor de catálogo configurable](docs/DYNAMIC-CATALOG-ENGINE.md)
- [Especificación aprobada de mejoras](docs/APPROVED-UPGRADES-2026-11.md)
- [Guía de la demo](demo/README.md)

## Seguridad

Este repositorio es público. No subir archivos `.env`, contraseñas, tokens, claves privadas, copias de bases de datos ni datos personales. Toda autenticación y autorización real debe ocurrir en el backend; ocultar controles en el navegador no es una medida de seguridad.
