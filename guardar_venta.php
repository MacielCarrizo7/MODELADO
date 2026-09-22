<?php
require_once "seguridad.php";
require_once "conexion.php";
require_once "FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();
$productoId = filter_input(INPUT_POST, "producto_id", FILTER_VALIDATE_INT) ?: 0;
$clienteId = filter_input(INPUT_POST, "cliente_id", FILTER_VALIDATE_INT) ?: 0;
$cantidad = filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT) ?: 0;
$tipoVenta = trim($_POST["tipo_venta"] ?? "unidad");
$descuentoPorcentaje = floatval($_POST["descuento_porcentaje"] ?? 0);

$tiposValidos = ["unidad", "caja", "bulto"];
if (!in_array($tipoVenta, $tiposValidos, true)) {
    $tipoVenta = "unidad";
}

if ($descuentoPorcentaje < 0 || $descuentoPorcentaje > 100) {
    responderJson(["error" => "El porcentaje de descuento debe estar entre 0% y 100%."], 400);
}

if ($productoId <= 0 || $clienteId <= 0 || $cantidad <= 0) {
    responderJson(["error" => "Seleccioná un producto, un cliente y una cantidad válida."], 400);
}

try {
    $clienteDoc = FirestoreConexion::obtenerFirestore()->obtenerDocumento("usuarios", (string)$clienteId);
    if (!$clienteDoc || ($clienteDoc["rol"] ?? "") !== "cliente") {
        throw new DomainException("El cliente seleccionado no existe.");
    }

    $pdo->beginTransaction();

    $productoStmt = $pdo->prepare("SELECT id, nombre, precio, stock, presentacion, unidades_por_bulto FROM productos WHERE id = ? FOR UPDATE");
    $productoStmt->execute([$productoId]);
    $producto = $productoStmt->fetch();
    if (!$producto) {
        throw new DomainException("Producto no encontrado.");
    }

    $unidadesPorEmpaque = max(1, (int) ($producto["unidades_por_bulto"] ?? 1));
    $cantidadEmpaque = $cantidad;
    
    if ($tipoVenta === "caja" || $tipoVenta === "bulto") {
        $totalUnidades = $cantidad * $unidadesPorEmpaque;
    } else {
        $totalUnidades = $cantidad;
    }

    if ((int) $producto["stock"] < $totalUnidades) {
        throw new DomainException("No hay stock suficiente. Disponible: " . $producto["stock"] . " unidades (solicitadas: " . $totalUnidades . " un.).");
    }

    $precioUnitario = (float) $producto["precio"];
    $subtotal = $precioUnitario * $totalUnidades;
    $descuentoMonto = round($subtotal * ($descuentoPorcentaje / 100), 2);
    $total = max(0, $subtotal - $descuentoMonto);
    $usuarioId = (int) $_SESSION["usuario_id"];

    // 1. Inserción en cabecera de ventas
    $insertVenta = $pdo->prepare(
        "INSERT INTO ventas
         (producto_id, producto_nombre, tipo_venta, cantidad_empaque, cantidad, precio_unitario, subtotal, descuento_porcentaje, descuento_monto, total, usuario_id, cliente_id, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVA')"
    );
    $insertVenta->execute([
        $productoId,
        $producto["nombre"],
        $tipoVenta,
        $cantidadEmpaque,
        $totalUnidades,
        $precioUnitario,
        $subtotal,
        $descuentoPorcentaje,
        $descuentoMonto,
        $total,
        $usuarioId,
        $clienteId
    ]);
    $ventaId = (int) $pdo->lastInsertId();

    // 2. Inserción en detalle_ventas
    $insertDetalle = $pdo->prepare(
        "INSERT INTO detalle_ventas
         (venta_id, producto_id, producto_nombre, tipo_venta, cantidad_empaque, cantidad, precio_unitario, subtotal, descuento_monto, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertDetalle->execute([
        $ventaId,
        $productoId,
        $producto["nombre"],
        $tipoVenta,
        $cantidadEmpaque,
        $totalUnidades,
        $precioUnitario,
        $subtotal,
        $descuentoMonto,
        $total
    ]);

    // 3. Descontar stock del producto
    $updateStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    $updateStock->execute([$totalUnidades, $productoId]);

    // 4. Registro en historial de ventas
    $historial = $pdo->prepare(
        "INSERT INTO venta_historial
         (venta_id, usuario_id, tipo, cantidad_nueva, total_nuevo, estado_anterior, estado_nuevo)
         VALUES (?, ?, 'VENTA_CREADA', ?, ?, 'NUEVA', 'ACTIVA')"
    );
    $historial->execute([$ventaId, $usuarioId, $totalUnidades, $total]);

    $pdo->commit();
    responderJson([
        "success" => true,
        "venta_id" => $ventaId,
        "total_unidades" => $totalUnidades,
        "subtotal" => $subtotal,
        "descuento_monto" => $descuentoMonto,
        "total" => $total
    ], 201);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderJson(["error" => $e->getMessage()], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al guardar venta: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la venta: " . $e->getMessage()], 500);
}
?>
