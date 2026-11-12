# NEXORA — Demostración del catálogo digital

Esta carpeta contiene una muestra HTML/CSS/JS para validar el catálogo público y el panel administrativo antes de implementar el backend Laravel.

## Qué demuestra

- Página pública adaptable a móvil y escritorio.
- Tres perfiles ficticios para mostrar cómo se configura el diseño según el rubro.
- Búsqueda y filtros por categoría.
- Fichas con fotografía, nombre, descripción, precio y detalle.
- Enlaces directos de consulta a WhatsApp.
- Panel de prueba para crear, editar y eliminar productos.
- Configuración de nombre comercial, número y mensaje de contacto.

## Cómo funciona

Abre `index.html` en un navegador moderno o sirve la carpeta `demo/` con un servidor estático. No requiere npm, Node, Composer, Laravel ni una base de datos.

El panel guarda cambios en `localStorage` del navegador. No hay sincronización entre dispositivos ni usuarios.

## Limitaciones

Esta demo no tiene inicio de sesión real, autorización en servidor, API, base de datos ni almacenamiento seguro de fotografías. Cualquier persona con acceso al navegador puede inspeccionar o modificar los datos locales. No usar para un negocio real ni guardar información privada.

Los productos, imágenes, números y mensajes son ilustrativos. La selección de varios rubros existe solamente para mostrar la flexibilidad visual; el producto instalado en producción tendrá un solo negocio por instalación.

## Producto de producción

El backend real se construirá con Laravel y MariaDB/MySQL. Incluirá autenticación del propietario, operaciones CRUD protegidas, validación, carga segura de fotografías y persistencia del catálogo en la base de datos. Esta demo es una referencia visual y de interacción, no el sistema de producción.
