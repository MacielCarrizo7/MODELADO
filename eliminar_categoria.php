<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

if ($id <= 0) {
    responderJson(["error" => "Identificador de categoría inválido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Validar si existen productos asignados a esta categoría
    $productosAsignados = $firestore->consultar("productos", [
        ["categoria_id", "==", $id]
    ]);

    if (!empty($productosAsignados)) {
        $cantidad = count($productosAsignados);
        responderJson([
            "error" => "No se puede eliminar la categoría porque tiene {$cantidad} producto(s) asignado(s). Reasigna los productos primero."
        ], 409);
    }

    $firestore->eliminarDocumento("categorias", (string)$id);

    responderJson([
        "success" => true,
        "mensaje" => "Categoría eliminada correctamente."
    ]);
} catch (Throwable $e) {
    error_log("Error al eliminar categoría: " . $e->getMessage());
    responderJson(["error" => "No se pudo eliminar la categoría: " . $e->getMessage()], 500);
}
?>
