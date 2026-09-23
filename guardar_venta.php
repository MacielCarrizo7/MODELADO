<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
requerirCsrfJson();

$inputRaw = file_get_contents("php://input");
$datosJson = json_decode($inputRaw, true);

if (!is_array($datosJson)) {
    // Si viene por multipart/form-data
    $clienteId = filter_input(INPUT_POST, "cliente_id", FILTER_VALIDATE_INT) ?: 0;
    $itemsRaw = $_POST["items"] ?? null;
    if ($itemsRaw && is_string($itemsRaw)) {
        $items = json_decode($itemsRaw, true) ?: [];
    } elseif (isset($_POST["producto_id"])) {
        // Modo unitario compatible
        $items = [[
            "producto_id" => filter_input(INPUT_POST, "producto_id", FILTER_VALIDATE_INT) ?: 0,
            "cantidad" => filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT) ?: 0,
            "tipo_venta" => trim($_POST["tipo_venta"] ?? "unidad"),
            "descuento_porcentaje" => floatval($_POST["descuento_porcentaje"] ?? 0)
        ]];
    } else {
        $items = [];
    }
} else {
    $clienteId = (int) ($datosJson["cliente_id"] ?? 0);
    $items = $datosJson["items"] ?? [];
    if (empty($items) && isset($datosJson["producto_id"])) {
        $items = [[
            "producto_id" => (int) ($datosJson["producto_id"] ?? 0),
            "cantidad" => (int) ($datosJson["cantidad"] ?? 0),
            "tipo_venta" => trim($datosJson["tipo_venta"] ?? "unidad"),
            "descuento_porcentaje" => floatval($datosJson["descuento_porcentaje"] ?? 0)
        ]];
    }
}

if ($clienteId <= 0) {
    responderJson(["error" => "Debés seleccionar un cliente válido."], 400);
}

if (!is_array($items) || empty($items)) {
    responderJson(["error" => "El carrito de ventas está vacío. Agregá al menos un producto."], 400);
}

// Límite de descuento para vendedores
$rol = $_SESSION["usuario_rol"] ?? "";
$limiteVendedor = ($rol === "admin") ? 100.0 : (isset($_SESSION["usuario_limite_descuento"]) ? (float)$_SESSION["usuario_limite_descuento"] : 15.0);

