# NEXORA — Arquitectura de producto

**Versión:** 1.0 · **Estado:** arquitectura base del MVP · **Fecha:** 2026-11-12  
**Plataforma elegida:** WordPress + plugin y tema propios  
**Despliegue:** una instalación independiente por negocio en su VPS

## 1. Propósito y límites

NEXORA es un producto reutilizable de catálogo digital. El mismo código se configura para sectores distintos —moda, tecnología, vehículos, repuestos y otros— sin crear una versión diferente del software por cliente.

El MVP publica y administra productos. No es tienda transaccional, ERP ni plataforma multi-tenant centralizada.

Principios:
1. Reutilizar el mismo código y configurar cada negocio.
2. Aislar clientes mediante instalaciones independientes.
3. Separar lógica de producto y presentación visual.
4. Priorizar APIs nativas de WordPress.
5. Entregar plugin y tema instalables en ZIP, sin Docker.
6. No añadir servicios o licencias de pago sin aprobación.
7. No considerar el prototipo HTML como sistema productivo.

## 2. Stack

| Capa | Tecnología | Responsabilidad |
|---|---|---|
| CMS/runtime | WordPress | Administración, sesiones, usuarios y medios |
| Backend | PHP + APIs WordPress | Validación, reglas y persistencia |
| Núcleo propio | Nexora Catalog Core | Productos, categorías, perfil, permisos y medios |
| Tema propio | Nexora Catalog Theme | Catálogo público responsive |
| Datos | MySQL o MariaDB | Persistencia de WordPress |
| Interfaz | PHP templates, HTML, CSS, JS | Renderizado y comportamiento |
| Archivos | Sistema de archivos VPS | Medios y archivos de aplicación |
| CI/QA | GitHub Actions + JB Expert QA | Validaciones del repositorio |

No fijar una versión concreta de PHP o WordPress hasta verificar la compatibilidad del VPS y las versiones soportadas al iniciar el build.

### Justificación

WordPress se selecciona para el MVP porque proporciona administración, usuarios, biblioteca multimedia, taxonomías y APIs de persistencia. Nexora añadirá su propia lógica y diseño; no será una configuración genérica de plugins de terceros.

La demo actual sigue siendo HTML/CSS/JS estático. Se utiliza para validar la experiencia antes de migrarla al tema WordPress.

## 3. Topología de instalación

```text
Cliente A / dominio A                Cliente B / dominio B
          |                                     |
        HTTPS                                 HTTPS
          |                                     |
        VPS A                                 VPS B
          |                                     |
      WordPress                             WordPress
          |                                     |
 Nexora Core + Theme                  Nexora Core + Theme
          |                                     |
        MySQL A                               MySQL B
          |                                     |
     uploads A                              uploads B
```

Cada instalación tiene dominio, archivos, base de datos, credenciales y respaldos propios. No existe una base de datos compartida entre negocios.

Fuera del MVP: multi-tenancy en una sola instalación, panel SaaS central, licencias centralizadas, facturación recurrente y sincronización de catálogos.

## 4. Componentes

### 4.1 Nexora Catalog Core — plugin

Es dueño de la lógica y los datos del catálogo:
- Registrar el tipo de contenido `nexora_product` y taxonomía `nexora_category`.
- Definir precio, precio anterior opcional, SKU opcional, atributos sencillos y galería.
- Gestionar perfil del negocio: nombre, logo, colores, contacto, WhatsApp y moneda.
- Crear el rol de encargado y capacidades mínimas.
- Proporcionar pantallas administrativas específicas.
- Validar, sanitizar y guardar mediante APIs WordPress.
- Gestionar imágenes por la biblioteca multimedia.
- Generar enlaces WhatsApp.
- Versionar las opciones para permitir actualizaciones.

El plugin no debe contener estilos exclusivos de una plantilla.

### 4.2 Nexora Catalog Theme — tema

Se encarga de:
- Encabezado y navegación.
- Identidad visual y presentación del negocio.
- Catálogo, cuadrícula y filtros.
- Búsqueda básica.
- Ficha de producto y galería.
- Precio, atributos y consultas WhatsApp.
- Información de contacto y pie de página.
- Estados vacíos y responsive.

No crea productos, administra usuarios, valida permisos ni guarda datos comerciales.

### 4.3 WordPress Core

Se reutilizan usuarios, sesiones, roles/capacidades, CPT, taxonomías, medios, opciones, metadatos, nonces y enrutamiento. No se modifica el núcleo.

## 5. Módulos funcionales

| Módulo | Función | Componente |
|---|---|---|
| Business Profile | Identidad, logo, colores, contacto, moneda | Plugin |
| Catalog | Alta, edición, borrador, publicación y eliminación | Plugin |
| Categories | Crear, editar y ordenar | Plugin |
| Product Attributes | Pares etiqueta/valor por rubro | Plugin |
| Media Gallery | Carga, orden y eliminación de imágenes | Plugin + WordPress |
| Admin | Panel, roles y permisos | Plugin + WordPress |
| Public Catalog | Listado, detalle, búsqueda y filtros | Tema |
| Contact | Enlaces WhatsApp por negocio/producto | Plugin + Tema |

## 6. Persistencia y modelo de datos

