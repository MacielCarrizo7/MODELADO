<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$pdo = Conexion::obtenerInstancia();
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

$condiciones = [];
$parametros = [];
if ($desde !== "") {
    $condiciones[] = "v.fecha >= ?";
    $parametros[] = $desde . " 00:00:00";
}
if ($hasta !== "") {
    $diaSiguiente = (new DateTimeImmutable($hasta))->modify("+1 day")->format("Y-m-d 00:00:00");
    $condiciones[] = "v.fecha < ?";
    $parametros[] = $diaSiguiente;
}
if ($productoIdTexto !== "") {
    if (!ctype_digit($productoIdTexto) || (int) $productoIdTexto <= 0) {
        responderJson(["error" => "El producto seleccionado no es válido."], 400);
    }
    $condiciones[] = "v.producto_id = ?";
    $parametros[] = (int) $productoIdTexto;
}
if ($clienteIdTexto !== "") {
    if (!ctype_digit($clienteIdTexto) || (int) $clienteIdTexto <= 0) {
        responderJson(["error" => "El cliente seleccionado no es válido."], 400);
    }
    $condiciones[] = "v.cliente_id = ?";
    $parametros[] = (int) $clienteIdTexto;
}
if ($estado !== "") {
    $condiciones[] = "v.estado = ?";
    $parametros[] = $estado;
}
if ($vendedorIdTexto !== "") {
    if (!ctype_digit($vendedorIdTexto) || (int) $vendedorIdTexto <= 0) {
        responderJson(["error" => "El vendedor seleccionado no es válido."], 400);
    }
    $condiciones[] = "v.usuario_id = ?";
    $parametros[] = (int) $vendedorIdTexto;
}

$sql = "SELECT v.id, v.producto_id, v.producto_nombre, v.tipo_venta, v.cantidad_empaque, v.cantidad,
               v.precio_unitario, v.subtotal, v.descuento_porcentaje, v.descuento_monto,
               v.total, v.fecha, v.estado, v.fecha_modificacion, v.motivo_cancelacion,
               v.cliente_id, v.usuario_id
        FROM ventas v";
if ($condiciones !== []) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}
$sql .= " ORDER BY v.fecha DESC, v.id DESC";

try {
    require_once "FirestoreConexion.php";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userIds = [];
    foreach ($ventas as $v) {
        if (!empty($v["cliente_id"])) $userIds[(int)$v["cliente_id"]] = true;
        if (!empty($v["usuario_id"])) $userIds[(int)$v["usuario_id"]] = true;
    }

    $mapaUsuarios = [];
    if (!empty($userIds)) {
        $firestore = FirestoreConexion::obtenerFirestore();
        foreach (array_keys($userIds) as $uid) {
            $uDoc = $firestore->obtenerDocumento("usuarios", (string)$uid);
            if ($uDoc) {
                $nombreCompleto = trim(($uDoc["nombre"] ?? "") . " " . ($uDoc["apellido"] ?? ""));
                $mapaUsuarios[$uid] = $nombreCompleto;
            } else {
                $mapaUsuarios[$uid] = "";
            }
        }
    }

    foreach ($ventas as &$v) {
        $cId = (int) ($v["cliente_id"] ?? 0);
        $uId = (int) ($v["usuario_id"] ?? 0);
        $v["cliente"] = $mapaUsuarios[$cId] ?? "";
        $v["vendedor"] = $mapaUsuarios[$uId] ?? "";
    }
    unset($v);

    echo json_encode($ventas, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_ventas: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
