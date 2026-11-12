# NEXORA — Seguridad, privacidad y continuidad

**Estado:** controles obligatorios para la implementación y despliegue del MVP.

## 1. Activos y amenazas

Activos: cuentas WordPress, productos/precios/contacto, archivos multimedia, credenciales VPS/base de datos y disponibilidad del catálogo.

Amenazas: acceso no autorizado, escalada de privilegios, CSRF, XSS, carga de archivos maliciosos, fuerza bruta, componentes vulnerables y pérdida de datos.

## 2. Autenticación y autorización

- Utilizar autenticación nativa de WordPress.
- Crear rol `nexora_catalog_manager` con capacidades mínimas.
- Cada operación de escritura comprueba capacidad en servidor.
- Cada formulario o acción protegida comprueba nonce.
- No confiar en ocultar botones o menús.
- Usar cuentas individuales, no compartidas.
- Retirar accesos cuando la persona deje de estar autorizada.
- Proteger cuentas privilegiadas con contraseñas robustas y MFA cuando el entorno lo permita.

Capacidades conceptuales: `edit_nexora_products`, `edit_others_nexora_products`, `publish_nexora_products`, `delete_nexora_products`, `manage_nexora_categories`, `manage_nexora_profile`.

## 3. Validación y salida

- Validar todos los datos en servidor.
- Sanitizar texto, números, URL y colores según su tipo.
- Validar precios decimales y límites.
- Escapar salida según contexto: HTML, atributo, URL o JavaScript.
- Rechazar HTML/scripts en atributos sencillos.
- Evitar SQL manual; si fuese imprescindible, consultas preparadas de WordPress.
- No mostrar errores internos o información privada al visitante.

## 4. Solicitudes protegidas

Toda creación, edición, eliminación o configuración exige sesión, capacidad, nonce, validación y sanitización. El catálogo público será de solo lectura. No se exponen endpoints de escritura anónimos.

## 5. Carga de archivos

- Usar APIs de medios WordPress.
- Verificar MIME real, extensión, tamaño y dimensiones.
- Permitir solo formatos raster aprobados.
- Rechazar SVG, HTML, PHP y archivos ejecutables.
- Utilizar nombres y rutas gestionados por WordPress.
- Definir límite de peso y dimensiones conforme a VPS.
- Asegurar que uploads no ejecute scripts.
- Restringir permisos de escritura al directorio requerido.

## 6. WordPress y VPS

- Mantener WordPress, PHP, plugin y tema en versiones soportadas.
- Eliminar temas/plugins no usados.
- Añadir dependencias solo con justificación.
- Exigir HTTPS.
- Restringir el panel con controles del servidor/WAF si es viable.
- Proteger configuración, logs y respaldos.
- Usar credenciales de base de datos únicas y privilegios limitados.
- Deshabilitar edición de archivos PHP desde el panel en producción.
- Mantener permisos mínimos de archivos/directorios.
- Proteger SSH mediante llaves y acceso limitado cuando se administre el VPS.

Cloudflare puede utilizarse como DNS, proxy o WAF opcional; no sustituye parches ni seguridad del servidor origen.

## 7. WhatsApp y privacidad

Normalizar número a dígitos con código de país; construir enlaces `https://wa.me/`; codificar el mensaje; no transmitir datos personales del visitante. No incorporar rastreadores sin decisión y aviso de privacidad del negocio.

## 8. Secretos y repositorio

Nunca subir contraseñas, tokens, claves API, credenciales SSH/FTP, `wp-config.php`, bases de datos reales ni fotografías/datos personales del cliente. La demo utiliza datos ficticios.

## 9. Backup y recuperación

Respaldar base de datos y `wp-content/uploads/`, guardar al menos una copia fuera del VPS, mantener generaciones, probar restauración en entorno no productivo y crear backup antes de actualizar. La frecuencia y retención se acuerdan con cliente y hosting.

## 10. Incidentes

No registrar contraseñas ni tokens. Ante compromiso: restringir acceso, preservar evidencia, rotar credenciales, revisar usuarios/extensiones/archivos, restaurar desde copia confiable, actualizar componentes vulnerables y comunicar al cliente si sus datos o catálogo fueron afectados.

## 11. Checklist de seguridad

- [ ] HTTPS activo.
- [ ] Cuenta técnica protegida.
- [ ] Rol Nexora con permisos mínimos probado.
- [ ] Escrituras no autorizadas bloqueadas en servidor.
- [ ] Nonces comprobados.
- [ ] Archivos validados.
- [ ] Entrada sanitizada y salida escapada.
- [ ] Extensiones y actualizaciones revisadas.
- [ ] Backup/restauración probados.
- [ ] Sin secretos ni datos reales en Git.
