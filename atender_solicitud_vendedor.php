<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;
$usuarioId = (int) $_SESSION["usuario_id"];

if ($id <= 0) {
    responderJson(["error" => "ID de solicitud inválido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $solicitud = $firestore->obtenerDocumento("solicitudes_vendedor", (string)$id);

    if (!$solicitud) {
        responderJson(["error" => "No se encontró la solicitud."], 404);
    }

    $firestore->actualizarCampos("solicitudes_vendedor", (string)$id, [
        "estado" => "ATENDIDA",
        "fecha_atencion" => date("Y-m-d H:i:s"),
        "atendido_por" => $usuarioId
    ]);

    responderJson(["success" => true, "mensaje" => "Solicitud marcada como atendida."]);
} catch (Throwable $e) {
    error_log("Error en atender_solicitud_vendedor (Firestore): " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar la solicitud: " . $e->getMessage()], 500);
}
?>
