# NEXORA Site Engine — dominios, DNS y HTTPS

## Alcance de esta entrega

Esta fase añade administración de hasta cinco nombres por instalación y comprobaciones controladas de DNS, propiedad del nombre y TLS. El propietario puede registrar:

- **Dominio propio:** por ejemplo, `www.negocio.com`.
- **Subdominio externo:** por ejemplo, un nombre entregado por un proveedor gratuito de DNS dinámico, siempre bajo control del cliente.

NEXORA no vende ni registra dominios, no ofrece subdominios centrales `cliente.nexora.tld` en esta versión y no administra la cuenta del proveedor DNS del cliente.

## Configuración del servidor

El proveedor del VPS debe indicar la IP pública de la instalación. Configura esa dirección en el entorno del servidor:

```dotenv
NEXORA_SITE_DNS_TARGETS=203.0.113.20
```

El ejemplo anterior es una dirección documental reservada y no debe copiarse como dirección real. Para IPv4 e IPv6, separa los valores mediante comas:

```dotenv
NEXORA_SITE_DNS_TARGETS=IPV4_PUBLICA,IPV6_PUBLICA
```

Después de modificar variables de entorno, limpia la caché de configuración de Laravel mediante el procedimiento de mantenimiento de tu alojamiento. No se almacenan IP del VPS en la base de datos.

Solo se aceptan direcciones globales públicas como destinos esperados. Direcciones loopback, privadas, link-local, reservadas o de documentación se descartan.

## Flujo de conexión

1. El propietario agrega el dominio o subdominio en el panel.
2. NEXORA genera un token aleatorio y muestra el nombre TXT `_nexora-verification.<dominio>` y su valor.
3. En el proveedor DNS, el cliente crea el registro A/AAAA hacia la IP del VPS y el registro TXT de propiedad.
4. Cuando la propagación haya terminado, el propietario ejecuta la comprobación.
5. NEXORA verifica que exista una IP pública configurada para ese VPS y que el TXT contenga el token exacto.
6. Solo entonces prueba TLS en el puerto 443, conectando directamente a una de las IP coincidentes y validando el certificado para el nombre mediante SNI, confianza de CA y nombre del host.
7. El nombre solo puede elegirse como principal cuando DNS, TXT y TLS están correctos.

Las comprobaciones DNS/TLS no se ejecutan automáticamente en segundo plano; se lanzan desde el panel y están limitadas por rate limiting.

## Publicación y HTTPS

Cuando hay un dominio principal verificado:

- Las rutas públicas solo aceptan nombres registrados con verificación vigente en la configuración.
- Un dominio secundario verificado redirige al dominio principal.
- El dominio principal redirige las solicitudes HTTP a HTTPS.
- Un host no registrado o no verificado no recibe el catálogo público.

El panel administrativo mantiene su autenticación y puede seguir usándose desde el host de instalación. Cambiar el dominio principal no modifica el DNS ni la configuración del servidor web.

**La comprobación TLS no emite ni renueva certificados.** El cliente debe configurar HTTPS en su VPS, por ejemplo mediante la herramienta ACME/Let's Encrypt de su proveedor o una configuración administrada por el operador. La renovación y disponibilidad del certificado dependen del alojamiento.

No se modifican registros DNS mediante API, no se ejecutan cambios en el VPS y no se realiza despliegue automático. Tampoco se habilitan servicios de pago.

## Controles de seguridad

- Gestión de dominios reservada al propietario autenticado de la instalación.
- Normalización del nombre: minúsculas, IDN a ASCII cuando PHP intl está disponible y rechazo de protocolos, rutas, puertos, comodines, direcciones IP y nombres locales.
- Máximo de cinco entradas por instalación; UUID y token de propiedad criptográficamente aleatorio.
- Actualizaciones JSONB serializadas dentro de transacción y bloqueo de fila.
- No se permite publicar un dominio principal antes de completar DNS, TXT y TLS.
- El diagnóstico no realiza solicitudes HTTP a destinos arbitrarios.
- La prueba TLS se fija a IP públicas configuradas explícitamente en el entorno del VPS. No conecta a direcciones adicionales devueltas por DNS, ni a redes privadas/reservadas, ni a puertos distintos de 443.
- Validación del certificado para el hostname solicitado y comprobación de sus fechas de validez.
- Mensajes de error genéricos; no se muestran detalles internos de red.

## Variables que deben ser revisadas en el hosting

- Dirección(es) pública(s) real(es) del VPS en `NEXORA_SITE_DNS_TARGETS`.
- Registros DNS A/AAAA y TXT creados por el cliente.
- Servidor web configurado para atender el dominio.
- Puertos 80/443 y emisión/renovación de certificados administrados en el alojamiento.
- `APP_URL` ajustado a la URL canónica HTTPS al completar la configuración del sitio.

## Pruebas automatizadas

La suite cubre normalización de hostnames, rechazo de entradas malformadas, DNS de destino incorrecto, TXT incorrecto, TLS inválido, límite de conexión a IP configuradas, selección del dominio principal, restricciones por propietario y comportamiento de host/HTTPS de las rutas públicas.

Las pruebas usan resolutores DNS y validadores TLS simulados; no consultan DNS público, no abren conexiones externas ni certifican la configuración real de un VPS. Antes de producción todavía se requiere una prueba operativa con un dominio controlado por el cliente, un VPS aislado y un certificado emitido por su proveedor.
