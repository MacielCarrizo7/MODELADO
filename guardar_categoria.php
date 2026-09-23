<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$id = isset($_POST["id"]) && $_POST["id"] !== "" ? (int) $_POST["id"] : null;
$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");
$icono = trim($_POST["icono"] ?? "🏷️");

if ($nombre === "" || mb_strlen($nombre) > 100) {
    responderJson(["error" => "El nombre de la categoría es obligatorio (máx. 100 caracteres)."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $fechaActual = date("Y-m-d H:i:s");

    // Validar nombre duplicado en otra categoría
    $existentes = $firestore->consultar("categorias", [
        ["nombre", "==", $nombre]
    ]);

    foreach ($existentes as $ex) {
        if ($id === null || (int)$ex["id"] !== $id) {
            responderJson(["error" => "Ya existe una categoría con el nombre '{$nombre}'."], 409);
        }
    }

    if ($id !== null && $id > 0) {
        // Edición
        $doc = [
            "id" => $id,
            "nombre" => $nombre,
            "descripcion" => $descripcion !== "" ? $descripcion : null,
            "icono" => $icono !== "" ? $icono : "🏷️",
            "modificado_el" => $fechaActual
        ];
        $firestore->actualizarDocumento("categorias", (string)$id, $doc);
        $categoriaId = $id;
        $mensaje = "Categoría actualizada exitosamente.";
    } else {
        // Creación
        $categoriaId = FirestoreConexion::obtenerSiguienteIdCategoria();
        $doc = [
            "id" => $categoriaId,
            "nombre" => $nombre,
            "descripcion" => $descripcion !== "" ? $descripcion : null,
            "icono" => $icono !== "" ? $icono : "🏷️",
            "creado_el" => $fechaActual,
            "modificado_el" => null
        ];
        $firestore->guardarDocumento("categorias", (string)$categoriaId, $doc);
        $mensaje = "Categoría creada exitosamente.";
    }

    responderJson([
        "success" => true,
        "id" => $categoriaId,
        "nombre" => $nombre,
        "mensaje" => $mensaje
    ]);
} catch (Throwable $e) {
    error_log("Error al guardar categoría: " . $e->getMessage());
    responderJson(["error" => "No se pudo guardar la categoría: " . $e->getMessage()], 500);
}
?>
