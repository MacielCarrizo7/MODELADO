<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["cliente"]);
header("Content-Type: application/json; charset=UTF-8");

$pdo = Conexion::obtenerInstancia();
$clienteId = (int) $_SESSION["usuario_id"];

try {
    $stmt = $pdo->prepare(
        "SELECT id, producto_nombre, cantidad, precio_unitario, total, fecha, estado,
                fecha_modificacion, motivo_cancelacion
         FROM ventas
         WHERE cliente_id = ?
         ORDER BY fecha DESC, id DESC"
    );
    $stmt->execute([$clienteId]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_compras_cliente: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
