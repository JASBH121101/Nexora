# NEXORA

**Sistema de catálogo digital configurable para distintos giros de negocio.**

NEXORA es una base de software reutilizable para que un negocio publique y administre sus productos desde un panel protegido. Cada cliente tendrá una instalación independiente; la misma base de código se configura con la identidad, productos y datos de contacto de cada negocio.

## Estado del proyecto

- **Prototipo visual:** disponible en `demo/` con HTML, CSS y JavaScript.
- **Arquitectura objetivo:** WordPress + plugin propio Nexora Catalog Core + tema propio Nexora Catalog Theme + MySQL/MariaDB.
- **Backend de producción:** todavía no implementado. El prototipo no tiene autenticación real ni persistencia compartida.
- **Entrega prevista:** paquete comprimido con plugin y tema instalables en WordPress en el VPS del cliente.
- **Despliegue:** VPS del cliente; sin Docker ni servicios de pago añadidos en esta etapa.

## Ver la demostración

- [Abrir catálogo demo](./demo/)
- [Abrir panel demo](./demo/admin.html)

El panel demo guarda los cambios únicamente en `localStorage` del navegador. No es seguro para operar un negocio real y los datos no se comparten entre dispositivos o usuarios.

## Alcance del MVP

- Catálogo público responsive.
- Productos, categorías, precios opcionales y atributos sencillos.
- Una o varias fotografías por producto.
- Búsqueda y filtros básicos.
- Perfil del negocio: nombre, logotipo, colores, contacto y WhatsApp.
- Panel de administración con permisos restringidos.
- Consulta directa por WhatsApp desde el catálogo y cada producto.

## Fuera de alcance

Carrito, checkout, pasarelas de pago, procesamiento de pedidos, inventario avanzado, facturación, contabilidad, nómina y funciones ERP.

## Arquitectura técnica

- Plataforma: WordPress.
- Backend y vistas: PHP y APIs nativas de WordPress.
- Base de datos: MySQL o MariaDB.
- Lógica: plugin propio `nexora-catalog-core`.
- Interfaz pública: tema propio `nexora-catalog-theme`.
- Frontend: HTML, CSS, JavaScript y plantillas PHP.
- CI: GitHub Actions.
- QA: JB Expert QA, pruebas funcionales y revisión manual.

La arquitectura, modelo de datos, seguridad, despliegue y hoja de ruta están documentados en `docs/`.

## Instalación por cliente

Cada negocio tendrá su propia instalación WordPress, base de datos, archivos, dominio y credenciales. No se mezclan clientes en una base de datos compartida. SaaS multi-tenant centralizado queda fuera del MVP.

El paquete exterior contendrá los ZIP del plugin y del tema, documentación, notas de versión y hashes de integridad. El WordPress del cliente debe estar instalado previamente en su VPS.

## Seguridad del repositorio

No subir archivos `.env`, contraseñas, tokens, claves privadas, respaldos de bases de datos ni fotografías o datos personales de clientes. La demo debe utilizar exclusivamente datos ficticios.
