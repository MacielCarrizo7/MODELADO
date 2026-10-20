<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$nombre = trim($_POST["nombre"] ?? "");
$email = trim($_POST["email"] ?? "");
$direccion = trim($_POST["direccion"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$cuitCuil = trim($_POST["cuit_cuil"] ?? "");

if ($nombre === "" || mb_strlen($nombre) > 150) {
    responderJson(["error" => "El nombre o empresa del proveedor es obligatorio (máx. 150 caracteres)."], 400);
}

if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderJson(["error" => "El correo electrónico no tiene un formato válido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Validar si ya existe un proveedor con el mismo nombre
    $existentes = $firestore->consultar("proveedores", [
        ["nombre", "==", $nombre]
    ]);

    if (!empty($existentes)) {
        responderJson(["error" => "Ya existe un proveedor registrado con ese nombre."], 409);
    }

    $proveedorId = FirestoreConexion::obtenerSiguienteIdProveedor();
    $fechaActual = date("Y-m-d H:i:s");

    $proveedorDoc = [
        "id" => $proveedorId,
        "nombre" => $nombre,
        "email" => $email !== "" ? $email : null,
        "direccion" => $direccion !== "" ? $direccion : null,
        "telefono" => $telefono !== "" ? $telefono : null,
        "cuit_cuil" => $cuitCuil !== "" ? $cuitCuil : null,
        "creado_el" => $fechaActual,
        "modificado_el" => null
    ];

    $firestore->guardarDocumento("proveedores", (string)$proveedorId, $proveedorDoc);

    responderJson([
        "success" => true,
        "id" => $proveedorId,
        "nombre" => $nombre,
        "mensaje" => "Proveedor registrado exitosamente."
    ], 201);
} catch (Throwable $e) {
    error_log("Error al guardar proveedor: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar el proveedor: " . $e->getMessage()], 500);
}
?>
