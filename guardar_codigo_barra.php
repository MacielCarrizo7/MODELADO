<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$codigo = trim($datos["codigo"] ?? $datos["codigo_barras"] ?? "");
$nombre = trim($datos["nombre"] ?? $datos["producto_nombre"] ?? "Producto General");
$precio = isset($datos["precio"]) && $datos["precio"] !== "" ? floatval($datos["precio"]) : 0;
$formato = trim($datos["formato"] ?? "CODE128");
$productoId = isset($datos["producto_id"]) && $datos["producto_id"] !== "" ? (int)$datos["producto_id"] : null;

if ($codigo === "") {
    responderJson(["error" => "El código de barras es obligatorio."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $id = $firestore->obtenerSiguienteId("contadores", "codigos_barra", "ultimo_id");

    $usuarioId = isset($_SESSION["usuario_id"]) ? (int)$_SESSION["usuario_id"] : null;
    $usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Admin") . " " . ($_SESSION["usuario_apellido"] ?? ""));

    $doc = [
        "id" => $id,
        "codigo" => $codigo,
        "codigo_barras" => $codigo,
        "nombre" => $nombre,
        "precio" => $precio,
        "formato" => $formato,
        "producto_id" => $productoId,
        "usuario_id" => $usuarioId,
        "usuario_nombre" => $usuarioNombre,
        "fecha" => date("Y-m-d H:i:s")
    ];

    $firestore->guardarDocumento("codigos_barra", (string)$id, $doc);

    responderJson([
        "success" => true,
        "id" => $id,
        "item" => $doc,
        "mensaje" => "Código de barras registrado en el historial."
    ], 201);
} catch (Throwable $e) {
    error_log("Error al guardar código de barras: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar el código en el historial: " . $e->getMessage()], 500);
}
?>
