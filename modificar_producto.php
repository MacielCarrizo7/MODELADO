<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$pdo = Conexion::obtenerInstancia();

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;
$nombre = trim($_POST["nombre"] ?? "");
$precio = floatval($_POST["precio"] ?? 0);
$stock = filter_input(INPUT_POST, "stock", FILTER_VALIDATE_INT);
$presentacion = trim($_POST["presentacion"] ?? "unidad");
$unidadesPorBulto = intval($_POST["unidades_por_bulto"] ?? 1);
$fechaVencimiento = trim($_POST["fecha_vencimiento"] ?? "");
$proveedor = trim($_POST["proveedor"] ?? "");
$motivo = trim($_POST["motivo"] ?? "Modificación / corrección de producto");

$presentacionesValidas = ["unidad", "caja", "bulto"];

if ($id <= 0) {
    responderJson(["error" => "ID de producto inválido."], 400);
}
if ($nombre === "" || mb_strlen($nombre) > 150) {
    responderJson(["error" => "Ingresá un nombre de producto válido (máx. 150 caracteres)."], 400);
}
if ($precio <= 0) {
    responderJson(["error" => "El precio debe ser mayor a 0."], 400);
}
if ($stock === null || $stock < 0) {
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

if (mb_strlen($proveedor) > 150) {
    responderJson(["error" => "El nombre del proveedor supera los 150 caracteres."], 400);
}
$proveedorParam = $proveedor !== "" ? $proveedor : null;
$usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;

try {
    $pdo->beginTransaction();

    $stmtBuscar = $pdo->prepare("SELECT id, nombre, precio, stock, presentacion, unidades_por_bulto, fecha_vencimiento, proveedor FROM productos WHERE id = ? FOR UPDATE");
    $stmtBuscar->execute([$id]);
    $productoActual = $stmtBuscar->fetch();

    if (!$productoActual) {
        $pdo->rollBack();
        responderJson(["error" => "El producto no existe."], 404);
    }

    $stockAnterior = (int) $productoActual["stock"];
    $diferenciaStock = $stock - $stockAnterior;

    $stmtUpdate = $pdo->prepare(
        "UPDATE productos
         SET nombre = ?, precio = ?, stock = ?, presentacion = ?, unidades_por_bulto = ?, fecha_vencimiento = ?, proveedor = ?
         WHERE id = ?"
    );
    $stmtUpdate->execute([$nombre, $precio, $stock, $presentacion, $unidadesPorBulto, $vencimientoParam, $proveedorParam, $id]);

    // Si hubo incremento de stock, registrar en Kardex de ingresos
    if ($diferenciaStock > 0) {
        $cantidadBultosIngresados = ($unidadesPorBulto > 1) ? intdiv($diferenciaStock, $unidadesPorBulto) : $diferenciaStock;
        if ($cantidadBultosIngresados === 0) {
            $cantidadBultosIngresados = 1;
        }

        $stmtIngreso = $pdo->prepare(
            "INSERT INTO ingresos_stock
             (producto_id, producto_nombre, cantidad, presentacion, unidades_por_bulto, total_unidades, precio_unitario, proveedor, fecha_vencimiento, usuario_id, motivo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $motivoIngreso = "Ajuste de inventario (+" . $diferenciaStock . " un.): " . $motivo;
        $stmtIngreso->execute([
            $id,
            $nombre,
            $cantidadBultosIngresados,
            $presentacion,
            $unidadesPorBulto,
            $diferenciaStock,
            $precio,
            $proveedorParam,
            $vencimientoParam,
            $usuarioId,
            $motivoIngreso
        ]);
    }

    $pdo->commit();
    responderJson(["success" => true, "mensaje" => "Producto modificado correctamente."]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al modificar producto: " . $e->getMessage());
    responderJson(["error" => "No se pudo modificar el producto: " . $e->getMessage()], 500);
}
?>
