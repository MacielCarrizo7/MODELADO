<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();
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
    $pdo->beginTransaction();

    $ventaStmt = $pdo->prepare(
        "SELECT id, producto_id, cantidad, precio_unitario, total, cliente_id, estado
         FROM ventas WHERE id = ? FOR UPDATE"
    );
    $ventaStmt->execute([$ventaId]);
    $venta = $ventaStmt->fetch();

    if (!$venta) {
        $pdo->rollBack();
        responderJson(["error" => "Venta no encontrada."], 404);
    }

    $rol = $_SESSION["usuario_rol"];
    $usuarioId = (int) $_SESSION["usuario_id"];
    if ($rol === "cliente" && (int) $venta["cliente_id"] !== $usuarioId) {
        $pdo->rollBack();
        responderJson(["error" => "No tenés permiso para modificar esta compra."], 403);
    }

    if ($venta["estado"] === "CANCELADA") {
        $pdo->commit();
        if ($accion === "cancelar") {
            responderJson(["success" => true, "already_cancelled" => true]);
        }
        responderJson(["error" => "Una venta cancelada no puede modificarse."], 409);
    }

    $productoStmt = $pdo->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
    $productoStmt->execute([(int) $venta["producto_id"]]);
    $producto = $productoStmt->fetch();
    if (!$producto) {
        throw new RuntimeException("El producto asociado ya no existe.");
    }

    $estadoAnterior = $venta["estado"];
    $cantidadAnterior = (int) $venta["cantidad"];
    $totalAnterior = (float) $venta["total"];
    $prefijoActor = strtoupper($rol);

    if ($accion === "cancelar") {
        $stock = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
        $stock->execute([$cantidadAnterior, (int) $venta["producto_id"]]);

        $actualizar = $pdo->prepare(
            "UPDATE ventas
             SET estado = 'CANCELADA', fecha_modificacion = NOW(), motivo_cancelacion = ?
             WHERE id = ?"
        );
        $actualizar->execute([$motivo !== "" ? $motivo : null, $ventaId]);

        $historial = $pdo->prepare(
            "INSERT INTO venta_historial
             (venta_id, usuario_id, tipo, cantidad_anterior, cantidad_nueva,
              total_anterior, total_nuevo, estado_anterior, estado_nuevo, motivo)
             VALUES (?, ?, ?, ?, 0, ?, 0, ?, 'CANCELADA', ?)"
        );
        $historial->execute([
            $ventaId, $usuarioId, $prefijoActor . "_CANCELA", $cantidadAnterior,
            $totalAnterior, $estadoAnterior, $motivo !== "" ? $motivo : null
        ]);

        $pdo->commit();
        responderJson(["success" => true, "estado" => "CANCELADA"]);
    }

    $cantidadNueva = filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT) ?: 0;
    if ($cantidadNueva <= 0) {
        $pdo->rollBack();
        responderJson(["error" => "La nueva cantidad debe ser mayor que cero."], 400);
    }
    if ($cantidadNueva === $cantidadAnterior) {
        $pdo->rollBack();
        responderJson(["error" => "La cantidad no cambió."], 400);
    }

    $diferencia = $cantidadNueva - $cantidadAnterior;
    if ($diferencia > 0 && (int) $producto["stock"] < $diferencia) {
        $pdo->rollBack();
        responderJson(["error" => "No hay stock suficiente para aumentar la cantidad."], 400);
    }

    $ajustarStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    $ajustarStock->execute([$diferencia, (int) $venta["producto_id"]]);
    $totalNuevo = $cantidadNueva * (float) $venta["precio_unitario"];

    $actualizar = $pdo->prepare(
        "UPDATE ventas
         SET cantidad = ?, total = ?, estado = 'MODIFICADA', fecha_modificacion = NOW(), motivo_cancelacion = NULL
         WHERE id = ?"
    );
    $actualizar->execute([$cantidadNueva, $totalNuevo, $ventaId]);

    // Actualizar también detalle_ventas
    $updateDetalle = $pdo->prepare(
        "UPDATE detalle_ventas
         SET cantidad = ?, total = ?
         WHERE venta_id = ?"
    );
    $updateDetalle->execute([$cantidadNueva, $totalNuevo, $ventaId]);

    $historial = $pdo->prepare(
        "INSERT INTO venta_historial
         (venta_id, usuario_id, tipo, cantidad_anterior, cantidad_nueva,
          total_anterior, total_nuevo, estado_anterior, estado_nuevo, motivo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'MODIFICADA', ?)"
    );
    $historial->execute([
        $ventaId, $usuarioId, $prefijoActor . "_MODIFICA_CANTIDAD",
        $cantidadAnterior, $cantidadNueva, $totalAnterior, $totalNuevo,
        $estadoAnterior, $motivo !== "" ? $motivo : null
    ]);

    $pdo->commit();
    responderJson(["success" => true, "estado" => "MODIFICADA", "total" => $totalNuevo]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al modificar venta: " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar la venta."], 500);
}
?>
