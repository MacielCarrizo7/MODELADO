@echo off
chcp 65001 >nul
title INSTALADOR Y RESTAURADOR - CONTROL STOCK
color 0A

echo ===============================================================================
echo                🚀 INSTALADOR AUTOMÁTICO - CONTROL STOCK
echo                Configuración e importación para nueva PC (con XAMPP)
echo ===============================================================================
echo.

set "SCRIPT_DIR=%~dp0"
set "XAMPP_DEFAULT=C:\xampp"
set "HTDOCS_DEST=%XAMPP_DEFAULT%\htdocs\stock_app"

echo [1/5] Verificando ubicación de la aplicación...

:: Verificar si el script está corriendo dentro de htdocs\stock_app o en otra carpeta (ej. Pendrive/Descargas)
echo %SCRIPT_DIR% | findstr /i "htdocs\\stock_app" >nul
if %errorlevel% neq 0 (
    echo   La carpeta se está ejecutando fuera de htdocs (ej: Pendrive o Descargas).
    if exist "%XAMPP_DEFAULT%\htdocs" (
        echo   Copiando archivos a %HTDOCS_DEST%...
        if not exist "%HTDOCS_DEST%" mkdir "%HTDOCS_DEST%"
        xcopy "%SCRIPT_DIR%*" "%HTDOCS_DEST%\" /E /I /Y /Q >nul
        echo   [OK] Archivos instalados correctamente en C:\xampp\htdocs\stock_app
        echo.
        echo   Redirigiendo ejecución al nuevo directorio...
        cd /d "%HTDOCS_DEST%"
        set "SCRIPT_DIR=%HTDOCS_DEST%\"
    ) else (
        echo.
        echo   [!] No se encontró la carpeta C:\xampp\htdocs.
        echo       Asegúrate de tener XAMPP instalado en el disco C:\.
        echo.
    )
) else (
    echo   [OK] Ejecutando correctamente desde C:\xampp\htdocs\stock_app
)

echo.
echo [2/5] Buscando intérprete PHP de XAMPP...
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
    echo   [!] No se detectó php.exe. Se intentará continuar con MySQL directo.
) else (
    echo   [OK] PHP detectado: %PHP_BIN%
)

echo.
echo [3/5] Verificando que MySQL esté iniciado...
set "MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe"

powershell -NoProfile -Command "$tcp = New-Object System.Net.Sockets.TcpClient; try { $tcp.Connect('127.0.0.1', 3306); Write-Host '  [OK] Puerto MySQL (3306) activo y respondiendo.' -ForegroundColor Green; $tcp.Close(); exit 0; } catch { Write-Host '  [AVISO] No se pudo conectar al puerto 3306 de MySQL.' -ForegroundColor Yellow; exit 1; }"

if %errorlevel% neq 0 (
    echo.
    echo ===============================================================================
    echo                       ⚠️ ATENCIÓN: INICIA XAMPP
    echo ===============================================================================
    echo Abre el 'XAMPP Control Panel' y haz clic en 'Start' en los módulos:
    echo   1. Apache
    echo   2. MySQL
    echo.
    echo Luego de iniciarlos, presiona cualquier tecla para continuar con la instalación...
    echo ===============================================================================
    pause >nul
)

echo.
echo [4/5] Creando e importando la base de datos 'control_stock'...
set "SQL_IMPORT="
if exist "%SCRIPT_DIR%database_backup.sql" (
    set "SQL_IMPORT=%SCRIPT_DIR%database_backup.sql"
    echo   Usando respaldo con datos existentes: database_backup.sql
) else if exist "%SCRIPT_DIR%database.sql" (
    set "SQL_IMPORT=%SCRIPT_DIR%database.sql"
    echo   Usando estructura limpia inicial: database.sql
)

if not "%PHP_BIN%"=="" (
    "%PHP_BIN%" "%SCRIPT_DIR%migracion_importar_db.php"
    if %errorlevel% equ 0 (
        echo   [OK] Base de datos restaurada exitosamente con PHP.
        goto IMPORT_SUCCESS
    )
)

if exist "%MYSQL_BIN%" (
    if not "%SQL_IMPORT%"=="" (
        echo   Intentando importar directamente con mysql.exe...
        "%MYSQL_BIN%" -u root -e "CREATE DATABASE IF NOT EXISTS control_stock DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" >nul 2>nul
        "%MYSQL_BIN%" -u root control_stock < "%SQL_IMPORT%" >nul 2>nul
        if %errorlevel% equ 0 (
            echo   [OK] Base de datos importada exitosamente con mysql.exe.
            goto IMPORT_SUCCESS
        )
    )
)

echo.
echo   [!] No se pudo importar de forma automática directa.
echo       Puedes completar la importación desde tu navegador abriendo:
echo       http://localhost/stock_app/migracion_importar_db.php
echo       o importando '%SQL_IMPORT%' en phpMyAdmin (http://localhost/phpmyadmin)
echo.

:IMPORT_SUCCESS

echo.
echo [5/5] Verificando archivos de seguridad y dependencias...
if exist "%SCRIPT_DIR%.totp_key" (
    echo   [OK] Clave de cifrado 2FA (.totp_key) encontrada y activa.
) else (
    echo   [AVISO] No se encontró .totp_key. Se generará automáticamente en el primer acceso.
)

if exist "%SCRIPT_DIR%vendor\autoload.php" (
    echo   [OK] Librerías PHP (vendor) listas.
) else (
    echo   [AVISO] Si falta vendor, ejecuta 'composer install' dentro de la carpeta stock_app.
)

echo.
echo ===============================================================================
echo                      🎉 ¡INSTALACIÓN COMPLETADA CON ÉXITO!
echo ===============================================================================
echo.
echo La aplicación está lista para usarse en esta computadora:
echo.
echo   🌐 URL: http://localhost/stock_app/login.php
echo.
echo -------------------------------------------------------------------------------
echo CREDENCIALES DEL ADMINISTRADOR INICIAL:
echo   DNI:        11111111
echo   Nombre:     Admin
echo   Apellido:   Principal
echo   Contraseña: Admin1234!
echo -------------------------------------------------------------------------------
echo.
echo Abriendo el navegador en http://localhost/stock_app/login.php...
start http://localhost/stock_app/login.php

echo.
echo Presiona cualquier tecla para finalizar...
pause >nul
