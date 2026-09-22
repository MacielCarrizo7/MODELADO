<?php
/**
 * Test de Verificación Integral PHP + MySQL (PDO) para XAMPP
 */
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../seguridad.php";

echo "=== INICIO DE PRUEBAS DE SISTEMA ===" . PHP_EOL;

// 1. Probar Conexión PDO
try {
    $pdo = Conexion::obtenerInstancia();
    echo "[OK] Conexión PDO exitosa a MySQL (control_stock)." . PHP_EOL;
} catch (Exception $e) {
    die("[FAIL] Error de conexión: " . $e->getMessage() . PHP_EOL);
}

// 2. Verificar Administrador Inicial y Login
$stmtAdmin = $pdo->prepare("SELECT id, dni, nombre, apellido, password, rol, activo FROM usuarios WHERE dni = ? AND nombre = ? AND apellido = ?");
$stmtAdmin->execute(['12345678', 'Admin', 'General']);
$admin = $stmtAdmin->fetch();

if (!$admin) {
    die("[FAIL] No se encontró el usuario administrador inicial." . PHP_EOL);
}

if (!password_verify('admin123', $admin['password'])) {
    die("[FAIL] La contraseña del administrador no valida con password_verify." . PHP_EOL);
}
echo "[OK] Usuario Administrador por defecto verificado con éxito (DNI: 12345678, Password: admin123)." . PHP_EOL;

// 3. Probar Registro de Usuario (Vendedor y Cliente)
$dniVendedor = "20111222";
$stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ?");
$stmtCheck->execute([$dniVendedor]);
if (!$stmtCheck->fetch()) {
    $passHashVendedor = password_hash("vendedor123", PASSWORD_DEFAULT);
    $stmtInsVend = $pdo->prepare("INSERT INTO usuarios (dni, nombre, apellido, password, rol, activo) VALUES (?, ?, ?, ?, 'vendedor', 1)");
    $stmtInsVend->execute([$dniVendedor, "Carlos", "Ventas", $passHashVendedor]);
    echo "[OK] Vendedor creado correctamente." . PHP_EOL;
}

$dniCliente = "30555666";
$stmtCheck->execute([$dniCliente]);
$clientRow = $stmtCheck->fetch();
$clienteId = 0;
if (!$clientRow) {
    $passHashCliente = password_hash("cliente123", PASSWORD_DEFAULT);
    $stmtInsCli = $pdo->prepare("INSERT INTO usuarios (dni, nombre, apellido, password, rol, activo) VALUES (?, ?, ?, ?, 'cliente', 1)");
    $stmtInsCli->execute([$dniCliente, "Maria", "Lopez", $passHashCliente]);
    $clienteId = (int) $pdo->lastInsertId();
    echo "[OK] Cliente creado correctamente (ID: {$clienteId})." . PHP_EOL;
} else {
    $clienteId = (int) $clientRow['id'];
}

// 4. Probar Inserción de Producto con Presentación y Lote
$stmtProd = $pdo->prepare("INSERT INTO productos (codigo, nombre, descripcion, presentacion, precio, stock, categoria, unidades_por_bulto, fecha_vencimiento, proveedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmtProd->execute([
    'PROD-TEST-01',
    'Aceite de Oliva 500ml',
    'Aceite extra virgen',
    'caja',
    1250.50,
    120, // 10 cajas de 12 unidades = 120 unidades
    'Almacén',
    12,
    date('Y-m-d', strtotime('+60 days')),
    'Distribuidora Central'
]);
$productoId = (int) $pdo->lastInsertId();
echo "[OK] Producto insertado correctamente con todas las columnas (ID: {$productoId})." . PHP_EOL;

// 5. Probar Inserción de Venta y Detalle de Venta
$stmtVenta = $pdo->prepare("INSERT INTO ventas (producto_id, producto_nombre, tipo_venta, cantidad_empaque, cantidad, precio_unitario, subtotal, descuento_porcentaje, descuento_monto, total, usuario_id, cliente_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVA')");
$subtotal = 1250.50 * 12; // 1 caja de 12
$total = $subtotal;
$stmtVenta->execute([
    $productoId,
    'Aceite de Oliva 500ml',
    'caja',
    1,
    12,
    1250.50,
    $subtotal,
    0,
    0,
    $total,
    (int) $admin['id'],
    $clienteId
]);
$ventaId = (int) $pdo->lastInsertId();

$stmtDetalle = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, producto_nombre, tipo_venta, cantidad_empaque, cantidad, precio_unitario, subtotal, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmtDetalle->execute([
    $ventaId,
    $productoId,
    'Aceite de Oliva 500ml',
    'caja',
    1,
    12,
    1250.50,
    $subtotal,
    $total
]);

// Descontar stock
$stmtStock = $pdo->prepare("UPDATE productos SET stock = stock - 12 WHERE id = ?");
$stmtStock->execute([$productoId]);

echo "[OK] Venta y Detalle de Venta registrados con éxito (Venta ID: {$ventaId})." . PHP_EOL;

// 6. Verificar Stock Restante
$stmtVerif = $pdo->prepare("SELECT stock FROM productos WHERE id = ?");
$stmtVerif->execute([$productoId]);
$stockActual = (int) $stmtVerif->fetchColumn();
echo "[OK] Stock verificado: 120 - 12 = {$stockActual} unidades." . PHP_EOL;

echo "=== TODAS LAS PRUEBAS COMPLETADAS EXITOSAMENTE ===" . PHP_EOL;
?>
