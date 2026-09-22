<?php

declare(strict_types=1);

require_once __DIR__ . "/../conexion.php";

$conexion = Conexion::obtenerInstancia();

function afirmar(bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new RuntimeException("ERROR: " . $mensaje);
    }
    echo "  [OK] {$mensaje}\n";
}

echo "=== INICIANDO PRUEBAS V2: EMPAQUES, DESCUENTOS, FIFO 45/90, PROVEEDORES Y SOLICITUDES ===\n\n";

// Limpiar datos de prueba previos
$conexion->query("DELETE FROM ventas WHERE producto_nombre LIKE 'TEST_V2_%'");
$conexion->query("DELETE FROM productos WHERE nombre LIKE 'TEST_V2_%'");
$conexion->query("DELETE FROM solicitudes_vendedor WHERE mensaje LIKE 'TEST_SOLICITUD_%'");

// 1. Crear producto con caja (12 un.) y precio $1000
echo "1. Creando producto de prueba (10 cajas de 12 un. = 120 un., precio unitario $1000)...\n";
$nombreProd = "TEST_V2_GALLETITAS";
$precioUnit = 1000.00;
$stockCajas = 10;
$unidadesPorBulto = 12;
$totalUnidades = 120;
$vencimiento = date("Y-m-d", strtotime("+30 days")); // Vence en 30 días -> Rojo (≤ 45 días)
$proveedor = "Arcor Alimentos";

$stmtP = $conexion->prepare(
    "INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor)
     VALUES (?, ?, ?, 'caja', ?, ?, ?)"
);
$stmtP->bind_param("sdiiss", $nombreProd, $precioUnit, $totalUnidades, $unidadesPorBulto, $vencimiento, $proveedor);
$stmtP->execute();
$prodId = $conexion->insert_id;
afirmar($prodId > 0, "Producto creado con ID {$prodId}");

// 2. Registrar venta de 2 cajas (24 unidades) con 15% de descuento
echo "\n2. Registrando venta de 2 cajas (24 unidades) con 15% de descuento...\n";
$clienteId = 1;
$vendedorId = 4; // Admin
$cantidadCajas = 2;
$unidadesVendidas = $cantidadCajas * $unidadesPorBulto; // 24
$subtotalEsperado = $unidadesVendidas * $precioUnit; // $24.000
$descuentoPct = 15.0;
$descuentoMontoEsperado = round($subtotalEsperado * ($descuentoPct / 100), 2); // $3.600
$totalEsperado = $subtotalEsperado - $descuentoMontoEsperado; // $20.400

$stmtV = $conexion->prepare(
    "INSERT INTO ventas
     (producto_id, producto_nombre, tipo_venta, cantidad_empaque, cantidad, precio_unitario, subtotal, descuento_porcentaje, descuento_monto, total, usuario_id, cliente_id, estado)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVA')"
);
$tipoVentaTest = "caja";
$stmtV->bind_param(
    "issiidddddii",
    $prodId,
    $nombreProd,
    $tipoVentaTest,
    $cantidadCajas,
    $unidadesVendidas,
    $precioUnit,
    $subtotalEsperado,
    $descuentoPct,
    $descuentoMontoEsperado,
    $totalEsperado,
    $vendedorId,
    $clienteId
);
$stmtV->execute();
$ventaId = $conexion->insert_id;

$conexion->query("UPDATE productos SET stock = stock - {$unidadesVendidas} WHERE id = {$prodId}");

afirmar($ventaId > 0, "Venta registrada con ID {$ventaId}");

// Verificar datos de venta en BD
$ventaRow = $conexion->query("SELECT * FROM ventas WHERE id = {$ventaId}")->fetch_assoc();
afirmar($ventaRow["tipo_venta"] === "caja", "Tipo de venta guardado como 'caja'");
afirmar((int)$ventaRow["cantidad_empaque"] === 2, "Cantidad de empaques guardada es 2 cajas");
afirmar((int)$ventaRow["cantidad"] === 24, "Total de unidades descontadas es 24");
afirmar((float)$ventaRow["subtotal"] === 24000.00, "Subtotal es $24.000");
afirmar((float)$ventaRow["descuento_porcentaje"] === 15.00, "Descuento es 15%");
afirmar((float)$ventaRow["descuento_monto"] === 3600.00, "Monto de descuento es $3.600");
afirmar((float)$ventaRow["total"] === 20400.00, "Total final a cobrar es $20.400");

$stockRestante = (int)$conexion->query("SELECT stock FROM productos WHERE id = {$prodId}")->fetch_assoc()["stock"];
afirmar($stockRestante === 96, "Stock restante en base de datos es 96 unidades (120 - 24)");

