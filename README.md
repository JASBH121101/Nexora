# NEXORA

**Sistema de catálogo digital configurable para distintos giros de negocio.**

NEXORA es una base de software reutilizable para publicar catálogos de productos y administrarlos desde un panel sencillo. La primera entrega será una demostración funcional y acotada; no pretende ser un ERP ni una tienda en línea.

## Alcance inicial de la demostración

- Catálogo público adaptable a móviles, tabletas y escritorio.
- Panel administrativo protegido.
- Gestión básica de productos y categorías.
- Fotografías de productos y galería básica.
- Datos de contacto y enlace directo a WhatsApp.
- Personalización visual básica del negocio.
- Optimización de imágenes como parte del flujo de carga.

## Fuera de alcance en esta etapa

Carrito de compras, pasarelas de pago, procesamiento de pedidos, inventario avanzado, facturación, contabilidad, nómina y funciones ERP.

## Tecnología prevista

- Backend: Laravel / PHP.
- Base de datos: MySQL o MariaDB.
- Interfaz: Blade, HTML, CSS y JavaScript.
- Control de calidad: JB Expert QA durante desarrollo y CI.

Las versiones concretas del framework y runtime se fijarán durante el bootstrap técnico, después de validar compatibilidad con el VPS objetivo.

## Estado actual

- Demostración frontend en `demo/` construida con HTML, CSS y JavaScript.
- Tres rubros ficticios de muestra: moda, vehículos y tecnología.
- Catálogo con búsqueda, filtros, fichas de producto y enlaces de consulta por WhatsApp.
- Panel de prueba con operaciones CRUD en el almacenamiento local del navegador.
- Pruebas automáticas y análisis estático mediante JB Expert QA en GitHub Actions.

La demostración no es todavía la aplicación Laravel de producción. No tiene autenticación real, API, base de datos ni persistencia compartida. La arquitectura backend y su integración se desarrollarán después de validar la muestra.

## Seguridad del repositorio

Este repositorio es público. No subir archivos `.env`, contraseñas, tokens, claves privadas, respaldos de bases de datos ni fotografías o datos personales de clientes. El código de demostración debe usar información ficticia.
