@echo off
chcp 65001 >nul
title EXPORTAR APLICACIÓN - CONTROL STOCK
color 0B

echo ===============================================================================
echo                📦 ASISTENTE DE EXPORTACIÓN - CONTROL STOCK
echo                Transportar la aplicación y base de datos a otra PC
echo ===============================================================================
echo.

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

echo [1/4] Comprobando entorno de XAMPP / PHP...
set "PHP_BIN="
if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else (
    where php >nul 2>nul
    if %errorlevel% equ 0 (
        set "PHP_BIN=php"
    )
)

if "%PHP_BIN%"=="" (
    echo.
    echo [ADVERTENCIA] No se encontró php.exe en C:\xampp\php\ ni en el PATH.
    echo Asegúrate de tener XAMPP instalado.
    echo.
) else (
    echo   [OK] PHP detectado: %PHP_BIN%
)

echo.
echo [2/4] Exportando base de datos 'control_stock'...
if not "%PHP_BIN%"=="" (
    "%PHP_BIN%" "%SCRIPT_DIR%migracion_exportar_db.php"
    if %errorlevel% neq 0 (
        echo   [!] La exportación automática con PHP no pudo conectarse con la clave vacía.
        echo       Si tienes contraseña en MySQL, puedes exportar desde el navegador entrando a:
        echo       http://localhost/stock_app/migracion_exportar_db.php
        echo.
    ) else (
        echo   [OK] Base de datos exportada en: database_backup.sql
    )
) else (
    echo   [!] Omitiendo exportación CLI por falta de PHP. Si tienes database.sql se usará esa.
)

echo.
echo [3/4] Verificando clave de seguridad 2FA (.totp_key)...
if not exist "%SCRIPT_DIR%.totp_key" (
    echo   Generando clave .totp_key inicial...
    if not "%PHP_BIN%"=="" (
        "%PHP_BIN%" -r "file_put_contents('.totp_key', base64_encode(random_bytes(32)));"
    )
)
if exist "%SCRIPT_DIR%.totp_key" (
    echo   [OK] Clave de seguridad .totp_key incluida en el paquete.
) else (
    echo   [!] Sin archivo .totp_key local.
)

echo.
echo [4/4] Creando archivo comprimido ZIP para transportar...
set "ZIP_DESTINO=%SCRIPT_DIR%PAQUETE_MIGRACION_STOCK_APP.zip"
if exist "%ZIP_DESTINO%" del /f /q "%ZIP_DESTINO%" >nul 2>nul

powershell -NoProfile -Command "Write-Host '  Comprimiendo carpeta stock_app en ZIP...' -ForegroundColor Cyan; Compress-Archive -Path '.\*' -DestinationPath '%ZIP_DESTINO%' -Force; if (Test-Path '%ZIP_DESTINO%') { Write-Host '  [OK] Archivo ZIP creado exitosamente!' -ForegroundColor Green } else { Write-Host '  [!] Error al crear ZIP' -ForegroundColor Red }"

echo.
echo ===============================================================================
echo                      🎉 ¡EXPORTACIÓN FINALIZADA!
echo ===============================================================================
echo.
echo Se ha generado el archivo listo para transportar:
echo   📁 %ZIP_DESTINO%
echo.
echo PASOS PARA LLEVAR A LA OTRA COMPUTADORA:
echo  1. Copia el archivo 'PAQUETE_MIGRACION_STOCK_APP.zip' a un Pendrive o Google Drive.
echo  2. En la nueva PC, instala XAMPP (con Apache y MySQL).
echo  3. Descomprime el ZIP dentro de: C:\xampp\htdocs\stock_app
echo  4. Abre la carpeta y haz doble clic en '2_INSTALAR_EN_OTRA_PC.bat'.
echo.
echo ===============================================================================
echo Presiona cualquier tecla para abrir la carpeta del archivo generado...
pause >nul
explorer.exe /select,"%ZIP_DESTINO%"
