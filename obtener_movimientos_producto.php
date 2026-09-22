<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
header("Content-Type: application/json; charset=UTF-8");

$productoId = filter_input(INPUT_GET, "producto_id", FILTER_VALIDATE_INT) ?: 0;

if ($productoId <= 0) {
    responderJson(["error" => "ID de producto inválido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Validar producto
    $producto = $firestore->obtenerDocumento("productos", (string)$productoId);
    if (!$producto) {
        responderJson(["error" => "Producto no encontrado."], 404);
    }

    $movimientos = $firestore->consultar("movimientos_producto", [
        ["producto_id", "==", $productoId]
    ]);

    $resultado = [];
    foreach ($movimientos as $m) {
        $resultado[] = [
            "id" => (int) ($m["id"] ?? $m["_id"] ?? 0),
            "producto_id" => (int) ($m["producto_id"] ?? $productoId),
            "tipo" => (string) ($m["tipo"] ?? "MOVIMIENTO"),
            "descripcion" => (string) ($m["descripcion"] ?? ""),
            "cantidad_anterior" => isset($m["cantidad_anterior"]) ? (int)$m["cantidad_anterior"] : null,
            "cantidad_nueva" => isset($m["cantidad_nueva"]) ? (int)$m["cantidad_nueva"] : null,
            "diferencia" => isset($m["diferencia"]) ? (int)$m["diferencia"] : null,
            "precio_anterior" => isset($m["precio_anterior"]) ? (float)$m["precio_anterior"] : null,
            "precio_nuevo" => isset($m["precio_nuevo"]) ? (float)$m["precio_nuevo"] : null,
            "usuario_nombre" => (string) ($m["usuario_nombre"] ?? "Sistema"),
            "fecha" => (string) ($m["fecha"] ?? "")
        ];
    }

    usort($resultado, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    responderJson([
        "producto" => [
            "id" => $productoId,
            "nombre" => (string) ($producto["nombre"] ?? ""),
            "codigo" => (string) ($producto["codigo"] ?? ""),
            "codigo_barras" => (string) ($producto["codigo_barras"] ?? ""),
            "stock_actual" => (int) ($producto["stock"] ?? 0),
            "precio_actual" => (float) ($producto["precio"] ?? 0),
            "presentacion" => (string) ($producto["presentacion"] ?? "unidad"),
            "proveedor" => (string) ($producto["proveedor"] ?? "—")
        ],
        "movimientos" => $resultado
    ]);
} catch (Throwable $e) {
    error_log("Error en obtener_movimientos_producto: " . $e->getMessage());
    responderJson(["error" => "No se pudo obtener el historial de movimientos."], 500);
}
?>
