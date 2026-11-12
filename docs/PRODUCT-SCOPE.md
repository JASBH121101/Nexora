# NEXORA — Alcance funcional de la primera versión

## Producto

Sitio web de catálogo para que un negocio presente sus productos y reciba consultas por WhatsApp. El negocio administra el contenido desde un panel privado. El software se instala por separado para cada cliente.

## Usuarios

- **Propietario:** primera cuenta creada durante la instalación. Administra los datos del negocio y el catálogo.
- **Visitante:** navega por el catálogo, busca productos, filtra categorías y contacta al negocio por WhatsApp.

No se incluye registro público de negocios ni gestión de varios usuarios con permisos avanzados en esta primera versión.

## Página pública

- Portada con nombre comercial, logotipo, descripción y contacto.
- Catálogo de productos.
- Búsqueda y filtros por categoría.
- Ficha individual de producto.
- Fotografías, nombre, descripción, categoría, precio opcional y detalle adicional.
- Botón de WhatsApp general y contextual por producto.
- Diseño responsive.

## Panel administrativo

- Inicio y cierre de sesión.
- Resumen visual básico: cantidad de productos, categorías y productos publicados.
- Crear, editar, publicar, ocultar y eliminar productos.
- Crear, editar y ordenar categorías.
- Cargar, reemplazar, ordenar y eliminar fotografías.
- Configurar nombre comercial, logotipo, colores básicos, moneda, número y mensaje de WhatsApp.

## Campos sugeridos de producto

- Nombre (obligatorio).
- Categoría (obligatoria).
- Descripción (obligatoria).
- Precio (opcional).
- Una o varias fotografías.
- Detalle adicional libre (opcional), por ejemplo talla, color, kilometraje o almacenamiento.
- Estado: publicado u oculto.
- Destacado (opcional).

El campo de detalle adicional evita construir un sistema complejo de atributos dinámicos en la primera versión.

## Fuera de alcance

- Carrito, checkout y pasarela de pagos.
- Pedidos, reservas o seguimiento de ventas.
- Inventario, existencias, almacenes y movimientos.
- Compras, proveedores o facturación.
- Contabilidad, finanzas, POS o nómina.
- CRM, campañas o mensajería masiva.
- Activación de módulos ERP.
- Plataforma SaaS centralizada multiempresa.
- Aplicación móvil nativa.

## Modelo de instalación

- Un único código fuente reutilizable.
- Una instalación independiente por negocio.
- Base de datos, fotografías y configuración separadas por instalación.
- El propietario crea su cuenta inicial durante la instalación.
- VPS, dominio y alojamiento contratados por el cliente.
- Sin servicios externos obligatorios para el funcionamiento básico.

## Criterios de aceptación

1. El visitante puede abrir el sitio en celular sin desbordamiento horizontal de la página.
2. Puede buscar y filtrar productos por categoría.
3. Puede abrir el detalle de un producto y contactar por WhatsApp.
4. El propietario puede iniciar sesión y cerrar sesión.
5. Un visitante no autenticado no puede acceder al panel ni a operaciones administrativas.
6. El propietario puede crear, editar, publicar, ocultar y eliminar productos.
7. El propietario puede crear, editar y ordenar categorías.
8. El propietario puede subir, reemplazar y eliminar fotografías.
9. Los cambios quedan guardados en MariaDB/MySQL y persisten al cerrar sesión o cambiar de dispositivo.
10. Las configuraciones de cada instalación permanecen separadas.
