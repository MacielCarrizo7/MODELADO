<?php
require_once __DIR__ . "/../FirestoreConexion.php";

$dni = "12345678";
$nombre = "Admin";
$apellido = "General";
$password = "admin123";

$firestore = FirestoreConexion::obtenerFirestore();

$candidatos = $firestore->consultar("usuarios", [
    ["dni", "==", $dni],
    ["nombre", "==", $nombre]
]);

echo "Candidatos encontrados: " . count($candidatos) . PHP_EOL;
$usuario = null;
foreach ($candidatos as $c) {
    $apeDoc = trim((string)($c["apellido"] ?? ""));
    if ($apeDoc === "" || mb_strtolower($apeDoc) === mb_strtolower($apellido)) {
        $usuario = $c;
        break;
    }
}

if ($usuario && password_verify($password, $usuario["password"])) {
    echo "LOGIN EXITOSO! Usuario ID: " . $usuario["id"] . " | Rol: " . $usuario["rol"] . PHP_EOL;
} else {
    echo "LOGIN FALLIDO!" . PHP_EOL;
    exit(1);
}
