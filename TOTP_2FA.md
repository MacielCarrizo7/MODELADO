# Segundo factor TOTP

## Qué es y cómo se usa

TOTP es una contraseña temporal de un solo uso basada en tiempo. La aplicación
autenticadora y el servidor comparten un secreto; cada 30 segundos calculan el
mismo código de seis dígitos. No se envían códigos por SMS ni se usa Firebase.

En Control Stock el flujo es:

1. MariaDB valida DNI, nombre, apellido y contraseña.
2. PHP crea únicamente una sesión pendiente, válida durante 10 minutos.
3. Si el usuario aún no tiene TOTP, ve un QR local y confirma un primer código.
4. En accesos posteriores introduce el código de su aplicación autenticadora.
5. Solo entonces se crea la sesión final con `mfa_verified = true` y se aplican
   los roles `admin`, `vendedor` o `cliente` almacenados en MariaDB.

Se puede usar Google Authenticator, Microsoft Authenticator, Authy o cualquier
aplicación compatible con TOTP estándar (SHA-1, seis dígitos y período de 30 s).

## Dependencias

- `robthree/twofactorauth` 3.0.3: generación y validación TOTP.
- `bacon/bacon-qr-code` 3.1.1: QR SVG generado localmente, sin enviar el secreto
  a un proveedor web.

Las versiones resueltas están fijadas en `composer.lock`. Instalación normal:

```powershell
composer install --no-dev --optimize-autoloader
```

Durante el desarrollo en XAMPP se utilizó Composer con `--prefer-source` porque
esa instalación de PHP no tenía `zip` ni un ejecutable `unzip` disponible:

```powershell
C:\xampp\php\php.exe composer.phar --working-dir=stock_app install --no-interaction --prefer-source --no-scripts --no-progress
```

## Migración de MariaDB

Ejecutar una sola vez `migracion_totp_usuarios.sql` sobre la base existente
`control_stock`. La migración solo usa `ALTER TABLE` y agrega:

- `totp_secret_encrypted`: envoltorio cifrado del secreto; nunca texto plano.
- `totp_enabled`: indica que el usuario confirmó un código válido.
- `totp_confirmed_at`: fecha de confirmación.
- `totp_last_timeslice`: intervalo de 30 s usado por última vez para impedir
  reutilización.

Los usuarios existentes quedan con `totp_enabled = 0` y realizan el enrolamiento
después de validar sus credenciales habituales.

## Clave de cifrado obligatoria

El secreto TOTP se cifra con AES-256-GCM. La clave no está en MariaDB ni en este
repositorio: debe existir como variable de entorno `CONTROL_STOCK_TOTP_KEY` y
contener exactamente 32 bytes codificados en Base64.

Generar una clave única una sola vez:

```powershell
C:\xampp\php\php.exe -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Configurar el valor de manera segura en el entorno del proceso Apache y
reiniciar Apache. Con un VirtualHost de XAMPP puede usarse, por ejemplo:

```apache
SetEnv CONTROL_STOCK_TOTP_KEY "VALOR_BASE64_PRIVADO"
```

No guardar esta clave en Git, archivos públicos, registros ni la base. Debe
incluirse en una copia de seguridad segura: si se pierde o cambia, los secretos
existentes no podrán descifrarse y un administrador tendrá que restablecer 2FA.

## Enrolamiento del usuario

Tras el primer factor correcto, un usuario sin TOTP llega a
`configurar_totp.php`. El backend genera el secreto con una fuente aleatoria
criptográfica, lo cifra antes de persistirlo y produce una URI `otpauth://` con
issuer `Control Stock`. El navegador recibe únicamente el QR durante este paso.

El usuario escanea el QR, introduce el código de seis dígitos y el backend lo
valida. `totp_enabled` solo cambia a 1 después de esa validación. Una cuenta ya
activada no puede volver a abrir la pantalla de QR.

## Medidas de seguridad

- AES-256-GCM autenticado, IV aleatorio de 12 bytes y etiqueta de 16 bytes.
- Clave de cifrado externa a código y base de datos.
- Respuestas de autenticación con `Cache-Control: no-store`.
- Sesiones estrictas, cookies `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS.
- Regeneración del identificador de sesión tras cada factor válido.
- Todos los paneles y endpoints requieren `mfa_verified === true` desde
  `seguridad.php`.
- CSRF en enrolamiento, verificación, restablecimiento y operaciones existentes.
- Formato exacto de seis dígitos.
- Máximo de cinco fallos; después se espera 60 segundos. No hay bloqueo permanente.
- Sesión pendiente de segundo factor con vencimiento de 10 minutos.
- Tolerancia TOTP de ±30 segundos (intervalo anterior, actual o siguiente), la
  mínima ventana práctica configurada para absorber una pequeña diferencia de reloj.
- `totp_last_timeslice` se actualiza dentro de una transacción con bloqueo de fila;
  cualquier intervalo ya aceptado se rechaza en sesiones posteriores.
- Los secretos y códigos no se escriben en logs, listados administrativos ni JSON.

El servidor y los teléfonos deben mantener la hora sincronizada.

## Administración y pérdida del dispositivo

Gestión de usuarios muestra solamente `Activado` o `Sin configurar`. Un admin
con sesión completa puede pulsar **Restablecer 2FA**; la acción exige CSRF y una
confirmación visual. El secreto cifrado, la fecha y el último intervalo se
eliminan. El admin nunca ve el secreto ni genera códigos por el usuario.

No se implementaron preguntas personales ni códigos de recuperación. Si se
pierde el dispositivo:

1. el usuario contacta al administrador;
2. el administrador restablece 2FA;
3. el usuario inicia sesión con sus credenciales habituales;
4. configura un autenticador nuevo.

## Pruebas

La prueba reproducible está en `tests/integracion_totp.php`. Requiere una base de
prueba accesible, la misma variable `CONTROL_STOCK_TOTP_KEY` del servidor y un
servidor local apuntando a `stock_app`:

```powershell
C:\xampp\php\php.exe stock_app\tests\integracion_totp.php http://127.0.0.1:8100
```

Crea cuentas, producto y venta con prefijos exclusivos, prueba los tres roles y
elimina esos datos en `finally`, incluso ante un fallo. Verifica enrolamiento,
QR, cifrado, código válido, panel/API bloqueados antes de MFA, roles, código
reutilizado, rate limit, logout, reset admin, CSRF y la regresión de productos,
clientes, ventas, filtros, historial, modificación, cancelación, stock,
auditoría, creación de usuarios y UTF-8.