$tiposValidos = ["unidad", "caja", "bulto"];

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // 1. Validar cliente
    $clienteDoc = $firestore->obtenerDocumento("usuarios", (string)$clienteId);
    if (!$clienteDoc || ($clienteDoc["rol"] ?? "") !== "cliente") {
        throw new DomainException("El cliente seleccionado no existe o no está activo.");
    }
    $clienteNombre = trim(($clienteDoc["nombre"] ?? "Cliente") . " " . ($clienteDoc["apellido"] ?? ""));

    // 2. Pre-validar stock y datos de cada producto en el carrito
    $itemsValidados = [];
    $totalVentaGeneral = 0;
    $totalDescuentoGeneral = 0;
    $totalUnidadesGeneral = 0;

    foreach ($items as $idx => $item) {
        $numItem = $idx + 1;
        $prodId = (int) ($item["producto_id"] ?? 0);
        $cant = (int) ($item["cantidad"] ?? 0);
        $tipoVenta = trim($item["tipo_venta"] ?? "unidad");
        $descPorc = floatval($item["descuento_porcentaje"] ?? 0);

        if ($prodId <= 0 || $cant <= 0) {
            throw new DomainException("Ítem #{$numItem}: Producto o cantidad inválida.");
        }
        if (!in_array($tipoVenta, $tiposValidos, true)) {
            $tipoVenta = "unidad";
        }
        if ($descPorc < 0 || $descPorc > 100) {
            throw new DomainException("Ítem #{$numItem}: El porcentaje de descuento debe estar entre 0% y 100%.");
        }
        if ($rol === "vendedor" && $descPorc > ($limiteVendedor + 0.001)) {
            throw new DomainException("Ítem #{$numItem}: El descuento aplicado ({$descPorc}%) supera tu límite autorizado ({$limiteVendedor}%).");
        }

        $producto = $firestore->obtenerDocumento("productos", (string)$prodId);
        if (!$producto) {
            throw new DomainException("Ítem #{$numItem}: Producto no encontrado en la base de datos.");
        }

        $nombreProd = (string) ($producto["nombre"] ?? "Producto #{$prodId}");
        $presProducto = (string) ($producto["presentacion"] ?? "unidad");
        $permiteVentaUnidad = isset($producto["permite_venta_unidad"]) ? (bool)$producto["permite_venta_unidad"] : ($presProducto === "unidad");

        // Validar que no se intente vender por unidad si está deshabilitado
        if ($tipoVenta === "unidad" && $presProducto !== "unidad" && !$permiteVentaUnidad) {
            throw new DomainException("Ítem #{$numItem} ('{$nombreProd}'): No se permite la venta por unidad suelta. Debe venderse en su presentación empaquetada ({$presProducto}).");
        }

        // Validar que no se intente vender en una presentación empaquetada que no corresponde
        if ($tipoVenta !== "unidad" && $tipoVenta !== $presProducto) {
            throw new DomainException("Ítem #{$numItem} ('{$nombreProd}'): Presentación '{$tipoVenta}' no permitida. Este producto está configurado como '{$presProducto}'.");
        }

        $unidadesPorEmpaque = max(1, (int) ($producto["unidades_por_bulto"] ?? 1));
        $totalUnidades = ($tipoVenta === "caja" || $tipoVenta === "bulto") ? ($cant * $unidadesPorEmpaque) : $cant;
        $stockActual = (int) ($producto["stock"] ?? 0);

        if ($stockActual < $totalUnidades) {
            throw new DomainException("Stock insuficiente para '{$nombreProd}'. Disponible: {$stockActual} un. (solicitadas: {$totalUnidades} un.).");
        }

        $precioUnitario = (float) $producto["precio"];
        $subtotal = $precioUnitario * $totalUnidades;
        $descuentoMonto = round($subtotal * ($descPorc / 100), 2);
        $totalItem = max(0, $subtotal - $descuentoMonto);

        $totalVentaGeneral += $totalItem;
        $totalDescuentoGeneral += $descuentoMonto;
        $totalUnidadesGeneral += $totalUnidades;

        $itemsValidados[] = [
            "producto_id" => $prodId,
            "producto_nombre" => (string)$producto["nombre"],
            "tipo_venta" => $tipoVenta,
            "cantidad_empaque" => $cant,
            "unidades_por_bulto" => $unidadesPorEmpaque,
            "total_unidades" => $totalUnidades,
            "stock_actual" => $stockActual,
            "precio_unitario" => $precioUnitario,
            "subtotal" => $subtotal,
            "descuento_porcentaje" => $descPorc,
            "descuento_monto" => $descuentoMonto,
            "total" => $totalItem
        ];
    }

    $usuarioId = (int) $_SESSION["usuario_id"];
    $usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));
    $fechaActual = date("Y-m-d H:i:s");
    $ticketId = "TK-" . date("Ymd-His") . "-" . str_pad((string)random_int(100, 999), 3, "0", STR_PAD_LEFT);
    $ventasRegistradas = [];

    // 3. Ejecutar guardado de cada ítem del carrito
    foreach ($itemsValidados as $iv) {
        $ventaId = FirestoreConexion::obtenerSiguienteIdVenta();
        $prodId = $iv["producto_id"];
        $totalUnidades = $iv["total_unidades"];
        $nuevoStock = $iv["stock_actual"] - $totalUnidades;

        // Venta
        $ventaDoc = [
            "id" => $ventaId,
            "ticket_id" => $ticketId,
            "producto_id" => $prodId,
            "producto_nombre" => $iv["producto_nombre"],
            "tipo_venta" => $iv["tipo_venta"],
            "cantidad_empaque" => $iv["cantidad_empaque"],
            "cantidad" => $totalUnidades,
            "precio_unitario" => $iv["precio_unitario"],
            "subtotal" => $iv["subtotal"],
            "descuento_porcentaje" => $iv["descuento_porcentaje"],
            "descuento_monto" => $iv["descuento_monto"],
            "total" => $iv["total"],
            "usuario_id" => $usuarioId,
            "cliente_id" => $clienteId,
            "estado" => "ACTIVA",
            "fecha" => $fechaActual
        ];
        $firestore->guardarDocumento("ventas", (string)$ventaId, $ventaDoc);

        // Detalle
        $detalleDoc = [
            "id" => $ventaId,
            "venta_id" => $ventaId,
            "ticket_id" => $ticketId,
            "producto_id" => $prodId,
            "producto_nombre" => $iv["producto_nombre"],
            "tipo_venta" => $iv["tipo_venta"],
            "cantidad_empaque" => $iv["cantidad_empaque"],
            "cantidad" => $totalUnidades,
            "precio_unitario" => $iv["precio_unitario"],
            "subtotal" => $iv["subtotal"],
            "descuento_monto" => $iv["descuento_monto"],
            "total" => $iv["total"]
        ];
        $firestore->guardarDocumento("detalle_ventas", (string)$ventaId, $detalleDoc);

        // Descontar Stock
        $firestore->actualizarCampos("productos", (string)$prodId, ["stock" => $nuevoStock]);

        // Historial
        $histId = FirestoreConexion::obtenerSiguienteIdHistorial();
        $historialDoc = [
            "id" => $histId,
            "venta_id" => $ventaId,
            "ticket_id" => $ticketId,
            "usuario_id" => $usuarioId,
            "tipo" => "VENTA_CREADA",
            "cantidad_nueva" => $totalUnidades,
            "total_nuevo" => $iv["total"],
            "estado_anterior" => "NUEVA",
            "estado_nuevo" => "ACTIVA",
            "fecha" => $fechaActual
        ];
        $firestore->guardarDocumento("venta_historial", (string)$histId, $historialDoc);

        // Trazabilidad de movimientos
        FirestoreConexion::registrarMovimientoProducto(
            productoId: $prodId,
            tipo: "VENTA",
            descripcion: "Venta #{$ventaId} [{$ticketId}] a {$clienteNombre} ({$totalUnidades} un. por $" . number_format($iv["total"], 2) . ")",
            cantidadAnterior: $iv["stock_actual"],
            cantidadNueva: $nuevoStock,
            diferencia: -$totalUnidades,
            precioAnterior: $iv["precio_unitario"],
            precioNuevo: $iv["precio_unitario"],
            usuarioId: $usuarioId,
            usuarioNombre: $usuarioNombre
        );

        $ventasRegistradas[] = $ventaId;
    }

    responderJson([
        "success" => true,
        "ticket_id" => $ticketId,
        "total_items" => count($ventasRegistradas),
        "total_unidades" => $totalUnidadesGeneral,
        "descuento_total" => $totalDescuentoGeneral,
        "total" => $totalVentaGeneral,
        "ventas_ids" => $ventasRegistradas,
        "mensaje" => "Venta de " . count($ventasRegistradas) . " producto(s) registrada con éxito."
    ], 201);
} catch (DomainException $e) {
    responderJson(["error" => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log("Error al guardar venta en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar la venta: " . $e->getMessage()], 500);
}
?>
