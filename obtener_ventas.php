<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");
$productoIdTexto = trim($_GET["producto_id"] ?? "");
$clienteIdTexto = trim($_GET["cliente_id"] ?? "");
$estado = strtoupper(trim($_GET["estado"] ?? ""));
$vendedorIdTexto = trim($_GET["vendedor_id"] ?? "");
$estadosValidos = ["ACTIVA", "MODIFICADA", "CANCELADA"];

if (($desde !== "" && !fechaIsoValida($desde)) || ($hasta !== "" && !fechaIsoValida($hasta))) {
    responderJson(["error" => "Ingresá fechas válidas."], 400);
}
if ($desde !== "" && $hasta !== "" && $desde > $hasta) {
    responderJson(["error" => "La fecha desde no puede ser posterior a la fecha hasta."], 400);
}
if ($estado !== "" && !in_array($estado, $estadosValidos, true)) {
    responderJson(["error" => "El estado seleccionado no es válido."], 400);
}

$prodIdFiltro = ($productoIdTexto !== "" && ctype_digit($productoIdTexto)) ? (int)$productoIdTexto : null;
$cliIdFiltro = ($clienteIdTexto !== "" && ctype_digit($clienteIdTexto)) ? (int)$clienteIdTexto : null;
$vendIdFiltro = ($vendedorIdTexto !== "" && ctype_digit($vendedorIdTexto)) ? (int)$vendedorIdTexto : null;

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $todasLasVentas = $firestore->obtenerColeccion("ventas");
    $todosLosUsuarios = $firestore->obtenerColeccion("usuarios");

    $mapaUsuarios = [];
    foreach ($todosLosUsuarios as $u) {
        $uId = (int) ($u["id"] ?? $u["_id"] ?? 0);
        if ($uId > 0) {
            $mapaUsuarios[$uId] = trim(($u["nombre"] ?? "") . " " . ($u["apellido"] ?? ""));
        }
    }

    $ventasFiltradas = [];

    foreach ($todasLasVentas as $v) {
        $id = (int) ($v["id"] ?? $v["_id"] ?? 0);
        $prodId = (int) ($v["producto_id"] ?? 0);
        $cliId = (int) ($v["cliente_id"] ?? 0);
        $usuId = (int) ($v["usuario_id"] ?? 0);
        $est = (string) ($v["estado"] ?? "ACTIVA");
        $fecha = (string) ($v["fecha"] ?? "");

        // Filtro por fecha desde
        if ($desde !== "" && $fecha !== "" && substr($fecha, 0, 10) < $desde) {
            continue;
        }

        // Filtro por fecha hasta
        if ($hasta !== "" && $fecha !== "" && substr($fecha, 0, 10) > $hasta) {
            continue;
        }

        // Filtro por producto
        if ($prodIdFiltro !== null && $prodId !== $prodIdFiltro) {
            continue;
        }

        // Filtro por cliente
        if ($cliIdFiltro !== null && $cliId !== $cliIdFiltro) {
            continue;
        }

        // Filtro por vendedor
        if ($vendIdFiltro !== null && $usuId !== $vendIdFiltro) {
            continue;
        }

        // Filtro por estado
        if ($estado !== "" && $est !== $estado) {
            continue;
        }

        $ventasFiltradas[] = [
            "id" => $id,
            "producto_id" => $prodId,
            "producto_nombre" => (string) ($v["producto_nombre"] ?? ""),
            "tipo_venta" => (string) ($v["tipo_venta"] ?? "unidad"),
            "cantidad_empaque" => (int) ($v["cantidad_empaque"] ?? $v["cantidad"] ?? 0),
            "cantidad" => (int) ($v["cantidad"] ?? 0),
            "precio_unitario" => (float) ($v["precio_unitario"] ?? 0),
            "subtotal" => (float) ($v["subtotal"] ?? 0),
            "descuento_porcentaje" => (float) ($v["descuento_porcentaje"] ?? 0),
            "descuento_monto" => (float) ($v["descuento_monto"] ?? 0),
            "total" => (float) ($v["total"] ?? 0),
            "fecha" => $fecha,
            "estado" => $est,
            "fecha_modificacion" => !empty($v["fecha_modificacion"]) ? (string)$v["fecha_modificacion"] : null,
            "motivo_cancelacion" => !empty($v["motivo_cancelacion"]) ? (string)$v["motivo_cancelacion"] : null,
            "cliente_id" => $cliId,
            "usuario_id" => $usuId,
            "cliente" => $mapaUsuarios[$cliId] ?? "",
            "vendedor" => $mapaUsuarios[$usuId] ?? ""
        ];
    }

    usort($ventasFiltradas, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode($ventasFiltradas, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_ventas (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
