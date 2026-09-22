<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    responderJson(["error" => "ID de producto inválido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $eliminado = $firestore->eliminarDocumento("productos", (string)$id);
    responderJson(["success" => true, "mensaje" => "Producto eliminado correctamente."]);
} catch (Throwable $e) {
    error_log("Error al eliminar producto en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo eliminar el producto: " . $e->getMessage()], 500);
}
?>
