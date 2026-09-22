<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
requerirCsrfJson();

$ventaId = filter_input(INPUT_POST, "venta_id", FILTER_VALIDATE_INT) ?: 0;
$accion = $_POST["accion"] ?? "";
$motivo = trim($_POST["motivo"] ?? "");

if ($ventaId <= 0 || !in_array($accion, ["cancelar", "modificar_cantidad"], true)) {
    responderJson(["error" => "Solicitud inválida."], 400);
}
if (mb_strlen($motivo) > 500) {
    responderJson(["error" => "El motivo es demasiado extenso."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $venta = $firestore->obtenerDocumento("ventas", (string)$ventaId);

    if (!$venta) {
        responderJson(["error" => "Venta no encontrada."], 404);
    }

    $rol = $_SESSION["usuario_rol"];
    $usuarioId = (int) $_SESSION["usuario_id"];
    if ($rol === "cliente" && (int) ($venta["cliente_id"] ?? 0) !== $usuarioId) {
        responderJson(["error" => "No tenés permiso para modificar esta compra."], 403);
    }

    if (($venta["estado"] ?? "") === "CANCELADA") {
        if ($accion === "cancelar") {
            responderJson(["success" => true, "already_cancelled" => true]);
        }
        responderJson(["error" => "Una venta cancelada no puede modificarse."], 409);
    }

    $productoId = (int) ($venta["producto_id"] ?? 0);
    $producto = $firestore->obtenerDocumento("productos", (string)$productoId);
    if (!$producto) {
        throw new RuntimeException("El producto asociado ya no existe.");
    }

    $estadoAnterior = (string) ($venta["estado"] ?? "ACTIVA");
    $cantidadAnterior = (int) ($venta["cantidad"] ?? 0);
    $totalAnterior = (float) ($venta["total"] ?? 0);
    $prefijoActor = strtoupper($rol);
    $fechaActual = date("Y-m-d H:i:s");

    if ($accion === "cancelar") {
        // Reintegrar stock al producto
        $stockActual = (int) ($producto["stock"] ?? 0);
        $firestore->actualizarCampos("productos", (string)$productoId, [
            "stock" => $stockActual + $cantidadAnterior
        ]);

        // Actualizar estado de la venta
        $firestore->actualizarCampos("ventas", (string)$ventaId, [
            "estado" => "CANCELADA",
            "fecha_modificacion" => $fechaActual,
            "motivo_cancelacion" => $motivo !== "" ? $motivo : null
        ]);

        // Registrar en historial
        $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
        $historialDoc = [
            "id" => $histId,
            "venta_id" => $ventaId,
            "usuario_id" => $usuarioId,
            "tipo" => $prefijoActor . "_CANCELA",
            "cantidad_anterior" => $cantidadAnterior,
            "cantidad_nueva" => 0,
            "total_anterior" => $totalAnterior,
            "total_nuevo" => 0.0,
            "estado_anterior" => $estadoAnterior,
            "estado_nuevo" => "CANCELADA",
            "motivo" => $motivo !== "" ? $motivo : null,
            "fecha" => $fechaActual
        ];
        $firestore->guardarDocumento("venta_historial", (string)$histId, $historialDoc);

        // Registrar en movimientos de producto
        FirestoreConexion::registrarMovimientoProducto(
            productoId: $productoId,
            tipo: "VENTA_CANCELADA",
            descripcion: "Venta #{$ventaId} cancelada. Reintegro de {$cantidadAnterior} un. al stock" . ($motivo !== "" ? " (Motivo: {$motivo})" : ""),
            cantidadAnterior: $stockActual,
            cantidadNueva: $stockActual + $cantidadAnterior,
            diferencia: +$cantidadAnterior,
            precioAnterior: (float) ($venta["precio_unitario"] ?? 0),
            precioNuevo: (float) ($venta["precio_unitario"] ?? 0),
            usuarioId: $usuarioId
        );

        responderJson(["success" => true, "estado" => "CANCELADA"]);
    }

    // Modificar cantidad
    $cantidadNueva = filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT) ?: 0;
    if ($cantidadNueva <= 0) {
        responderJson(["error" => "La nueva cantidad debe ser mayor que cero."], 400);
    }
    if ($cantidadNueva === $cantidadAnterior) {
        responderJson(["error" => "La cantidad no cambió."], 400);
    }

    $diferencia = $cantidadNueva - $cantidadAnterior;
    $stockActual = (int) ($producto["stock"] ?? 0);
    if ($diferencia > 0 && $stockActual < $diferencia) {
        responderJson(["error" => "No hay stock suficiente para aumentar la cantidad."], 400);
    }

    // Ajustar stock del producto
    $firestore->actualizarCampos("productos", (string)$productoId, [
        "stock" => $stockActual - $diferencia
    ]);

    $precioUnitario = (float) ($venta["precio_unitario"] ?? 0);
    $totalNuevo = round($cantidadNueva * $precioUnitario, 2);

    // Actualizar venta
    $firestore->actualizarCampos("ventas", (string)$ventaId, [
        "cantidad" => $cantidadNueva,
        "total" => $totalNuevo,
        "estado" => "MODIFICADA",
        "fecha_modificacion" => $fechaActual,
        "motivo_cancelacion" => null
    ]);

    // Actualizar detalle_ventas
    $firestore->actualizarCampos("detalle_ventas", (string)$ventaId, [
        "cantidad" => $cantidadNueva,
        "total" => $totalNuevo
    ]);

    // Registrar en historial
    $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
    $historialDoc = [
        "id" => $histId,
        "venta_id" => $ventaId,
        "usuario_id" => $usuarioId,
        "tipo" => $prefijoActor . "_MODIFICA_CANTIDAD",
        "cantidad_anterior" => $cantidadAnterior,
        "cantidad_nueva" => $cantidadNueva,
        "total_anterior" => $totalAnterior,
        "total_nuevo" => $totalNuevo,
        "estado_anterior" => $estadoAnterior,
        "estado_nuevo" => "MODIFICADA",
        "motivo" => $motivo !== "" ? $motivo : null,
        "fecha" => $fechaActual
    ];
    $firestore->guardarDocumento("venta_historial", (string)$histId, $historialDoc);

    // Registrar en movimientos de producto
    $signo = $diferencia > 0 ? "-{$diferencia}" : "+" . abs($diferencia);
    FirestoreConexion::registrarMovimientoProducto(
        productoId: $productoId,
        tipo: "VENTA_MODIFICADA",
        descripcion: "Venta #{$ventaId} modificada. Cantidad: {$cantidadAnterior} → {$cantidadNueva} ({$signo} un. en stock)" . ($motivo !== "" ? " (Motivo: {$motivo})" : ""),
        cantidadAnterior: $stockActual,
        cantidadNueva: $stockActual - $diferencia,
        diferencia: -$diferencia,
        precioAnterior: (float) ($venta["precio_unitario"] ?? 0),
        precioNuevo: (float) ($venta["precio_unitario"] ?? 0),
        usuarioId: $usuarioId
    );

    responderJson(["success" => true, "estado" => "MODIFICADA", "total" => $totalNuevo]);
} catch (Throwable $e) {
    error_log("Error al modificar venta en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar la venta: " . $e->getMessage()], 500);
}
?>
