<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
header("Content-Type: application/json; charset=UTF-8");

$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");
$motivo = trim($_GET["motivo"] ?? "");
$busqueda = trim($_GET["q"] ?? ($_GET["busqueda"] ?? ""));

if (($desde !== "" && !fechaIsoValida($desde)) || ($hasta !== "" && !fechaIsoValida($hasta))) {
    responderJson(["error" => "Ingresá fechas válidas."], 400);
}
if ($desde !== "" && $hasta !== "" && $desde > $hasta) {
    responderJson(["error" => "La fecha desde no puede ser posterior a la fecha hasta."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $bajas = $firestore->obtenerTodos("bajas_inventario");

    $resultado = [];
    foreach ($bajas as $b) {
        $fecha = (string)($b["fecha_baja"] ?? $b["creado_el"] ?? "");
        $fechaSolo = substr($fecha, 0, 10);
        $mot = (string)($b["motivo_baja"] ?? "Eliminación manual");
        $nombre = (string)($b["nombre"] ?? "");
        $codigo = (string)($b["codigo"] ?? "");
        $codigoBarras = (string)($b["codigo_barras"] ?? "");

        if ($desde !== "" && $fechaSolo !== "" && $fechaSolo < $desde) {
            continue;
        }
        if ($hasta !== "" && $fechaSolo !== "" && $fechaSolo > $hasta) {
            continue;
        }
        if ($motivo !== "" && strcasecmp($mot, $motivo) !== 0) {
            continue;
        }
        if ($busqueda !== "") {
            $term = mb_strtolower($busqueda);
            $match = str_contains(mb_strtolower($nombre), $term) ||
                     str_contains(mb_strtolower($codigo), $term) ||
                     str_contains(mb_strtolower($codigoBarras), $term) ||
                     str_contains(mb_strtolower($mot), $term);
            if (!$match) {
                continue;
            }
        }

        $resultado[] = [
            "id" => (int)($b["id"] ?? $b["_id"] ?? 0),
            "producto_id" => (int)($b["producto_id"] ?? 0),
            "codigo" => $codigo !== "" ? $codigo : null,
            "codigo_barras" => $codigoBarras !== "" ? $codigoBarras : null,
            "nombre" => $nombre,
            "categoria" => (string)($b["categoria"] ?? "Sin categoría"),
            "presentacion" => (string)($b["presentacion"] ?? "unidad"),
            "precio_costo" => (float)($b["precio_costo"] ?? 0),
            "precio_venta" => (float)($b["precio_venta"] ?? 0),
            "stock_remanente" => (int)($b["stock_remanente"] ?? 0),
            "unidades_vendidas_historicas" => (int)($b["unidades_vendidas_historicas"] ?? 0),
            "total_ventas_historicas_monto" => (float)($b["total_ventas_historicas_monto"] ?? 0),
            "total_perdida_costo" => (float)($b["total_perdida_costo"] ?? 0),
            "motivo_baja" => $mot,
            "observaciones" => !empty($b["observaciones"]) ? (string)$b["observaciones"] : null,
            "usuario_nombre" => (string)($b["usuario_nombre"] ?? "Administrador"),
            "fecha_baja" => $fecha
        ];
    }

    // Ordenar descendente por fecha_baja
    usort($resultado, function ($a, $b) {
        return strcmp($b["fecha_baja"], $a["fecha_baja"]);
    });

    responderJson(array_values($resultado));
} catch (Throwable $e) {
    error_log("Error al obtener bajas de inventario: " . $e->getMessage());
    responderJson(["error" => "No se pudieron obtener los registros de bajas de inventario."], 500);
}
?>
