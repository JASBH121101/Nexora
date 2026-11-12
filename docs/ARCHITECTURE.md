# NEXORA — Arquitectura del catálogo digital

**Producto:** página web de catálogo con panel administrativo.  
**Modelo:** un código fuente reutilizable y una instalación independiente por negocio.

## 1. Propósito y límites

NEXORA permite a un negocio mostrar sus productos en una página pública y administrarlos desde un panel privado. Debe ser sencillo de instalar, operar y mantener.

No es un ERP, no procesa pedidos ni pagos y no es un SaaS centralizado multiempresa. Cada cliente recibe una instalación separada. El código se reutiliza, pero los datos, fotografías y configuración no se comparten entre clientes.

## 2. Arquitectura técnica

Aplicación monolítica Laravel en el VPS de cada cliente:
- Laravel/PHP para rutas, autenticación, validación y lógica.
- Blade para las páginas públicas y administrativas.
- HTML, CSS y JavaScript para la interfaz.
- MariaDB Community o MySQL Community en el VPS.
- Sistema de archivos local para fotografías.
- GitHub para versionado y CI.

No se introducen microservicios, multi-tenancy, marketplace de módulos, cuentas centrales de propietarios ni servicios de pago en esta fase.

## 3. Flujo de instalación

1. El cliente contrata su dominio y VPS.
2. Recibe el ZIP y la guía de instalación.
3. Configura las credenciales de base de datos y ejecuta las migraciones.
4. Durante el primer acceso controlado crea la cuenta del propietario y registra el nombre comercial.
5. El propietario configura el negocio, WhatsApp, categorías y productos.
6. El sitio público queda disponible en el dominio del negocio.

El asistente de instalación debe permitir crear el primer propietario de forma segura y quedar bloqueado al concluir. No habrá registro público abierto.

## 4. Secciones funcionales

| Sección | Función |
|---|---|
| Resumen | Cantidad de productos, categorías y productos publicados |
| Productos | Crear, editar, publicar, ocultar y eliminar productos |
| Categorías | Crear, editar, ordenar y eliminar categorías |
| Fotografías | Cargar, reemplazar, ordenar y eliminar imágenes |
| Configuración | Nombre, logotipo, descripción, colores básicos, moneda y WhatsApp |
| Cuenta | Inicio y cierre de sesión del propietario |

Estas secciones son partes de un panel sencillo; no son módulos ERP que el cliente active o contrate.

## 5. Modelo de datos inicial

Mantener el esquema reducido:
- `users`: propietario de la instalación.
- `business_settings`: identidad y contacto del negocio.
- `categories`: categorías del catálogo.
- `products`: nombre, descripción, categoría, precio opcional, estado, destacado y detalle adicional opcional.
- `product_images`: ruta, texto alternativo y orden de las fotografías.

No crear tablas de inventario, almacenes, movimientos, pedidos, pagos, compras, proveedores, facturación, contabilidad ni nómina.

No crear todavía un constructor avanzado de atributos dinámicos. Para diferencias entre rubros se podrá usar un campo de detalle adicional libre, por ejemplo talla, color, kilometraje o almacenamiento. Un editor de atributos configurable solo se considerará si el cliente lo pide expresamente.

## 6. Seguridad

- Toda ruta administrativa requiere autenticación.
- Las operaciones de escritura requieren validación y autorización en el servidor.
- La primera cuenta de propietario se crea durante la instalación, no mediante registro público.
- Las contraseñas se guardan usando el hashing seguro del framework.
- Las sesiones y cookies se configuran con atributos seguros en producción.
- La carga de imágenes valida MIME real, extensión, tamaño y dimensiones.
- Los archivos utilizan nombres generados por el sistema.
- Ocultar un botón en el frontend nunca sustituye la autorización del backend.

La demo frontend no ofrece seguridad y no debe utilizarse para administrar datos reales.

## 7. Fotografías

Las imágenes se almacenan como archivos en el almacenamiento local configurado por Laravel; la base de datos guarda sus rutas y metadatos. No se guardan imágenes binarias como BLOB en MariaDB/MySQL.

El flujo validará formato, MIME real, tamaño y dimensiones; redimensionará y comprimirá las imágenes. WebP será preferido si el entorno lo soporta. No se requiere R2, S3 ni otro almacenamiento externo para el funcionamiento básico.

## 8. WhatsApp

El propietario configura un número con código de país y un mensaje general. Cada producto podrá generar un enlace `https://wa.me/` con un texto precargado que incluya el nombre y, si está configurado, el precio del producto.

NEXORA no procesa compras, pagos ni pedidos. La conversación y cierre de venta suceden fuera del sistema.

## 9. Despliegue y entrega

Requisitos previstos: PHP compatible con la versión de Laravel seleccionada, Composer, MariaDB/MySQL, servidor web, HTTPS y extensiones PHP necesarias.

La versión definitiva de PHP, Laravel y extensiones se fijará después de conocer el entorno VPS del cliente. La entrega incluirá ZIP, `.env.example`, migraciones, instrucciones y procedimiento de actualización. Nunca incluirá credenciales ni secretos.

El cliente contrata y mantiene el VPS, dominio y cualquier servicio externo opcional.

## 10. Calidad

JB Expert QA se ejecuta en CI como herramienta de desarrollo; no forma parte del producto instalado por el cliente. La auditoría estática no sustituye pruebas funcionales ni revisión visual real.

Antes de entregar se probarán página pública, responsive, autenticación, CRUD, gestión de categorías, carga y eliminación de fotografías, enlaces de WhatsApp y protección de rutas.

## 11. Demo actual

`demo/` es una muestra independiente en HTML/CSS/JS. Incluye tres rubros ficticios para mostrar la reutilización del diseño, búsqueda, filtros, fichas, WhatsApp y un panel de prueba que guarda cambios en `localStorage`.

La selección de rubro es únicamente un recurso de demostración. En producción cada despliegue corresponde a un único negocio y lee su propia identidad y catálogo desde su base de datos.

La demo no tiene autenticación real, API, base de datos ni almacenamiento seguro de fotografías.
