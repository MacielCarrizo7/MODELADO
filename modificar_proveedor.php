<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;
$nombre = trim($_POST["nombre"] ?? "");
$email = trim($_POST["email"] ?? "");
$direccion = trim($_POST["direccion"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$cuitCuil = trim($_POST["cuit_cuil"] ?? "");

if ($id <= 0) {
    responderJson(["error" => "ID de proveedor inválido."], 400);
}

if ($nombre === "" || mb_strlen($nombre) > 150) {
    responderJson(["error" => "El nombre o empresa del proveedor es obligatorio (máx. 150 caracteres)."], 400);
}

if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderJson(["error" => "El correo electrónico no tiene un formato válido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $provActual = $firestore->obtenerDocumento("proveedores", (string)$id);

    if (!$provActual) {
        responderJson(["error" => "Proveedor no encontrado."], 404);
    }

    $nombreAnterior = (string) ($provActual["nombre"] ?? "");

    $camposActualizados = [
        "nombre" => $nombre,
        "email" => $email !== "" ? $email : null,
        "direccion" => $direccion !== "" ? $direccion : null,
        "telefono" => $telefono !== "" ? $telefono : null,
        "cuit_cuil" => $cuitCuil !== "" ? $cuitCuil : null,
        "modificado_el" => date("Y-m-d H:i:s")
    ];

    $firestore->actualizarCampos("proveedores", (string)$id, $camposActualizados);

    // Si el nombre cambió, actualizar productos asociados
    if ($nombreAnterior !== "" && $nombreAnterior !== $nombre) {
        $productos = $firestore->consultar("productos", [
            ["proveedor", "==", $nombreAnterior]
        ]);
        foreach ($productos as $p) {
            $pId = (string) ($p["id"] ?? $p["_id"]);
            $firestore->actualizarCampos("productos", $pId, ["proveedor" => $nombre]);
        }
    }

    responderJson(["success" => true, "mensaje" => "Proveedor actualizado correctamente."]);
} catch (Throwable $e) {
    error_log("Error al modificar proveedor: " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar el proveedor: " . $e->getMessage()], 500);
}
?>
