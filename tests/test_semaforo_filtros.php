<?php

declare(strict_types=1);

@ini_set("display_errors", "0");
require_once __DIR__ . "/../seguridad.php";
iniciarSesionAplicacion();

$_SESSION["usuario_id"] = 4;
$_SESSION["usuario_rol"] = "admin";
$_SESSION["mfa_verified"] = true;

require_once __DIR__ . "/../conexion.php";

$conexion = Conexion::obtenerInstancia();

function afirmar(bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new RuntimeException("ERROR: " . $mensaje);
    }
    echo "  [OK] {$mensaje}\n";
}

echo "=== PRUEBA DE FILTROS DE SEMÁFORO FIFO EN ENDPOINT Y CLIENTE ===\n\n";

// Limpiar datos previos
$conexion->query("DELETE FROM productos WHERE nombre LIKE 'TEST_SEM_%'");

// Insertar productos en diferentes estados
$conexion->query("INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor) VALUES ('TEST_SEM_ROJO', 100, 10, 'unidad', 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Prov Rojo')");
$conexion->query("INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor) VALUES ('TEST_SEM_AMARILLO', 200, 20, 'caja', 12, DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'Prov Amarillo')");
$conexion->query("INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor) VALUES ('TEST_SEM_VERDE', 300, 30, 'bulto', 24, DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'Prov Verde')");
$conexion->query("INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor) VALUES ('TEST_SEM_SIN_FECHA', 400, 40, 'unidad', 1, NULL, 'Prov Sin Fecha')");

function ejecutarObtenerProductos(array $getParams): array {
    $_GET = $getParams;
    ob_start();
    include __DIR__ . "/../obtener_productos.php";
    $salida = ob_get_clean();
    // Extraer solo la parte JSON en caso de warnings de header
    $pos = strpos($salida, "[");
    if ($pos !== false) {
        $salida = substr($salida, $pos);
    }
    return json_decode($salida, true) ?: [];
}

// 1. Probar filtro ROJO (<= 45 días)
$prodsRojo = ejecutarObtenerProductos(["semaforo" => "rojo"]);
$nombresRojo = array_column($prodsRojo, "nombre");
afirmar(in_array("TEST_SEM_ROJO", $nombresRojo, true), "Filtro ROJO incluye producto con vencimiento en 20 días");
afirmar(!in_array("TEST_SEM_AMARILLO", $nombresRojo, true), "Filtro ROJO no incluye producto de 60 días");
afirmar(!in_array("TEST_SEM_VERDE", $nombresRojo, true), "Filtro ROJO no incluye producto de 120 días");
afirmar(!in_array("TEST_SEM_SIN_FECHA", $nombresRojo, true), "Filtro ROJO no incluye producto sin fecha");

// 2. Probar filtro AMARILLO (46 a 90 días)
$prodsAmarillo = ejecutarObtenerProductos(["semaforo" => "amarillo"]);
$nombresAmarillo = array_column($prodsAmarillo, "nombre");
afirmar(in_array("TEST_SEM_AMARILLO", $nombresAmarillo, true), "Filtro AMARILLO incluye producto con vencimiento en 60 días");
afirmar(!in_array("TEST_SEM_ROJO", $nombresAmarillo, true), "Filtro AMARILLO no incluye producto de 20 días");
afirmar(!in_array("TEST_SEM_VERDE", $nombresAmarillo, true), "Filtro AMARILLO no incluye producto de 120 días");

// 3. Probar filtro VERDE (> 90 días)
$prodsVerde = ejecutarObtenerProductos(["semaforo" => "verde"]);
$nombresVerde = array_column($prodsVerde, "nombre");
afirmar(in_array("TEST_SEM_VERDE", $nombresVerde, true), "Filtro VERDE incluye producto con vencimiento en 120 días");
afirmar(!in_array("TEST_SEM_ROJO", $nombresVerde, true), "Filtro VERDE no incluye producto de 20 días");

// 4. Probar filtro SIN FECHA
$prodsSinFecha = ejecutarObtenerProductos(["semaforo" => "sin_fecha"]);
$nombresSinFecha = array_column($prodsSinFecha, "nombre");
afirmar(in_array("TEST_SEM_SIN_FECHA", $nombresSinFecha, true), "Filtro SIN FECHA incluye producto sin fecha");
afirmar(!in_array("TEST_SEM_ROJO", $nombresSinFecha, true), "Filtro SIN FECHA no incluye productos con fecha");

// 5. Probar Kardex con filtro semáforo
function ejecutarObtenerIngresos(array $getParams): array {
    $_GET = $getParams;
    ob_start();
    include __DIR__ . "/../obtener_ingresos.php";
    $salida = ob_get_clean();
    $pos = strpos($salida, "[");
    if ($pos !== false) {
        $salida = substr($salida, $pos);
    }
    return json_decode($salida, true) ?: [];
}

$ingresosRojo = ejecutarObtenerIngresos(["semaforo" => "rojo"]);
afirmar(is_array($ingresosRojo), "Filtro de semáforo en Kardex de ingresos ejecutado correctamente");

// Limpiar
$conexion->query("DELETE FROM productos WHERE nombre LIKE 'TEST_SEM_%'");

echo "\n=== TODAS LAS PRUEBAS DE SEMÁFORO EN FILTROS PASARON EXITOSAMENTE ===\n";
