<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$id = intval($_POST["id"] ?? 0);
$motivo = trim($_POST["motivo"] ?? ($_POST["motivo_baja"] ?? "Eliminación manual"));
$observaciones = trim($_POST["observaciones"] ?? "");

if ($id <= 0) {
    responderJson(["error" => "ID de producto inválido."], 400);
}

if ($motivo === "") {
    $motivo = "Eliminación manual";
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    
    // 1. Obtener datos actuales del producto
    $producto = $firestore->obtenerDocumento("productos", (string)$id);
    if (!$producto) {
        responderJson(["error" => "El producto no existe o ya fue dado de baja."], 404);
    }

    $stockRemanente = max(0, (int)($producto["stock"] ?? 0));
    $nombreProd = (string)($producto["nombre"] ?? "Producto #{$id}");
    $precioCosto = (float)($producto["precio_costo"] ?? 0);
    $precioVenta = (float)($producto["precio_venta"] ?? ($producto["precio"] ?? 0));

    // 2. Calcular histórico acumulativo de unidades vendidas
    $unidadesVendidas = 0;
    $totalVentasMonto = 0.0;

    $todasLasVentas = $firestore->obtenerColeccion("ventas");
    foreach ($todasLasVentas as $v) {
        if ((int)($v["producto_id"] ?? 0) === $id) {
            $estadoVenta = strtoupper((string)($v["estado"] ?? "ACTIVA"));
            if ($estadoVenta !== "CANCELADA") {
                $unidadesVendidas += (int)($v["cantidad"] ?? 0);
                $totalVentasMonto += (float)($v["total"] ?? 0);
            }
        }
    }

    $totalPerdidaCosto = round($stockRemanente * $precioCosto, 2);
    $usuarioId = isset($_SESSION["usuario_id"]) ? (int)$_SESSION["usuario_id"] : null;
    $usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Administrador") . " " . ($_SESSION["usuario_apellido"] ?? ""));
    $fechaActual = date("Y-m-d H:i:s");

    // 3. Registrar en colección histórica "bajas_inventario"
    $datosBaja = [
        "producto_id" => $id,
        "codigo" => $producto["codigo"] ?? null,
        "codigo_barras" => $producto["codigo_barras"] ?? null,
        "nombre" => $nombreProd,
        "descripcion" => $producto["descripcion"] ?? null,
        "categoria_id" => $producto["categoria_id"] ?? null,
        "categoria" => $producto["categoria_nombre"] ?? ($producto["categoria"] ?? null),
        "presentacion" => $producto["presentacion"] ?? "unidad",
        "unidades_por_bulto" => (int)($producto["unidades_por_bulto"] ?? 1),
        "fecha_vencimiento" => $producto["fecha_vencimiento"] ?? null,
        "proveedor" => $producto["proveedor"] ?? null,
        "precio_costo" => $precioCosto,
        "precio_venta" => $precioVenta,
        "stock_remanente" => $stockRemanente,
        "unidades_vendidas_historicas" => $unidadesVendidas,
        "total_ventas_historicas_monto" => $totalVentasMonto,
        "total_perdida_costo" => $totalPerdidaCosto,
        "motivo_baja" => $motivo,
        "observaciones" => $observaciones !== "" ? $observaciones : null,
        "usuario_id" => $usuarioId,
        "usuario_nombre" => $usuarioNombre,
        "fecha_baja" => $fechaActual
    ];

    $bajaId = FirestoreConexion::registrarBajaInventario($datosBaja);

    // 4. Remover el producto activo de la colección "productos"
    $firestore->eliminarDocumento("productos", (string)$id);

    // 5. Registrar trazabilidad en Kardex / Movimientos
    FirestoreConexion::registrarMovimientoProducto(
        productoId: $id,
        tipo: "BAJA",
        descripcion: "Baja de inventario: {$motivo}. Remanente: {$stockRemanente} un., Vendidas históricas: {$unidadesVendidas} un. (Reg. #{$bajaId})",
        cantidadAnterior: $stockRemanente,
        cantidadNueva: 0,
        diferencia: -$stockRemanente,
        precioAnterior: $precioVenta,
        precioNuevo: 0,
        usuarioId: $usuarioId,
        usuarioNombre: $usuarioNombre
    );

    responderJson([
        "success" => true,
        "baja_id" => $bajaId,
        "stock_remanente" => $stockRemanente,
        "unidades_vendidas_historicas" => $unidadesVendidas,
        "mensaje" => "Producto transferido al registro histórico de Bajas de Inventario (#{$bajaId})."
    ]);

} catch (Throwable $e) {
    error_log("Error al procesar baja de producto en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo procesar la baja del producto: " . $e->getMessage()], 500);
}
?>
