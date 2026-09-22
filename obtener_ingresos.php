<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$pdo = Conexion::obtenerInstancia();

$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");
$productoIdTexto = trim($_GET["producto_id"] ?? "");
$proveedor = trim($_GET["proveedor"] ?? "");
$usuarioIdTexto = trim($_GET["usuario_id"] ?? "");
$semaforo = trim($_GET["semaforo"] ?? "");

if (($desde !== "" && !fechaIsoValida($desde)) || ($hasta !== "" && !fechaIsoValida($hasta))) {
    responderJson(["error" => "Ingresá fechas válidas."], 400);
}
if ($desde !== "" && $hasta !== "" && $desde > $hasta) {
    responderJson(["error" => "La fecha desde no puede ser posterior a la fecha hasta."], 400);
}

$condiciones = [];
$parametros = [];

if ($desde !== "") {
    $condiciones[] = "i.fecha >= ?";
    $parametros[] = $desde . " 00:00:00";
}
if ($hasta !== "") {
    $diaSiguiente = (new DateTimeImmutable($hasta))->modify("+1 day")->format("Y-m-d 00:00:00");
    $condiciones[] = "i.fecha < ?";
    $parametros[] = $diaSiguiente;
}
if ($productoIdTexto !== "") {
    if (!ctype_digit($productoIdTexto) || (int) $productoIdTexto <= 0) {
        responderJson(["error" => "El producto seleccionado no es válido."], 400);
    }
    $condiciones[] = "i.producto_id = ?";
    $parametros[] = (int) $productoIdTexto;
}
if ($proveedor !== "") {
    $condiciones[] = "i.proveedor LIKE ?";
    $parametros[] = "%" . $proveedor . "%";
}
if ($usuarioIdTexto !== "") {
    if (!ctype_digit($usuarioIdTexto) || (int) $usuarioIdTexto <= 0) {
        responderJson(["error" => "El usuario seleccionado no es válido."], 400);
    }
    $condiciones[] = "i.usuario_id = ?";
    $parametros[] = (int) $usuarioIdTexto;
}

if ($semaforo === "rojo") {
    $condiciones[] = "(i.fecha_vencimiento IS NOT NULL AND i.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 45 DAY))";
} elseif ($semaforo === "amarillo") {
    $condiciones[] = "(i.fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 45 DAY) AND i.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 90 DAY))";
} elseif ($semaforo === "verde") {
    $condiciones[] = "(i.fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 90 DAY))";
} elseif ($semaforo === "sin_fecha") {
    $condiciones[] = "i.fecha_vencimiento IS NULL";
}

$sql = "SELECT i.id, i.producto_id, i.producto_nombre, i.cantidad, i.presentacion,
               i.unidades_por_bulto, i.total_unidades, i.precio_unitario, i.proveedor,
               i.fecha_vencimiento, i.usuario_id, i.motivo, i.fecha
        FROM ingresos_stock i";

if ($condiciones !== []) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}
$sql .= " ORDER BY i.fecha DESC, i.id DESC";

try {
    require_once "FirestoreConexion.php";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $ingresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userIds = [];
    foreach ($ingresos as $ing) {
        if (!empty($ing["usuario_id"])) $userIds[(int)$ing["usuario_id"]] = true;
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

    foreach ($ingresos as &$ing) {
        $uId = (int) ($ing["usuario_id"] ?? 0);
        $ing["usuario"] = $mapaUsuarios[$uId] ?? "";
    }
    unset($ing);

    echo json_encode($ingresos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_ingresos: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
