<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$codigo = trim($_POST["codigo"] ?? "");
$codigoBarras = trim($_POST["codigo_barras"] ?? "");
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
$codigoBarrasParam = $codigoBarras !== "" ? $codigoBarras : null;
$descripcionParam = $descripcion !== "" ? $descripcion : null;
$categoriaParam = $categoria !== "" ? $categoria : null;

if (mb_strlen($proveedor) > 150) {
    responderJson(["error" => "El nombre del proveedor supera los 150 caracteres."], 400);
}
$proveedorParam = $proveedor !== "" ? $proveedor : null;

$totalUnidades = $stock * $unidadesPorBulto;
$usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;
$usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Admin") . " " . ($_SESSION["usuario_apellido"] ?? ""));

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Si se especificó código de barras, verificar que no esté duplicado
    if ($codigoBarrasParam !== null) {
        $existentesCb = $firestore->consultar("productos", [
            ["codigo_barras", "==", $codigoBarrasParam]
        ]);
        if (!empty($existentesCb)) {
            responderJson(["error" => "Ya existe otro producto con el mismo código de barras."], 409);
        }
    }

    $productoId = FirestoreConexion::obtenerSiguienteIdProducto();

    $productoDatos = [
        "id" => $productoId,
        "codigo" => $codigoParam,
        "codigo_barras" => $codigoBarrasParam,
        "nombre" => $nombre,
        "descripcion" => $descripcionParam,
        "presentacion" => $presentacion,
        "precio" => $precio,
        "stock" => $totalUnidades,
        "categoria" => $categoriaParam,
        "unidades_por_bulto" => $unidadesPorBulto,
        "fecha_vencimiento" => $vencimientoParam,
        "proveedor" => $proveedorParam,
        "creado_el" => date("Y-m-d H:i:s")
    ];

    $firestore->guardarDocumento("productos", (string)$productoId, $productoDatos);

    // Registrar en Kardex de ingresos si stock > 0
    if ($totalUnidades > 0) {
        $ingresoId = $firestore->obtenerSiguienteId("contadores", "ingresos", "ultimo_id");
        $ingresoDatos = [
            "id" => $ingresoId,
            "producto_id" => $productoId,
            "producto_nombre" => $nombre,
            "cantidad" => $stock,
            "presentacion" => $presentacion,
            "unidades_por_bulto" => $unidadesPorBulto,
            "total_unidades" => $totalUnidades,
            "precio_unitario" => $precio,
            "proveedor" => $proveedorParam,
            "fecha_vencimiento" => $vencimientoParam,
            "usuario_id" => $usuarioId,
            "motivo" => "Alta inicial de producto",
            "fecha" => date("Y-m-d H:i:s")
        ];
        $firestore->guardarDocumento("ingresos_stock", (string)$ingresoId, $ingresoDatos);
    }

    // Registrar en trazabilidad de movimientos de producto
    FirestoreConexion::registrarMovimientoProducto(
        productoId: $productoId,
        tipo: "ALTA_INICIAL",
        descripcion: "Alta inicial del producto con stock de {$totalUnidades} un. a $" . number_format($precio, 2),
        cantidadAnterior: 0,
        cantidadNueva: $totalUnidades,
        diferencia: $totalUnidades,
        precioAnterior: null,
        precioNuevo: $precio,
        usuarioId: $usuarioId,
        usuarioNombre: $usuarioNombre
    );

    responderJson([
        "success" => true,
        "id" => $productoId,
        "codigo_barras" => $codigoBarrasParam,
        "mensaje" => "Producto registrado exitosamente."
    ], 201);
} catch (Throwable $e) {
    error_log("Error al guardar producto en Firestore: " . $e->getMessage());
    responderJson(["error" => "No se pudo registrar el producto: " . $e->getMessage()], 500);
}
?>
