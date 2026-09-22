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

echo "=== INICIANDO PRUEBAS DE NUEVAS FUNCIONALIDADES ===\n\n";

// 1. Limpieza de datos de prueba previos si existieran
$conexion->query("DELETE FROM productos WHERE nombre LIKE 'TEST_PROD_%'");
$conexion->query("DELETE FROM ingresos_stock WHERE producto_nombre LIKE 'TEST_PROD_%'");

// 2. Probar inserción con presentación 'caja', unidades_por_bulto, vencimiento y proveedor
echo "1. Creando producto de prueba con presentación 'caja' (10 cajas x 12 unidades = 120 unidades)...\n";
$nombre1 = "TEST_PROD_CAJA_1";
$precio1 = 1500.50;
$stockCajas1 = 10;
$unidadesPorBulto1 = 12;
$totalEsperado1 = 120;
$vencimiento1 = date("Y-m-d", strtotime("+45 days"));
$proveedor1 = "Distribuidora Mayorista Central";
$presentacion1 = "caja";

// Simulamos lo que hace guardar_producto.php
$conexion->begin_transaction();
$stmt1 = $conexion->prepare(
    "INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt1->bind_param("sdisiss", $nombre1, $precio1, $totalEsperado1, $presentacion1, $unidadesPorBulto1, $vencimiento1, $proveedor1);
$stmt1->execute();
$prodId1 = $conexion->insert_id;

$stmtIngreso1 = $conexion->prepare(
    "INSERT INTO ingresos_stock
     (producto_id, producto_nombre, cantidad, presentacion, unidades_por_bulto, total_unidades, precio_unitario, proveedor, fecha_vencimiento, usuario_id, motivo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'Alta inicial de producto')"
);
$stmtIngreso1->bind_param("isisidsss", $prodId1, $nombre1, $stockCajas1, $presentacion1, $unidadesPorBulto1, $totalEsperado1, $precio1, $proveedor1, $vencimiento1);
$stmtIngreso1->execute();
$conexion->commit();

afirmar($prodId1 > 0, "Producto con caja creado con ID {$prodId1}");

// Verificar registro en tabla productos
$checkProd1 = $conexion->query("SELECT * FROM productos WHERE id = {$prodId1}")->fetch_assoc();
afirmar((int)$checkProd1["stock"] === 120, "Stock total en unidades es 120");
afirmar($checkProd1["presentacion"] === "caja", "Presentación es 'caja'");
afirmar((int)$checkProd1["unidades_por_bulto"] === 12, "Unidades por bulto es 12");
afirmar($checkProd1["proveedor"] === $proveedor1, "Proveedor asignado correctamente");
afirmar($checkProd1["fecha_vencimiento"] === $vencimiento1, "Fecha de vencimiento guardada");

// Verificar registro en kardex ingresos_stock
$checkIng1 = $conexion->query("SELECT * FROM ingresos_stock WHERE producto_id = {$prodId1}")->fetch_assoc();
afirmar($checkIng1 !== null, "Kardex de ingreso registrado para el producto");
afirmar((int)$checkIng1["cantidad"] === 10, "Cantidad de paquetes en Kardex es 10");
afirmar((int)$checkIng1["total_unidades"] === 120, "Total de unidades en Kardex es 120");
afirmar($checkIng1["proveedor"] === $proveedor1, "Proveedor en Kardex coincide");

// 3. Probar inserción con producto que vence pronto para FIFO
echo "\n2. Creando producto de prueba con vencimiento próximo para rotación FIFO...\n";
$nombre2 = "TEST_PROD_VENCE_PRONTO";
$precio2 = 800.00;
$stock2 = 50;
$unidadesPorBulto2 = 1;
$vencimiento2 = date("Y-m-d", strtotime("+5 days"));
$proveedor2 = "Lácteos del Sur";
$presentacion2 = "unidad";

$conexion->begin_transaction();
$stmt2 = $conexion->prepare(
    "INSERT INTO productos (nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt2->bind_param("sdisiss", $nombre2, $precio2, $stock2, $presentacion2, $unidadesPorBulto2, $vencimiento2, $proveedor2);
$stmt2->execute();
$prodId2 = $conexion->insert_id;
$conexion->commit();

afirmar($prodId2 > 0, "Producto creado con ID {$prodId2}");

// 4. Probar ordenamiento FIFO en obtener_productos.php
echo "\n3. Probando ordenamiento FIFO (productos próximos a vencer primero)...\n";
$resProd = $conexion->query(
    "SELECT id, nombre, fecha_vencimiento FROM productos
     WHERE nombre LIKE 'TEST_PROD_%'
     ORDER BY CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC, nombre ASC"
)->fetch_all(MYSQLI_ASSOC);

afirmar(count($resProd) === 2, "Se obtuvieron los 2 productos de prueba");
afirmar($resProd[0]["nombre"] === $nombre2, "El producto que vence antes ({$nombre2}) aparece en primer lugar (FIFO)");
afirmar($resProd[1]["nombre"] === $nombre1, "El producto con vencimiento posterior ({$nombre1}) aparece después");

// 5. Probar edición de producto
echo "\n4. Probando edición de producto y ajuste de stock...\n";
$nuevoNombre1 = "TEST_PROD_CAJA_1_EDITADO";
$nuevoPrecio1 = 1650.00;
$nuevoStock1 = 144; // Aumenta de 120 a 144 (+24 unidades)
$diferencia = $nuevoStock1 - (int)$checkProd1["stock"]; // +24
$cantBultosAjuste = intdiv($diferencia, $unidadesPorBulto1); // 2 cajas
$motivoAjuste = "Ajuste de inventario (+24 un.): Reposición";
$usuarioIdTest = 1;

$conexion->begin_transaction();
$stmtUpdate = $conexion->prepare(
    "UPDATE productos
     SET nombre = ?, precio = ?, stock = ?, presentacion = ?, unidades_por_bulto = ?, fecha_vencimiento = ?, proveedor = ?
     WHERE id = ?"
);
$stmtUpdate->bind_param("sdisissi", $nuevoNombre1, $nuevoPrecio1, $nuevoStock1, $presentacion1, $unidadesPorBulto1, $vencimiento1, $proveedor1, $prodId1);
$stmtUpdate->execute();

if ($diferencia > 0) {
    $stmtIngresoAjuste = $conexion->prepare(
        "INSERT INTO ingresos_stock
         (producto_id, producto_nombre, cantidad, presentacion, unidades_por_bulto, total_unidades, precio_unitario, proveedor, fecha_vencimiento, usuario_id, motivo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtIngresoAjuste->bind_param("isisidsssis", $prodId1, $nuevoNombre1, $cantBultosAjuste, $presentacion1, $unidadesPorBulto1, $diferencia, $nuevoPrecio1, $proveedor1, $vencimiento1, $usuarioIdTest, $motivoAjuste);
    $stmtIngresoAjuste->execute();
}
$conexion->commit();

$checkEditado = $conexion->query("SELECT * FROM productos WHERE id = {$prodId1}")->fetch_assoc();
afirmar($checkEditado["nombre"] === $nuevoNombre1, "Nombre actualizado correctamente");
afirmar((float)$checkEditado["precio"] === 1650.00, "Precio actualizado a 1650.00");
afirmar((int)$checkEditado["stock"] === 144, "Stock actualizado a 144 unidades");

$totalIngresosProd1 = (int)$conexion->query("SELECT COUNT(*) AS c FROM ingresos_stock WHERE producto_id = {$prodId1}")->fetch_assoc()["c"];
afirmar($totalIngresosProd1 === 2, "Se registraron 2 movimientos en el Kardex (alta inicial y ajuste)");

// 6. Probar filtros de Kardex de ingresos
echo "\n5. Probando filtros del Kardex de ingresos...\n";
$stmtFiltroProveedor = $conexion->prepare("SELECT * FROM ingresos_stock WHERE proveedor LIKE ?");
$paramProv = "%Mayorista%";
$stmtFiltroProveedor->bind_param("s", $paramProv);
$stmtFiltroProveedor->execute();
$ingresosFiltrados = $stmtFiltroProveedor->get_result()->fetch_all(MYSQLI_ASSOC);
afirmar(count($ingresosFiltrados) >= 2, "Filtro por proveedor 'Mayorista' devolvió los registros correspondientes");

// 7. Probar consulta de vendedores para el selector
echo "\n6. Probando obtención de vendedores para los filtros...\n";
$vendedores = $conexion->query("SELECT id, nombre, rol FROM usuarios WHERE rol IN ('vendedor', 'admin')")->fetch_all(MYSQLI_ASSOC);
afirmar(count($vendedores) > 0, "Se listaron los vendedores/administradores para el selector");

// Limpieza de datos de prueba
$conexion->query("DELETE FROM productos WHERE nombre LIKE 'TEST_PROD_%'");
$conexion->query("DELETE FROM ingresos_stock WHERE producto_nombre LIKE 'TEST_PROD_%'");

echo "\n=== TODAS LAS PRUEBAS FINALIZARON EXITOSAMENTE ===\n";
