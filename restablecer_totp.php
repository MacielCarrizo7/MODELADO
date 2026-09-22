<?php
require "seguridad.php";
requerirPaginaAutenticada(["admin"]);
require "FirestoreConexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit;
}

$token = $_POST["csrf_token"] ?? "";
$usuarioId = filter_input(INPUT_POST, "usuario_id", FILTER_VALIDATE_INT);
if (!hash_equals($_SESSION["csrf_token"] ?? "", is_string($token) ? $token : "")) {
    http_response_code(403);
    echo "La sesión del formulario venció.";
    exit;
}
if (!$usuarioId || $usuarioId < 1) {
    http_response_code(400);
    echo "Usuario inválido.";
    exit;
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $firestore->actualizarCampos("usuarios", (string)$usuarioId, [
        "totp_secret_encrypted" => null,
        "totp_enabled" => 0,
        "totp_confirmed_at" => null,
        "totp_last_timeslice" => null
    ]);
} catch (Throwable $e) {
    error_log("Error al restablecer TOTP en Firestore: " . $e->getMessage());
}

header("Location: registro.php?totp_restablecido=1");
exit;
