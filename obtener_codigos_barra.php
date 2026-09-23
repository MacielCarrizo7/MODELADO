<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$busqueda = mb_strtolower(trim($_GET["busqueda"] ?? ""));

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $codigos = $firestore->obtenerColeccion("codigos_barra");

    $items = [];
    foreach ($codigos as $c) {
        $id = (int) ($c["id"] ?? $c["_id"] ?? 0);
        $codigo = (string) ($c["codigo"] ?? $c["codigo_barras"] ?? "");
        $nombre = (string) ($c["nombre"] ?? "Producto General");
        $precio = (float) ($c["precio"] ?? 0);
        $formato = (string) ($c["formato"] ?? "CODE128");
        $fecha = (string) ($c["fecha"] ?? "");
        $usuarioNombre = (string) ($c["usuario_nombre"] ?? "Admin");
        $productoId = isset($c["producto_id"]) ? (int)$c["producto_id"] : null;

        if ($codigo === "") continue;

        if ($busqueda !== "") {
            $codLower = mb_strtolower($codigo);
            $nomLower = mb_strtolower($nombre);
            if (!str_contains($codLower, $busqueda) && !str_contains($nomLower, $busqueda)) {
                continue;
            }
        }

        $items[] = [
            "id" => $id,
            "codigo" => $codigo,
            "codigo_barras" => $codigo,
            "nombre" => $nombre,
            "precio" => $precio,
            "formato" => $formato,
            "fecha" => $fecha,
            "usuario_nombre" => $usuarioNombre,
            "producto_id" => $productoId
        ];
    }

    usort($items, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode($items, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error al obtener historial de códigos de barra: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
