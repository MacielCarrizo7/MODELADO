<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
header("Content-Type: application/json; charset=UTF-8");

$ventaId = filter_input(INPUT_GET, "venta_id", FILTER_VALIDATE_INT) ?: 0;
if ($ventaId <= 0) {
    responderJson(["error" => "Venta inválida."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    if ($_SESSION["usuario_rol"] === "cliente") {
        $venta = $firestore->obtenerDocumento("ventas", (string)$ventaId);
        if (!$venta || (int)($venta["cliente_id"] ?? 0) !== (int)$_SESSION["usuario_id"]) {
            responderJson(["error" => "No tenés permiso para consultar esta compra."], 403);
        }
    }

    $todosHistorial = $firestore->consultar("venta_historial", [
        ["venta_id", "==", $ventaId]
    ]);

    $todosLosUsuarios = $firestore->obtenerColeccion("usuarios");
    $mapaUsuarios = [];
    foreach ($todosLosUsuarios as $u) {
        $uId = (int) ($u["id"] ?? $u["_id"] ?? 0);
        if ($uId > 0) {
            $mapaUsuarios[$uId] = trim(($u["nombre"] ?? "") . " " . ($u["apellido"] ?? ""));
        }
    }

    $resultado = [];
    foreach ($todosHistorial as $h) {
        $uId = (int) ($h["usuario_id"] ?? 0);
        $resultado[] = [
            "id" => (int) ($h["id"] ?? $h["_id"] ?? 0),
            "usuario_id" => $uId,
            "tipo" => (string) ($h["tipo"] ?? ""),
            "cantidad_anterior" => isset($h["cantidad_anterior"]) ? (int)$h["cantidad_anterior"] : null,
            "cantidad_nueva" => isset($h["cantidad_nueva"]) ? (int)$h["cantidad_nueva"] : null,
            "total_anterior" => isset($h["total_anterior"]) ? (float)$h["total_anterior"] : null,
            "total_nuevo" => isset($h["total_nuevo"]) ? (float)$h["total_nuevo"] : null,
            "estado_anterior" => (string) ($h["estado_anterior"] ?? ""),
            "estado_nuevo" => (string) ($h["estado_nuevo"] ?? ""),
            "motivo" => !empty($h["motivo"]) ? (string)$h["motivo"] : null,
            "fecha" => (string) ($h["fecha"] ?? ""),
            "modificado_por" => $mapaUsuarios[$uId] ?? ""
        ];
    }

    usort($resultado, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_historial_venta (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