// 3. Probar filtro por cliente en ventas
echo "\n3. Probando consulta de ventas filtrando por cliente_id...\n";
$stmtFiltroCliente = $conexion->prepare("SELECT id, cliente_id FROM ventas WHERE cliente_id = ? AND id = ?");
$stmtFiltroCliente->bind_param("ii", $clienteId, $ventaId);
$stmtFiltroCliente->execute();
$ventaFiltrada = $stmtFiltroCliente->get_result()->fetch_assoc();
afirmar($ventaFiltrada !== null, "Filtro por cliente_id encontró la venta correctamente");

// 4. Probar nuevo semáforo FIFO (≤ 45 días = Rojo, 46-90 = Amarillo, >90 = Verde)
echo "\n4. Probando lógica de semáforo FIFO (45 y 90 días)...\n";
function testSemaforoDias(int $dias): string {
    if ($dias <= 45) return "rojo";
    if ($dias <= 90) return "amarillo";
    return "verde";
}
afirmar(testSemaforoDias(10) === "rojo", "10 días restantes -> Semáforo ROJO (≤ 45 días)");
afirmar(testSemaforoDias(45) === "rojo", "45 días restantes -> Semáforo ROJO (≤ 45 días)");
afirmar(testSemaforoDias(46) === "amarillo", "46 días restantes -> Semáforo AMARILLO (46 a 90 días)");
afirmar(testSemaforoDias(90) === "amarillo", "90 días restantes -> Semáforo AMARILLO (46 a 90 días)");
afirmar(testSemaforoDias(91) === "verde", "91 días restantes -> Semáforo VERDE (> 90 días)");

// 5. Probar Directorio de Proveedores
echo "\n5. Probando obtención de Directorio de Proveedores...\n";
$provQuery = $conexion->query(
    "SELECT p.nombre_proveedor, COALESCE(prod.total_productos, 0) AS total_productos
     FROM (SELECT DISTINCT proveedor AS nombre_proveedor FROM productos WHERE proveedor IS NOT NULL AND proveedor != '') p
     LEFT JOIN (SELECT proveedor, COUNT(*) AS total_productos FROM productos GROUP BY proveedor) prod
     ON p.nombre_proveedor = prod.proveedor
     WHERE p.nombre_proveedor = 'Arcor Alimentos'"
)->fetch_assoc();

afirmar($provQuery !== null, "Proveedor 'Arcor Alimentos' encontrado en el directorio");
afirmar((int)$provQuery["total_productos"] >= 1, "Proveedor tiene productos asociados en catálogo");

// 6. Probar Solicitud de Vendedor por Cliente y Atención
echo "\n6. Probando flujo de solicitud de vendedor por cliente...\n";
$mensajePrueba = "TEST_SOLICITUD_CONSULTA_PRECIOS";
$stmtSol = $conexion->prepare("INSERT INTO solicitudes_vendedor (cliente_id, mensaje, estado) VALUES (?, ?, 'PENDIENTE')");
$stmtSol->bind_param("is", $clienteId, $mensajePrueba);
$stmtSol->execute();
$solId = $conexion->insert_id;
afirmar($solId > 0, "Solicitud de atención creada con ID {$solId}");

$solPendiente = $conexion->query("SELECT * FROM solicitudes_vendedor WHERE id = {$solId}")->fetch_assoc();
afirmar($solPendiente["estado"] === "PENDIENTE", "Estado inicial es PENDIENTE");

// Atender solicitud
$stmtAtender = $conexion->prepare("UPDATE solicitudes_vendedor SET estado = 'ATENDIDA', fecha_atencion = NOW(), atendido_por = ? WHERE id = ?");
$stmtAtender->bind_param("ii", $vendedorId, $solId);
$stmtAtender->execute();

$solAtendida = $conexion->query("SELECT * FROM solicitudes_vendedor WHERE id = {$solId}")->fetch_assoc();
afirmar($solAtendida["estado"] === "ATENDIDA", "Estado actualizado a ATENDIDA");
afirmar((int)$solAtendida["atendido_por"] === $vendedorId, "Atendido por asignado al vendedor ID {$vendedorId}");

// Limpieza de datos de prueba
$conexion->query("DELETE FROM ventas WHERE producto_nombre LIKE 'TEST_V2_%'");
$conexion->query("DELETE FROM productos WHERE nombre LIKE 'TEST_V2_%'");
$conexion->query("DELETE FROM solicitudes_vendedor WHERE mensaje LIKE 'TEST_SOLICITUD_%'");

echo "\n=== TODAS LAS PRUEBAS V2 FINALIZARON EXITOSAMENTE ===\n";
