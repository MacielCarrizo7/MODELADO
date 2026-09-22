<?php

declare(strict_types=1);

require_once __DIR__ . "/../conexion.php";

$conexion = Conexion::obtenerInstancia();

$dni = "11111111";
$nombre = "Admin";
$apellido = "Principal";
$passwordPlano = "Admin1234!";
$rol = "admin";
$hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

// Verificar si ya existe un usuario con este DNI
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE dni = ? LIMIT 1");
$stmt->bind_param("s", $dni);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();

if ($existe) {
    $update = $conexion->prepare(
        "UPDATE usuarios
         SET nombre = ?, apellido = ?, password = ?, rol = 'admin', totp_enabled = 0, totp_secret_encrypted = NULL
         WHERE id = ?"
    );
    $update->bind_param("sssi", $nombre, $apellido, $hash, $existe["id"]);
    $update->execute();
    echo "Usuario admin actualizado correctamente (ID: {$existe['id']})\n";
} else {
    $insert = $conexion->prepare(
        "INSERT INTO usuarios (dni, nombre, apellido, password, rol, totp_enabled)
         VALUES (?, ?, ?, ?, 'admin', 0)"
    );
    $insert->bind_param("ssss", $dni, $nombre, $apellido, $hash);
    $insert->execute();
    $id = $conexion->insert_id;
    echo "Usuario admin creado correctamente (ID: {$id})\n";
}
