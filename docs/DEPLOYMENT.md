# NEXORA — Despliegue y entrega

## 1. Topología

```text
Dominio -> DNS/HTTPS -> VPS -> Apache o Nginx -> PHP/WordPress
                                                |-> Nexora Catalog Core
                                                |-> Nexora Catalog Theme
                                                |-> MySQL/MariaDB
                                                |-> wp-content/uploads
```

GitHub Pages solo hospeda la demo estática. No ejecuta PHP ni reemplaza el VPS.

## 2. Requisitos a confirmar con el hosting

- Versión PHP compatible con WordPress objetivo.
- MySQL o MariaDB compatible.
- Apache o Nginx.
- Acceso a WordPress y panel/VPS.
- Espacio para aplicación, base y fotografías.
- Límites de subida PHP/WordPress.
- HTTPS/TLS.
- Capacidad y política de respaldos.

No fijar versiones por intuición; validar compatibilidad real antes de release.

## 3. Entornos

| Entorno | Uso |
|---|---|
| Desarrollo | Código y datos sintéticos |
| Staging | Prueba de ZIP y flujo del cliente |
| Producción | Catálogo real del cliente |

No probar migraciones directamente en producción ni copiar credenciales de producción a desarrollo.

## 4. Paquete de entrega

```text
NEXORA-release-x.y.z/
├── nexora-catalog-core-x.y.z.zip
├── nexora-catalog-theme-x.y.z.zip
├── INSTALL.md
├── CHANGELOG.md
└── SHA256SUMS.txt
```

El paquete exterior se extrae primero. WordPress instala los ZIP del plugin y del tema por separado.

## 5. Instalación

1. Confirmar WordPress operativo y HTTPS activo.
2. Respaldar base de datos y archivos.
3. Extraer paquete de distribución.
4. En Plugins → Añadir plugin → Subir plugin, instalar y activar Core.
5. En Apariencia → Temas → Añadir nuevo → Subir tema, instalar y activar Theme.
6. Completar perfil, moneda, logo, colores, WhatsApp y contacto.
7. Crear usuario individual con rol Nexora.
8. Guardar enlaces permanentes.
9. Cargar productos e imágenes de prueba.
10. Probar en móvil, tableta y escritorio.
11. Verificar backup y recuperación.
12. Incorporar contenido real solo tras validación del cliente.

## 6. Actualizaciones

- Versionar plugin y tema.
- Documentar cambios incompatibles.
- Respaldar antes de actualizar.
- Probar ZIP en staging.
- Verificar productos, categorías, imágenes y ajustes.
- Mantener ZIP anterior para rollback.

No actualizar WordPress/PHP ni extensiones en producción sin revisar compatibilidad y respaldo.

## 7. Mantenimiento

El acuerdo debe aclarar responsable de actualizaciones, backup/restauración, dominio, SSL, disponibilidad, errores y soporte de usuarios. VPS, dominio, licencias y mantenimiento recurrente se cotizan por separado; no se asumen incluidos.

## 8. Rollback

1. Habilitar mantenimiento si es necesario.
2. Restaurar archivos del plugin/tema de versión anterior.
3. Restaurar base de datos solo cuando una migración incompatible lo requiera.
4. Verificar catálogo, panel, imágenes y WhatsApp.
5. Documentar causa y corrección.

## 9. Checklist de entrega

- [ ] Plugin/tema instalan en WordPress limpio.
- [ ] Activación sin errores PHP.
- [ ] Perfil comercial funcional.
- [ ] Rol limitado probado.
- [ ] CRUD de productos y categorías.
- [ ] Carga y galería de imágenes.
- [ ] WhatsApp general y por producto.
- [ ] Responsive probado.
- [ ] HTTPS y backup comprobados.
- [ ] Documentación y SHA-256 entregados.
- [ ] Aprobación del cliente registrada.
