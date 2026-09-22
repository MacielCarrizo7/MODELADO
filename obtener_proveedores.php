<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$pdo = Conexion::obtenerInstancia();

$sql = "SELECT 
            p.nombre_proveedor AS proveedor,
            COALESCE(prod.total_productos, 0) AS total_productos,
            COALESCE(ing.total_ingresos, 0) AS total_ingresos,
            COALESCE(ing.total_unidades_ingresadas, 0) AS total_unidades,
            ing.ultimo_ingreso,
            COALESCE(prod.productos_lista, '—') AS productos_lista
        FROM (
            SELECT DISTINCT proveedor AS nombre_proveedor FROM productos WHERE proveedor IS NOT NULL AND proveedor != ''
            UNION
            SELECT DISTINCT proveedor AS nombre_proveedor FROM ingresos_stock WHERE proveedor IS NOT NULL AND proveedor != ''
        ) p
        LEFT JOIN (
            SELECT proveedor, COUNT(*) AS total_productos, GROUP_CONCAT(DISTINCT nombre ORDER BY nombre SEPARATOR ', ') AS productos_lista
            FROM productos
            WHERE proveedor IS NOT NULL AND proveedor != ''
            GROUP BY proveedor
        ) prod ON p.nombre_proveedor = prod.proveedor
        LEFT JOIN (
            SELECT proveedor, COUNT(*) AS total_ingresos, SUM(total_unidades) AS total_unidades_ingresadas, MAX(fecha) AS ultimo_ingreso
            FROM ingresos_stock
            WHERE proveedor IS NOT NULL AND proveedor != ''
            GROUP BY proveedor
        ) ing ON p.nombre_proveedor = ing.proveedor
        ORDER BY p.nombre_proveedor ASC";

try {
    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_proveedores: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
