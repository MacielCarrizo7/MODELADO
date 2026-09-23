<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

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

// Validar límite de descuento configurado para el vendedor
if (isset($_SESSION["usuario_rol"]) && $_SESSION["usuario_rol"] === "vendedor") {
    $limiteVendedor = isset($_SESSION["usuario_limite_descuento"]) ? (float)$_SESSION["usuario_limite_descuento"] : 15.0;
    if ($descuentoPorcentaje > ($limiteVendedor + 0.001)) {
        responderJson([
            "error" => "El descuento aplicado ({$descuentoPorcentaje}%) supera tu límite máximo autorizado de {$limiteVendedor}% establecido por el administrador."
        ], 403);
    }
}

if ($productoId <= 0 || $clienteId <= 0 || $cantidad <= 0) {
    responderJson(["error" => "Seleccioná un producto, un cliente y una cantidad válida."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // 1. Validar cliente
    $clienteDoc = $firestore->obtenerDocumento("usuarios", (string)$clienteId);
    if (!$clienteDoc || ($clienteDoc["rol"] ?? "") !== "cliente") {
        throw new DomainException("El cliente seleccionado no existe.");
    }

    // 2. Validar producto
    $producto = $firestore->obtenerDocumento("productos", (string)$productoId);
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

    $stockActual = (int) ($producto["stock"] ?? 0);
    if ($stockActual < $totalUnidades) {
        throw new DomainException("No hay stock suficiente. Disponible: " . $stockActual . " unidades (solicitadas: " . $totalUnidades . " un.).");
    }

    $precioUnitario = (float) $producto["precio"];
    $subtotal = $precioUnitario * $totalUnidades;
    $descuentoMonto = round($subtotal * ($descuentoPorcentaje / 100), 2);
    $total = max(0, $subtotal - $descuentoMonto);
    $usuarioId = (int) $_SESSION["usuario_id"];
    $fechaActual = date("Y-m-d H:i:s");

    $ventaId = FirestoreConexion::obtenerSiguienteIdVenta();

    // 3. Guardar venta en colección ventas
    $ventaDoc = [
        "id" => $ventaId,
        "producto_id" => $productoId,
        "producto_nombre" => (string)$producto["nombre"],
        "tipo_venta" => $tipoVenta,
        "cantidad_empaque" => $cantidadEmpaque,
        "cantidad" => $totalUnidades,
        "precio_unitario" => $precioUnitario,
        "subtotal" => $subtotal,
        "descuento_porcentaje" => $descuentoPorcentaje,
        "descuento_monto" => $descuentoMonto,
        "total" => $total,
        "usuario_id" => $usuarioId,
        "cliente_id" => $clienteId,
        "estado" => "ACTIVA",
        "fecha" => $fechaActual
    ];
    $firestore->guardarDocumento("ventas", (string)$ventaId, $ventaDoc);

    // 4. Guardar detalle_ventas
    $detalleDoc = [
        "id" => $ventaId,
        "venta_id" => $ventaId,
        "producto_id" => $productoId,
        "producto_nombre" => (string)$producto["nombre"],
        "tipo_venta" => $tipoVenta,
        "cantidad_empaque" => $cantidadEmpaque,
        "cantidad" => $totalUnidades,
        "precio_unitario" => $precioUnitario,
        "subtotal" => $subtotal,
        "descuento_monto" => $descuentoMonto,
        "total" => $total
    ];
    $firestore->guardarDocumento("detalle_ventas", (string)$ventaId, $detalleDoc);

    // 5. Descontar stock del producto
    $nuevoStock = $stockActual - $totalUnidades;
    $firestore->actualizarCampos("productos", (string)$productoId, ["stock" => $nuevoStock]);

    // 6. Registro en historial de ventas
    $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
    $historialDoc = [
        "id" => $histId,
        "venta_id" => $ventaId,
        "usuario_id" => $usuarioId,
        "tipo" => "VENTA_CREADA",
        "cantidad_nueva" => $totalUnidades,
        "total_nuevo" => $total,
        "estado_anterior" => "NUEVA",
        "estado_nuevo" => "ACTIVA",
        "fecha" => $fechaActual
    ];
    $firestore->guardarDocumento("venta_historial", (string)$histId, $historialDoc);

    // 7. Registro en trazabilidad de movimientos de producto
    $clienteNombre = trim(($clienteDoc["nombre"] ?? "Cliente") . " " . ($clienteDoc["apellido"] ?? ""));
    FirestoreConexion::registrarMovimientoProducto(
        productoId: $productoId,
        tipo: "VENTA",
        descripcion: "Venta #{$ventaId} registrada a {$clienteNombre} ({$totalUnidades} un. por $" . number_format($total, 2) . ")",
        cantidadAnterior: $stockActual,
        cantidadNueva: $nuevoStock,
        diferencia: -$totalUnidades,
        precioAnterior: $precioUnitario,
        precioNuevo: $precioUnitario,
        usuarioId: $usuarioId
    );

    responderJson([
        "success" => true,
        "venta_id" => $ventaId,
        "total_unidades" => $totalUnidades,
        "subtotal" => $subtotal,
        "descuento_monto" => $descuentoMonto,
        "total" => $total
    ], 201);
} catch (DomainException $e) {
    responderJson(["error" => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log("Error al guardar venta en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la venta: " . $e->getMessage()], 500);
}
?>
