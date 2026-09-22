<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();
$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    responderJson(["error" => "ID de producto inválido."], 400);
}

try {
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    responderJson(["success" => true, "mensaje" => "Producto eliminado correctamente."]);
} catch (Throwable $e) {
    error_log("Error al eliminar producto: " . $e->getMessage());
    responderJson(["error" => "No se pudo eliminar el producto: " . $e->getMessage()], 500);
}
?>
