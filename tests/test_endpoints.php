<?php

declare(strict_types=1);

// Configuramos sesión simulada
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION["usuario_id"] = 1;
$_SESSION["usuario_nombre"] = "Admin";
$_SESSION["usuario_apellido"] = "Test";
$_SESSION["usuario_rol"] = "admin";
$_SESSION["mfa_verified"] = true;
$_SESSION["csrf_token"] = "token_prueba_123";

// 1. Test obtener_vendedores.php
ob_start();
require __DIR__ . "/../obtener_vendedores.php";
$jsonVendedores = ob_get_clean();
$vendedores = json_decode($jsonVendedores, true);

// 2. Test obtener_productos.php
ob_start();
require __DIR__ . "/../obtener_productos.php";
$jsonProductos = ob_get_clean();
$productos = json_decode($jsonProductos, true);

// 3. Test obtener_ingresos.php
ob_start();
require __DIR__ . "/../obtener_ingresos.php";
$jsonIngresos = ob_get_clean();
$ingresos = json_decode($jsonIngresos, true);

// 4. Test obtener_ventas.php
ob_start();
require __DIR__ . "/../obtener_ventas.php";
$jsonVentas = ob_get_clean();
$ventas = json_decode($jsonVentas, true);

echo "=== TEST ENDPOINTS RESULTADOS ===\n";
echo "Vendedores obtenidos: " . count($vendedores) . "\n";
echo "Productos obtenidos: " . count($productos) . "\n";
echo "Ingresos obtenidos: " . count($ingresos) . "\n";
echo "Ventas obtenidas: " . count($ventas) . "\n";

if (is_array($vendedores) && is_array($productos) && is_array($ingresos) && is_array($ventas)) {
    echo "\n>>> TODOS LOS ENDPOINTS RETORNARON JSON VÁLIDO <<<\n";
} else {
    echo "\n>>> FALLÓ LA VALIDACIÓN DE RESPUESTAS <<<\n";
    exit(1);
}
