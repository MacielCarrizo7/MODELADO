<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $categorias = $firestore->obtenerTodos("categorias");

    // Ordenar alfabéticamente por nombre
    usort($categorias, function ($a, $b) {
        return strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? "");
    });

    responderJson(array_values($categorias));
} catch (Throwable $e) {
    error_log("Error al obtener categorías: " . $e->getMessage());
    responderJson(["error" => "No se pudieron obtener las categorías."], 500);
}
?>
