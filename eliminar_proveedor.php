<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;

if ($id <= 0) {
    responderJson(["error" => "ID de proveedor inválido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $prov = $firestore->obtenerDocumento("proveedores", (string)$id);

    if (!$prov) {
        responderJson(["error" => "El proveedor no existe."], 404);
    }

    $nombre = (string) ($prov["nombre"] ?? "");

    // Verificar si hay productos asociados a este proveedor
    if ($nombre !== "") {
        $prods = $firestore->consultar("productos", [
            ["proveedor", "==", $nombre]
        ]);
        if (!empty($prods)) {
            responderJson(["error" => "No se puede eliminar el proveedor porque tiene productos asociados en el inventario."], 409);
        }
    }

    $firestore->eliminarDocumento("proveedores", (string)$id);

    responderJson(["success" => true, "mensaje" => "Proveedor eliminado correctamente."]);
} catch (Throwable $e) {
    error_log("Error al eliminar proveedor: " . $e->getMessage());
    responderJson(["error" => "No se pudo eliminar el proveedor: " . $e->getMessage()], 500);
}
?>
