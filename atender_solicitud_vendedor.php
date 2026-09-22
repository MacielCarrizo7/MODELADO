<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();
$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;
$usuarioId = (int) $_SESSION["usuario_id"];

if ($id <= 0) {
    responderJson(["error" => "ID de solicitud inválido."], 400);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE solicitudes_vendedor
         SET estado = 'ATENDIDA', fecha_atencion = NOW(), atendido_por = ?
         WHERE id = ?"
    );
    $stmt->execute([$usuarioId, $id]);

    if ($stmt->rowCount() === 0) {
        responderJson(["error" => "No se encontró la solicitud o ya fue atendida."], 404);
    }

    responderJson(["success" => true, "mensaje" => "Solicitud marcada como atendida."]);
} catch (Throwable $e) {
    error_log("Error en atender_solicitud_vendedor: " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar la solicitud: " . $e->getMessage()], 500);
}
?>
