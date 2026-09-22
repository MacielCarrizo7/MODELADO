<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["cliente"]);
requerirCsrfJson();

$clienteId = (int) $_SESSION["usuario_id"];
$mensaje = trim($_POST["mensaje"] ?? "");

if (mb_strlen($mensaje) > 500) {
    responderJson(["error" => "El mensaje no puede superar los 500 caracteres."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Verificar si ya tiene una solicitud pendiente
    $solicitudes = $firestore->consultar("solicitudes_vendedor", [
        ["cliente_id", "==", $clienteId],
        ["estado", "==", "PENDIENTE"]
    ]);

    if (!empty($solicitudes)) {
        responderJson(["success" => true, "mensaje" => "Ya tenés una solicitud de atención pendiente. Un vendedor te contactará a la brevedad."]);
    }

    $solicitudId = FirestoreConexion::obtenerSiguienteIdSolicitud();
    $nuevaSol = [
        "id" => $solicitudId,
        "cliente_id" => $clienteId,
        "mensaje" => $mensaje !== "" ? $mensaje : null,
        "estado" => "PENDIENTE",
        "fecha" => date("Y-m-d H:i:s"),
        "fecha_atencion" => null,
        "atendido_por" => null
    ];

    $firestore->guardarDocumento("solicitudes_vendedor", (string)$solicitudId, $nuevaSol);

    responderJson([
        "success" => true,
        "mensaje" => "Solicitud enviada correctamente. El equipo de ventas y administración ha sido notificado."
    ], 201);
} catch (Throwable $e) {
    error_log("Error en solicitar_vendedor (Firestore): " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la solicitud: " . $e->getMessage()], 500);
}
?>
