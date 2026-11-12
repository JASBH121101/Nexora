# NEXORA

**Catálogo digital administrable para negocios.**

NEXORA permite que un negocio publique sus productos en una página web y los administre desde un panel privado. El mismo código fuente se reutiliza para distintos clientes, pero cada negocio recibe su propia instalación independiente.

**NEXORA no es un ERP, una tienda en línea ni un SaaS centralizado.**

## Primera versión

### Página web pública
- Diseño adaptable a celulares, tabletas y computadoras.
- Página de presentación del negocio.
- Catálogo de productos con fotografías, nombre, categoría y descripción.
- Precio opcional y detalle adicional.
- Búsqueda y filtros por categoría.
- Ficha individual de producto.
- Botón de contacto por WhatsApp, general y desde cada producto.
- Personalización sencilla de nombre, logotipo y colores.

### Panel administrativo privado
- Inicio de sesión del propietario.
- Resumen sencillo: productos, categorías y productos publicados.
- Crear, editar, publicar, ocultar y eliminar productos.
- Crear, editar y ordenar categorías.
- Subir, reemplazar y eliminar fotografías.
- Configurar identidad del negocio y datos de WhatsApp.

## Fuera de alcance
No incluye carrito, pagos, pedidos, inventario, compras, proveedores, facturación, contabilidad, nómina, POS, CRM, módulos activables ni funciones ERP.

## Modelo de entrega
- Un único código fuente reutilizable.
- Una instalación independiente por negocio, normalmente en el VPS del cliente.
- Base de datos, fotografías y configuración separadas por instalación.
- El propietario crea su cuenta y registra el negocio durante la instalación.
- Entrega prevista: ZIP instalable, documentación y guía de configuración.
- El VPS, dominio y alojamiento son responsabilidad del cliente.

## Tecnología prevista
- Laravel / PHP.
- MariaDB Community o MySQL Community.
- Blade, HTML, CSS y JavaScript.
- Almacenamiento local del servidor para las fotografías.
- Pruebas automatizadas y JB Expert QA durante el desarrollo.

Las funciones básicas no requieren licencias comerciales ni servicios externos obligatorios. El VPS, dominio y servicios que el cliente contrate pueden generar costos propios.

## Estado actual
El repositorio contiene una demo frontend HTML/CSS/JS para validar el catálogo, la vista de producto, WhatsApp y un panel de prueba. La demo usa `localStorage`; no tiene autenticación real, backend, base de datos ni carga de archivos en servidor.

La demostración incluye tres rubros ficticios para enseñar la flexibilidad del producto. En producción cada instalación mostrará un solo negocio y utilizará su propia base de datos.

## Documentación
- [Alcance funcional](docs/PRODUCT-SCOPE.md)
- [Arquitectura técnica](docs/ARCHITECTURE.md)
- [Guía de la demo](demo/README.md)

## Seguridad
Este repositorio es público. No subir archivos `.env`, contraseñas, tokens, claves privadas, copias de bases de datos ni datos personales de clientes. La demo debe utilizar información ficticia.
