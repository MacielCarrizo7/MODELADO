<?php
require_once "seguridad.php";
requerirPaginaAutenticada();

$destinos = [
    "admin"    => "admin.php",
    "vendedor" => "vendedor.php",
    "cliente"  => "cliente.php",
];

$rol = $_SESSION["usuario_rol"] ?? "";

if (!isset($destinos[$rol])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

header("Location: " . $destinos[$rol]);
exit;
?>
