<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();

$codigo = trim($_POST["codigo"] ?? "");
$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");
$precio = floatval($_POST["precio"] ?? 0);
$stock = intval($_POST["stock"] ?? 0);
$presentacion = trim($_POST["presentacion"] ?? "unidad");
$categoria = trim($_POST["categoria"] ?? "");
$unidadesPorBulto = intval($_POST["unidades_por_bulto"] ?? 1);
$fechaVencimiento = trim($_POST["fecha_vencimiento"] ?? "");
$proveedor = trim($_POST["proveedor"] ?? "");

$presentacionesValidas = ["unidad", "caja", "bulto"];

if ($nombre === "" || mb_strlen($nombre) > 150) {
    responderJson(["error" => "Ingresá un nombre de producto válido (máx. 150 caracteres)."], 400);
}
if ($precio <= 0) {
    responderJson(["error" => "El precio debe ser mayor a 0."], 400);
}
if ($stock < 0) {
    responderJson(["error" => "El stock no puede ser negativo."], 400);
}
if (!in_array($presentacion, $presentacionesValidas, true)) {
    responderJson(["error" => "La presentación seleccionada no es válida."], 400);
}

if ($presentacion === "unidad") {
    $unidadesPorBulto = 1;
} else {
    if ($unidadesPorBulto <= 0) {
        responderJson(["error" => "Debés indicar cuántas unidades contiene cada " . ($presentacion === "caja" ? "caja" : "bulto") . "."], 400);
    }
}

if ($fechaVencimiento !== "") {
    if (!fechaIsoValida($fechaVencimiento)) {
        responderJson(["error" => "La fecha de vencimiento ingresada no es válida."], 400);
    }
    $vencimientoParam = $fechaVencimiento;
} else {
    $vencimientoParam = null;
}

$codigoParam = $codigo !== "" ? $codigo : null;
$descripcionParam = $descripcion !== "" ? $descripcion : null;
$categoriaParam = $categoria !== "" ? $categoria : null;

if (mb_strlen($proveedor) > 150) {
    responderJson(["error" => "El nombre del proveedor supera los 150 caracteres."], 400);
}
$proveedorParam = $proveedor !== "" ? $proveedor : null;

$totalUnidades = $stock * $unidadesPorBulto;
$usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO productos (codigo, nombre, descripcion, presentacion, precio, stock, categoria, unidades_por_bulto, fecha_vencimiento, proveedor)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $codigoParam,
        $nombre,
        $descripcionParam,
        $presentacion,
        $precio,
        $totalUnidades,
        $categoriaParam,
        $unidadesPorBulto,
        $vencimientoParam,
        $proveedorParam
    ]);
    $productoId = (int) $pdo->lastInsertId();

    if ($totalUnidades > 0) {
        $stmtIngreso = $pdo->prepare(
            "INSERT INTO ingresos_stock
             (producto_id, producto_nombre, cantidad, presentacion, unidades_por_bulto, total_unidades, precio_unitario, proveedor, fecha_vencimiento, usuario_id, motivo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Alta inicial de producto')"
        );
        $stmtIngreso->execute([
            $productoId,
            $nombre,
            $stock,
            $presentacion,
            $unidadesPorBulto,
            $totalUnidades,
            $precio,
            $proveedorParam,
            $vencimientoParam,
            $usuarioId
        ]);
    }

    $pdo->commit();
    responderJson(["success" => true, "id" => $productoId, "mensaje" => "Producto registrado exitosamente."], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al guardar producto: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar el producto: " . $e->getMessage()], 500);
}
?>
