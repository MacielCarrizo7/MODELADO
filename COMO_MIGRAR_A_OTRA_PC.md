# 🚀 GUÍA PARA TRANSPORTAR LA APLICACIÓN A OTRA COMPUTADORA

Esta guía te explica cómo mover toda la aplicación **Control Stock** (código, dependencias, usuarios, productos, ventas y configuración de seguridad 2FA) a otra PC con **1 solo clic**.

---

## 📋 ¿Qué incluye el sistema de migración rápida?

1. **`1_EXPORTAR_APP.bat`**: Script para ejecutar en tu **PC actual**. Respalda la base de datos con todos tus datos reales y crea un archivo comprimido `PAQUETE_MIGRACION_STOCK_APP.zip`.
2. **`2_INSTALAR_EN_OTRA_PC.bat`**: Script para ejecutar en la **nueva PC**. Crea la base de datos `control_stock`, importa todas las tablas y datos, verifica las claves de seguridad y abre el navegador listo para usar.
3. **`migracion_exportar_db.php`**: Asistente web y por consola para exportar la base de datos.
4. **`migracion_importar_db.php`**: Asistente web y por consola para importar/restaurar la base de datos.

---

## 🖥️ PASO 1: En tu PC Actual (Origen)

1. Abre la carpeta del proyecto `C:\xampp\htdocs\stock_app`.
2. Haz doble clic en el archivo:
   👉 **`1_EXPORTAR_APP.bat`**
3. El script realizará automáticamente:
   - Exportará toda la base de datos con tus datos actuales a `database_backup.sql`.
   - Incluirá la clave de cifrado 2FA `.totp_key`.
   - Empaquetará todo en el archivo comprimido **`PAQUETE_MIGRACION_STOCK_APP.zip`**.
4. **Copia el archivo `PAQUETE_MIGRACION_STOCK_APP.zip`** a un Pendrive (memoria USB) o súbelo a Google Drive / OneDrive / correo.

> 💡 *Alternativa Web*: También puedes ingresar desde tu navegador a `http://localhost/stock_app/migracion_exportar_db.php` para generar y descargar el respaldo SQL.

---

## 💻 PASO 2: En la Nueva PC (Destino)

### Requisitos previos en la nueva PC:
- Tener instalado **XAMPP** (con PHP 8.2+ y MySQL/MariaDB).
- Abrir **XAMPP Control Panel** y hacer clic en **Start** en:
  - **Apache**
  - **MySQL**

### Instalación con 1 Clic:
1. Conecta tu pendrive y copia el archivo **`PAQUETE_MIGRACION_STOCK_APP.zip`**.
2. Descomprímelo dentro de:
   `C:\xampp\htdocs\stock_app`
   *(Si lo descomprimes en otra carpeta o en el pendrive, el script se encargará de copiarlo a `htdocs\stock_app` automáticamente)*.
3. Entra a la carpeta `C:\xampp\htdocs\stock_app` y haz doble clic en:
   👉 **`2_INSTALAR_EN_OTRA_PC.bat`**
4. El script realizará:
   - Verificará que Apache y MySQL estén activos.
   - Creará la base de datos `control_stock`.
   - Restaurará todas las tablas, usuarios, productos, ingresos y ventas.
   - Verificará librerías (`vendor/`) y clave `.totp_key`.
   - Abrirá automáticamente tu navegador web en: `http://localhost/stock_app/login.php`.

---

## 🔑 Credenciales de Acceso

| Rol | DNI | Nombre | Apellido | Contraseña |
| :--- | :--- | :--- | :--- | :--- |
| **Administrador** | `11111111` | `Admin` | `Principal` | `Admin1234!` |

---

## ❓ Preguntas Frecuentes y Solución de Problemas

### 1. ¿Se conservan los productos, ventas y usuarios cargados?
**Sí.** El archivo `1_EXPORTAR_APP.bat` genera `database_backup.sql` que contiene todos los registros actuales de tu base de datos.

### 2. ¿Qué pasa con el Segundo Factor de Autenticación (2FA)?
El paquete incluye el archivo `.totp_key`, lo que garantiza que los códigos de Google Authenticator / Authy que los usuarios ya tenían configurados sigan funcionando exactamente igual en la nueva PC.

### 3. ¿Qué hacer si en la nueva PC MySQL tiene contraseña?
Por defecto en XAMPP el usuario es `root` sin contraseña. Si en la nueva máquina le pusiste contraseña a MySQL:
1. Abre el archivo `conexion.php` y coloca tu contraseña en `$clave = "tu_password";`.
2. O entra desde el navegador a `http://localhost/stock_app/migracion_importar_db.php` donde podrás ingresar el usuario y contraseña para importar la base de datos con un clic.
