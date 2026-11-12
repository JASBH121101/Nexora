# NEXORA — Demostración HTML/CSS/JS

Esta carpeta contiene una muestra independiente para validar la experiencia pública del catálogo y una vista administrativa básica.

## Abrir la demo

Abre `index.html` en un navegador moderno o sirve la carpeta `demo/` con cualquier servidor estático local. No necesita npm, Node, Composer, Laravel ni base de datos para esta demostración.

## Funciones disponibles

- Cambiar entre tres rubros ficticios: moda, vehículos y tecnología.
- Buscar productos y filtrar por categoría.
- Abrir fichas con descripción, atributos y precio.
- Generar enlaces de consulta a WhatsApp con datos de muestra.
- Abrir `admin.html` para agregar, editar y eliminar productos.
- Cambiar el nombre, número y mensaje del negocio en la vista administrativa.
- Compartir cambios entre ambas pantallas mediante `localStorage` del navegador.

## Limitaciones de seguridad

Esta demo es solo frontend. No existe autenticación real, autorización de servidor, API, persistencia central ni aislamiento entre usuarios. Cualquier visitante puede inspeccionar el código y modificar el estado local del navegador. No usar para administrar negocios reales ni cargar información privada.

Los números de WhatsApp, productos, precios y textos son ficticios. Sustituir los datos de demostración solo en un entorno local de prueba.

## Relación con Laravel

La demo valida estructura, navegación, componentes visuales y flujo de interacción. La implementación real se trasladará a Laravel con validación y autorización en servidor, base de datos MySQL/MariaDB y almacenamiento seguro de imágenes. No se deben convertir datos de `localStorage` en fuente de verdad de producción.
