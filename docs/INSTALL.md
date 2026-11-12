# NEXORA — Guía inicial de instalación

Esta guía se completa con el primer release instalable. Requiere una instalación WordPress existente en el VPS del cliente.

## Componentes

- `nexora-catalog-core-x.y.z.zip`: lógica y administración.
- `nexora-catalog-theme-x.y.z.zip`: presentación pública.

Extraer el paquete de distribución exterior para obtener los dos ZIP. Instalar cada uno por separado.

## Pasos

1. Verificar WordPress operativo y HTTPS.
2. Crear un respaldo de base de datos y archivos.
3. Abrir Plugins → Añadir plugin → Subir plugin.
4. Subir y activar Nexora Catalog Core.
5. Abrir Apariencia → Temas → Añadir nuevo → Subir tema.
6. Subir y activar Nexora Catalog Theme.
7. Completar el perfil comercial desde el menú Nexora.
8. Crear un usuario individual con rol de encargado del catálogo.
9. Guardar enlaces permanentes.
10. Crear productos e imágenes de prueba y verificar el catálogo.

## Importante

La demo en `demo/` no se instala en WordPress y no debe utilizarse como panel real. Los pasos definitivos y las compatibilidades se publicarán con el primer release funcional.
