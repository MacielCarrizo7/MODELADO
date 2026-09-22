<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["cliente"]);
header("Content-Type: application/json; charset=UTF-8");

$clienteId = (int) $_SESSION["usuario_id"];

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $compras = $firestore->consultar("ventas", [
        ["cliente_id", "==", $clienteId]
    ]);

    $resultado = [];
    foreach ($compras as $c) {
        $resultado[] = [
            "id" => (int) ($c["id"] ?? $c["_id"] ?? 0),
            "producto_nombre" => (string) ($c["producto_nombre"] ?? ""),
            "cantidad" => (int) ($c["cantidad"] ?? 0),
            "precio_unitario" => (float) ($c["precio_unitario"] ?? 0),
            "total" => (float) ($c["total"] ?? 0),
            "fecha" => (string) ($c["fecha"] ?? ""),
            "estado" => (string) ($c["estado"] ?? "ACTIVA"),
            "fecha_modificacion" => !empty($c["fecha_modificacion"]) ? (string)$c["fecha_modificacion"] : null,
            "motivo_cancelacion" => !empty($c["motivo_cancelacion"]) ? (string)$c["motivo_cancelacion"] : null
        ];
    }

    usort($resultado, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_compras_cliente (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
