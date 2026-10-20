<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$usuarioId = filter_input(INPUT_POST, "usuario_id", FILTER_VALIDATE_INT) ?: 0;
$limite = filter_input(INPUT_POST, "limite_descuento", FILTER_VALIDATE_FLOAT);

if ($usuarioId <= 0) {
    responderJson(["error" => "ID de usuario inválido."], 400);
}

if ($limite === false || $limite === null || $limite < 0 || $limite > 100) {
    responderJson(["error" => "El porcentaje de descuento debe estar entre 0% y 100%."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $usuario = $firestore->obtenerDocumento("usuarios", (string)$usuarioId);

    if (!$usuario) {
        responderJson(["error" => "Usuario no encontrado."], 404);
    }

    $firestore->actualizarCampos("usuarios", (string)$usuarioId, [
        "limite_descuento" => (float) $limite
    ]);

    responderJson([
        "success" => true,
        "limite_descuento" => (float) $limite,
        "mensaje" => "Límite de descuento actualizado correctamente a {$limite}%."
    ]);
} catch (Throwable $e) {
    error_log("Error al actualizar límite de descuento: " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar el límite de descuento."], 500);
}
?>
