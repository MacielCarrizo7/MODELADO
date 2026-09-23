<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$codigo = trim($_POST["codigo"] ?? "");
$codigoBarras = trim($_POST["codigo_barras"] ?? "");
$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");

// Precios de costo y venta
$precioCosto = floatval($_POST["precio_costo"] ?? 0);
$precioVenta = floatval($_POST["precio_venta"] ?? ($_POST["precio"] ?? 0));
$precio = $precioVenta; // Compatibilidad

$stock = intval($_POST["stock"] ?? 0);
$presentacion = trim($_POST["presentacion"] ?? "unidad");

// Categoría
$categoriaId = isset($_POST["categoria_id"]) && $_POST["categoria_id"] !== "" ? (int)$_POST["categoria_id"] : null;
$categoriaNombre = trim($_POST["categoria_nombre"] ?? ($_POST["categoria"] ?? ""));

$unidadesPorBulto = intval($_POST["unidades_por_bulto"] ?? 1);
$fechaVencimiento = trim($_POST["fecha_vencimiento"] ?? "");
$proveedor = trim($_POST["proveedor"] ?? "");

$presentacionesValidas = ["unidad", "caja", "bulto"];

if ($nombre === "" || mb_strlen($nombre) > 150) {
    responderJson(["error" => "Ingresá un nombre de producto válido (máx. 150 caracteres)."], 400);
}
if ($precioVenta <= 0) {
    responderJson(["error" => "El precio de venta debe ser mayor a 0."], 400);
}
if ($precioCosto < 0) {
    responderJson(["error" => "El precio de costo no puede ser negativo."], 400);
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

if (mb_strlen($proveedor) > 150) {
    responderJson(["error" => "El nombre del proveedor supera los 150 caracteres."], 400);
}
$proveedorParam = $proveedor !== "" ? $proveedor : null;

// Procesar imagen (archivo o URL)
$imagenUrl = trim($_POST["imagen_url"] ?? "");

if (isset($_FILES["imagen_archivo"]) && $_FILES["imagen_archivo"]["error"] === UPLOAD_ERR_OK) {
    $file = $_FILES["imagen_archivo"];
    $allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (in_array($mimeType, $allowedTypes, true) && $file["size"] <= 5 * 1024 * 1024) {
        $ext = match ($mimeType) {
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp",
            "image/gif" => "gif",
            default => "jpg"
        };
        $dirUploads = __DIR__ . "/uploads/productos/";
        if (!is_dir($dirUploads)) {
            @mkdir($dirUploads, 0755, true);
        }
        $nombreArchivo = "prod_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $destino = $dirUploads . $nombreArchivo;
        if (move_uploaded_file($file["tmp_name"], $destino)) {
            $imagenUrl = "uploads/productos/" . $nombreArchivo;
        }
    }
}

$imagenUrlParam = $imagenUrl !== "" ? $imagenUrl : null;

$sinFactura = isset($_POST["sin_factura"]) && ($_POST["sin_factura"] === "1" || $_POST["sin_factura"] === "true" || $_POST["sin_factura"] === "on");
$numeroFactura = trim($_POST["numero_factura"] ?? "");
$numeroFacturaFinal = ($sinFactura || $numeroFactura === "") ? ($sinFactura ? "Sin Factura" : null) : $numeroFactura;

$totalUnidades = $stock * $unidadesPorBulto;
$usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;
$usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Admin") . " " . ($_SESSION["usuario_apellido"] ?? ""));

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    // Si se especificó categoría_id y no se pasó nombre, buscarlo
    if ($categoriaId !== null && $categoriaNombre === "") {
        $catDoc = $firestore->obtenerDocumento("categorias", (string)$categoriaId);
        if ($catDoc) {
            $categoriaNombre = $catDoc["nombre"] ?? "";
        }
    }

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
        "precio" => $precioVenta,
        "precio_venta" => $precioVenta,
        "precio_costo" => $precioCosto,
        "stock" => $totalUnidades,
        "categoria_id" => $categoriaId,
        "categoria" => $categoriaNombre !== "" ? $categoriaNombre : null,
        "categoria_nombre" => $categoriaNombre !== "" ? $categoriaNombre : null,
        "imagen_url" => $imagenUrlParam,
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
            "precio_unitario" => $precioVenta,
            "precio_costo" => $precioCosto,
            "precio_venta" => $precioVenta,
            "proveedor" => $proveedorParam,
            "fecha_vencimiento" => $vencimientoParam,
            "numero_factura" => $numeroFacturaFinal,
            "sin_factura" => $sinFactura,
            "usuario_id" => $usuarioId,
            "motivo" => "Alta inicial de producto" . ($numeroFacturaFinal ? " (Factura: {$numeroFacturaFinal})" : ""),
            "fecha" => date("Y-m-d H:i:s")
        ];
        $firestore->guardarDocumento("ingresos_stock", (string)$ingresoId, $ingresoDatos);
    }

    // Registrar en trazabilidad de movimientos de producto
    FirestoreConexion::registrarMovimientoProducto(
        productoId: $productoId,
        tipo: "ALTA_INICIAL",
        descripcion: "Alta inicial del producto con stock de {$totalUnidades} un. Costo: $" . number_format($precioCosto, 2) . " | Venta: $" . number_format($precioVenta, 2),
        cantidadAnterior: 0,
        cantidadNueva: $totalUnidades,
        diferencia: $totalUnidades,
        precioAnterior: null,
        precioNuevo: $precioVenta,
        usuarioId: $usuarioId,
        usuarioNombre: $usuarioNombre
    );

    responderJson([
        "success" => true,
        "id" => $productoId,
        "nombre" => $nombre,
        "categoria" => $categoriaNombre,
        "precio_costo" => $precioCosto,
        "precio_venta" => $precioVenta,
        "imagen_url" => $imagenUrlParam,
        "mensaje" => "Producto registrado exitosamente."
    ], 201);
} catch (Throwable $e) {
    error_log("Error al guardar producto: " . $e->getMessage());
    responderJson(["error" => "No se pudo guardar el producto: " . $e->getMessage()], 500);
}
?>
