# NEXORA MFA — implementación TOTP v1

## Alcance de laboratorio

Esta entrega añade MFA TOTP opcional por cuenta, como una fase aislada sobre la rama de imágenes privadas. No activa una política obligatoria general ni se considera certificación de producción. La especificación aprobada deja pendiente la regla definitiva de obligatoriedad para propietarios y cuentas administrativas de privilegio elevado.

No utiliza SMS, WhatsApp OTP, proveedores externos, API de pago ni dependencias comerciales.

## Enrolamiento

1. El usuario autenticado confirma su contraseña actual.
2. Se genera un secreto aleatorio de 160 bits en Base32.
3. El secreto pendiente se guarda mediante el cast cifrado de Laravel y vence a los 10 minutos.
4. El usuario introduce manualmente la clave en una aplicación compatible con TOTP, configurada en SHA-1, seis dígitos y período de 30 segundos.
5. El usuario confirma un código válido antes de activar MFA.
6. Al activarse, el secreto se conserva cifrado y se generan diez códigos de recuperación aleatorios.

La interfaz muestra una clave manual y una URI `otpauth://` en una página con `Cache-Control: private, no-store`. No se envía el secreto a generadores de QR, sitios ni servicios externos.

## Verificación

- Algoritmo TOTP basado en HMAC-SHA1, con contador de 30 segundos y seis dígitos.
- Ventana de tolerancia de un contador a cada lado para diferencias pequeñas de reloj.
- El servidor guarda el último contador aceptado y rechaza su reutilización.
- El desafío de MFA se solicita después de validar usuario/contraseña y antes de permitir las rutas del panel.
- La sesión registra el ID de cuenta verificada y la hora; la vigencia máxima de esa verificación se limita por `SESSION_LIFETIME`.
- El endpoint de desafío tiene rate limit de cinco intentos por minuto.

## Códigos de recuperación

- Se generan diez códigos de 80 bits al activar MFA.
- Se muestran una sola vez después del enrolamiento.
- La base de datos guarda solamente hashes.
- Cada código se invalida atómicamente después de su uso.
- No se imprimen en logs ni se incluyen en exportaciones.

## Desactivación

Para desactivar MFA se requiere la contraseña actual y un código TOTP nuevo cuyo contador no haya sido utilizado. La operación elimina los hashes de recuperación y limpia los secretos de la cuenta. No se permite reutilizar un código consumido.

## Límites y trabajo posterior

- La política de cuentas que tendrán MFA obligatorio continúa pendiente de aprobación y de integración con roles de privilegio elevado.
- La administración de sesiones activas, revocación individual, auditoría de autenticación y política de retención son entregas separadas.
- La recuperación de cuenta cuando se pierdan el autenticador y todos los códigos requiere un procedimiento administrativo seguro aún por definir.
- El cifrado de los secretos depende de la clave de aplicación; el despliegue real debe proteger y respaldar esa clave fuera del repositorio.
- Las pruebas automatizadas validan la lógica y los contratos de la aplicación; no son auditoría externa ni certificación de producción.
