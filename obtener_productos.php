<?php
require_once "seguridad.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
require_once "conexion.php";
header("Content-Type: application/json; charset=UTF-8");

$pdo = Conexion::obtenerInstancia();

$semaforo = trim($_GET["semaforo"] ?? "");
$busqueda = trim($_GET["busqueda"] ?? "");
$presentacion = trim($_GET["presentacion"] ?? "");

$condiciones = [];
$parametros = [];

if ($busqueda !== "") {
    $condiciones[] = "(nombre LIKE ? OR proveedor LIKE ?)";
    $parametros[] = "%" . $busqueda . "%";
    $parametros[] = "%" . $busqueda . "%";
}

if ($presentacion !== "") {
    $condiciones[] = "presentacion = ?";
    $parametros[] = $presentacion;
}

if ($semaforo === "rojo") {
    $condiciones[] = "(fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 45 DAY))";
} elseif ($semaforo === "amarillo") {
    $condiciones[] = "(fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 45 DAY) AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 90 DAY))";
} elseif ($semaforo === "verde") {
    $condiciones[] = "(fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 90 DAY))";
} elseif ($semaforo === "sin_fecha") {
    $condiciones[] = "fecha_vencimiento IS NULL";
}

$sql = "SELECT id, codigo, nombre, descripcion, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor, categoria
        FROM productos";

if ($condiciones !== []) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= " ORDER BY CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC, nombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $productos = $stmt->fetchAll();
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_productos: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
