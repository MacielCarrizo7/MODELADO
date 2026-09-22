<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["cliente"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();
$clienteId = (int) $_SESSION["usuario_id"];
$mensaje = trim($_POST["mensaje"] ?? "");

if (mb_strlen($mensaje) > 500) {
    responderJson(["error" => "El mensaje no puede superar los 500 caracteres."], 400);
}

try {
    // Verificar si ya tiene una solicitud pendiente reciente
    $check = $pdo->prepare("SELECT id FROM solicitudes_vendedor WHERE cliente_id = ? AND estado = 'PENDIENTE' LIMIT 1");
    $check->execute([$clienteId]);
    $existente = $check->fetch();

    if ($existente) {
        responderJson(["success" => true, "mensaje" => "Ya tenés una solicitud de atención pendiente. Un vendedor te contactará a la brevedad."]);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO solicitudes_vendedor (cliente_id, mensaje, estado) VALUES (?, ?, 'PENDIENTE')"
    );
    $mensajeParam = $mensaje !== "" ? $mensaje : null;
    $stmt->execute([$clienteId, $mensajeParam]);

    responderJson([
        "success" => true,
        "mensaje" => "Solicitud enviada correctamente. El equipo de ventas y administración ha sido notificado."
    ], 201);
} catch (Throwable $e) {
    error_log("Error en solicitar_vendedor: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la solicitud: " . $e->getMessage()], 500);
}
?>
