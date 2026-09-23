<?php
require_once __DIR__ . "/seguridad.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
require_once __DIR__ . "/FirestoreConexion.php";
header("Content-Type: application/json; charset=UTF-8");

$busqueda = mb_strtolower(trim($_GET["busqueda"] ?? ""));
$categoriaFiltro = trim($_GET["categoria"] ?? "");

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    
    // Obtener todas las categorías
    $categorias = $firestore->obtenerTodos("categorias");
    usort($categorias, function ($a, $b) {
        return strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? "");
    });

    // Obtener todos los productos
    $productos = $firestore->obtenerColeccion("productos");

    $itemsCatalogo = [];

    foreach ($productos as $p) {
        $id = (int) ($p["id"] ?? $p["_id"] ?? 0);
        $nombre = (string) ($p["nombre"] ?? "");
        $descripcion = (string) ($p["descripcion"] ?? "");
        $presentacion = (string) ($p["presentacion"] ?? "unidad");
        $categoriaId = isset($p["categoria_id"]) ? (int)$p["categoria_id"] : null;
        $categoriaNombre = (string) ($p["categoria_nombre"] ?? $p["categoria"] ?? "General");
        $imagenUrl = (string) ($p["imagen_url"] ?? "");

        if ($nombre === "") {
            continue;
        }

        // Filtro de búsqueda
        if ($busqueda !== "") {
            $nomLower = mb_strtolower($nombre);
            $descLower = mb_strtolower($descripcion);
            $catLower = mb_strtolower($categoriaNombre);
            if (!str_contains($nomLower, $busqueda) && !str_contains($descLower, $busqueda) && !str_contains($catLower, $busqueda)) {
                continue;
            }
        }

        // Filtro de categoría
        if ($categoriaFiltro !== "" && $categoriaNombre !== $categoriaFiltro && (string)$categoriaId !== $categoriaFiltro) {
            continue;
        }

        // NOTA DE SEGURIDAD: NO se incluye precio, precio_costo, precio_venta ni stock
        $itemsCatalogo[] = [
            "id" => $id,
            "nombre" => $nombre,
            "descripcion" => $descripcion !== "" ? $descripcion : null,
            "presentacion" => $presentacion,
            "categoria_id" => $categoriaId,
            "categoria_nombre" => $categoriaNombre !== "" ? $categoriaNombre : "General",
            "imagen_url" => $imagenUrl !== "" ? $imagenUrl : null
        ];
    }

    // Ordenar alfabéticamente
    usort($itemsCatalogo, function ($a, $b) {
        return strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? "");
    });

    echo json_encode([
        "categorias" => array_values($categorias),
        "productos" => array_values($itemsCatalogo),
        "total" => count($itemsCatalogo)
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error al obtener catálogo: " . $e->getMessage());
    responderJson(["error" => "No se pudo cargar el catálogo de productos."], 500);
}
?>