El MVP reutiliza las tablas nativas de WordPress; no crea tablas SQL propias salvo evidencia de necesidad:

- Productos: `wp_posts`, tipo `nexora_product`.
- Categorías: tablas de términos y relaciones, taxonomía `nexora_category`.
- Campos propios: `wp_postmeta`, claves `_nexora_*`.
- Perfil del negocio: `wp_options`, opción `nexora_business_profile`.
- Usuarios/roles: `wp_users` y `wp_usermeta`.
- Imágenes: adjuntos y archivos en `wp-content/uploads/`.

Los nombres reales de tablas dependen del prefijo configurado por WordPress. Ver [DATA-MODEL.md](./DATA-MODEL.md).

## 7. Rutas conceptuales

| Ruta | Uso |
|---|---|
| `/` | Inicio y productos destacados |
| `/catalogo/` | Listado |
| `/producto/{slug}/` | Ficha individual |
| `/categoria/{slug}/` | Productos de una categoría |
| `/wp-admin/` | Administración protegida |
| menú Nexora en wp-admin | Perfil, configuración y accesos |

Los slugs definitivos se registrarán en el plugin y se comprobarán al guardar enlaces permanentes. Las páginas públicas no exponen controles de edición.

## 8. Flujos de datos

### Publicar producto

1. Usuario inicia sesión.
2. Backend verifica capacidad de gestión.
3. Formulario incluye nonce válido.
4. Servidor valida y sanitiza los campos.
5. Imágenes se procesan mediante APIs de medios WordPress.
6. Producto y metadatos se guardan mediante APIs nativas.
7. El tema consulta solo productos publicados.

### Consultar por WhatsApp

1. Visitante abre la ficha.
2. El tema muestra datos públicos del producto.
3. El enlace se genera con el número normalizado del negocio.
4. El mensaje puede incluir nombre y precio.
5. La conversación continúa en WhatsApp.

No se crea pedido, pago o compra dentro de Nexora.

## 9. Imágenes

Usar la biblioteca multimedia; validar MIME real, extensión, tamaño y dimensiones; permitir solo formatos raster aprobados; generar tamaños adecuados para tarjeta y detalle; preferir WebP si el servidor lo soporta; mantener referencias por ID de adjunto; no guardar binarios en MySQL; no permitir SVG ni archivos activos en cargas del catálogo.

La operación de carga debe verificar autorización, nonce y permisos del usuario.

## 10. Diseño, accesibilidad y rendimiento

Diseño mobile-first. La cuadrícula debe adaptarse a móviles, tabletas y escritorio. Contemplar áreas táctiles cómodas, contraste, foco visible, navegación por teclado, etiquetas accesibles, estados de carga/error y carga diferida de imágenes. No depender de hover para revelar controles esenciales.

Limitar resultados por página, optimizar tamaños de imagen y evitar dependencias JS innecesarias.

## 11. Seguridad

Todas las operaciones de escritura deben comprobar sesión, capacidad, nonce, validar entrada y escapar salida según el contexto. No confiar en ocultar botones. Proteger carga de archivos, credenciales, actualizaciones y respaldos. Ver [SECURITY.md](./SECURITY.md).

## 12. Despliegue y entrega

WordPress se ejecuta en el VPS. GitHub Pages solo aloja la demo estática; no ejecuta PHP.

La entrega contiene ZIP del plugin, ZIP del tema, guía de instalación, notas de versión y hashes SHA-256. El paquete exterior se extrae para obtener los ZIP instalables por separado. Ver [DEPLOYMENT.md](./DEPLOYMENT.md).

## 13. Calidad

Incluir pruebas unitarias y de integración para CPT, taxonomía, metadatos, roles y medios; pruebas funcionales de CRUD; pruebas de autorización; validación de archivos; responsive; PHP lint; estándares de WordPress; CI y revisión manual. JB Expert QA es complementario, no reemplaza pruebas funcionales ni revisión de seguridad.

La CI existente cubre la demo JavaScript. Se ampliará al plugin/tema cuando exista código PHP real.

## 14. Estructura objetivo del repositorio

```text
Nexora/
├── .github/workflows/
├── demo/
│   ├── index.html
│   ├── admin.html
│   ├── assets/
│   └── tests/
├── plugin/nexora-catalog-core/
│   ├── nexora-catalog-core.php
│   ├── includes/{content,admin,settings,media,security,support}/
│   ├── assets/
│   ├── languages/
│   └── tests/
├── theme/nexora-catalog-theme/
│   ├── style.css
│   ├── functions.php
│   ├── templates/
│   ├── template-parts/
│   ├── assets/
│   └── languages/
├── docs/
└── README.md
```

Esta es la estructura objetivo, no una afirmación de que el plugin/tema ya estén implementados.

## 15. Decisiones invariables para el MVP

- WordPress como runtime, salvo decisión explícita posterior.
- Plugin de lógica y tema visual separados.
- Una instalación por cliente.
- Sin WooCommerce para la primera etapa.
- Sin binarios en base de datos.
- Sin modificaciones a WordPress Core.
- Sin PHP en GitHub Pages.
- Sin servicios de pago o infraestructura adicional sin aprobación.
