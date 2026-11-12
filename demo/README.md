# NEXORA — Demostración HTML/CSS/JS

Esta carpeta contiene una muestra independiente para validar la experiencia pública del catálogo y una vista administrativa básica.

## Abrir la demo

Abre `index.html` en un navegador moderno o sirve la carpeta `demo/` con cualquier servidor estático local. No necesita npm, Node, Composer, WordPress ni base de datos para esta demostración.

## Funciones disponibles

- Cambiar entre tres rubros ficticios: moda, vehículos y tecnología.
- Buscar productos y filtrar por categoría.
- Abrir fichas con descripción, atributos y precio.
- Generar enlaces de consulta a WhatsApp con datos de muestra.
- Abrir `admin.html` para agregar, editar y eliminar productos.
- Cambiar nombre, número y mensaje del negocio.
- Compartir cambios entre las pantallas mediante `localStorage` del mismo navegador.

## Limitaciones

Es solo un prototipo frontend. No existe autenticación real, autorización de servidor, API, persistencia central ni aislamiento entre usuarios. Cualquier visitante puede inspeccionar el código y modificar el estado local. No debe utilizarse para administrar un negocio real ni cargar información privada.

Los números de WhatsApp, productos, precios y textos son ficticios.

## Relación con la aplicación WordPress

Esta demo valida estructura, navegación, componentes visuales y flujos. La implementación de producción utilizará WordPress, el plugin propio Nexora Catalog Core, el tema Nexora Catalog Theme y MySQL/MariaDB. Los datos se leerán y escribirán en el servidor mediante APIs de WordPress. El almacenamiento local del navegador no será fuente de verdad de producción.
