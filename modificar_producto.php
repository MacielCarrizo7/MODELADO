<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;
$codigoBarras = trim($_POST["codigo_barras"] ?? "");
$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");

// Precios de costo y venta
$precioCosto = isset($_POST["precio_costo"]) ? floatval($_POST["precio_costo"]) : null;
$precioVenta = floatval($_POST["precio_venta"] ?? ($_POST["precio"] ?? 0));
$precio = $precioVenta;

$stock = filter_input(INPUT_POST, "stock", FILTER_VALIDATE_INT);
$presentacion = trim($_POST["presentacion"] ?? "unidad");

// Categoría
$categoriaId = isset($_POST["categoria_id"]) && $_POST["categoria_id"] !== "" ? (int)$_POST["categoria_id"] : null;
$categoriaNombre = trim($_POST["categoria_nombre"] ?? ($_POST["categoria"] ?? ""));

$unidadesPorBulto = intval($_POST["unidades_por_bulto"] ?? 1);
$fechaVencimiento = trim($_POST["fecha_vencimiento"] ?? "");
$proveedor = trim($_POST["proveedor"] ?? "");
$motivo = trim($_POST["motivo"] ?? "Modificación / corrección de producto");

$presentacionesValidas = ["unidad", "caja", "bulto"];

if ($id <= 0) {
    responderJson(["error" => "ID de producto inválido."], 400);
}

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $productoActual = $firestore->obtenerDocumento("productos", (string)$id);

    if (!$productoActual) {
        responderJson(["error" => "El producto no existe."], 404);
    }

    $nombre = isset($_POST["nombre"]) && trim($_POST["nombre"]) !== "" ? trim($_POST["nombre"]) : ($productoActual["nombre"] ?? "");
    $descripcion = isset($_POST["descripcion"]) ? trim($_POST["descripcion"]) : ($productoActual["descripcion"] ?? "");

    // Precios
    $precioCosto = isset($_POST["precio_costo"]) && $_POST["precio_costo"] !== "" ? floatval($_POST["precio_costo"]) : floatval($productoActual["precio_costo"] ?? 0);
    $precioVenta = isset($_POST["precio_venta"]) && $_POST["precio_venta"] !== "" ? floatval($_POST["precio_venta"]) : (isset($_POST["precio"]) && $_POST["precio"] !== "" ? floatval($_POST["precio"]) : floatval($productoActual["precio_venta"] ?? $productoActual["precio"] ?? 0));
    $precio = $precioVenta;

    $stock = isset($_POST["stock"]) && $_POST["stock"] !== "" ? filter_input(INPUT_POST, "stock", FILTER_VALIDATE_INT) : (int)($productoActual["stock"] ?? 0);
    $presentacion = trim($_POST["presentacion"] ?? ($productoActual["presentacion"] ?? "unidad"));
    $unidadesPorBulto = isset($_POST["unidades_por_bulto"]) && $_POST["unidades_por_bulto"] !== "" ? intval($_POST["unidades_por_bulto"]) : intval($productoActual["unidades_por_bulto"] ?? 1);

    // Categoría
    $categoriaId = isset($_POST["categoria_id"]) && $_POST["categoria_id"] !== "" ? (int)$_POST["categoria_id"] : (isset($productoActual["categoria_id"]) ? (int)$productoActual["categoria_id"] : null);
    $categoriaNombre = trim($_POST["categoria_nombre"] ?? ($_POST["categoria"] ?? ($productoActual["categoria_nombre"] ?? $productoActual["categoria"] ?? "")));

    $fechaVencimiento = isset($_POST["fecha_vencimiento"]) ? trim($_POST["fecha_vencimiento"]) : ($productoActual["fecha_vencimiento"] ?? "");
    $proveedor = isset($_POST["proveedor"]) ? trim($_POST["proveedor"]) : ($productoActual["proveedor"] ?? "");
    $codigoBarras = isset($_POST["codigo_barras"]) ? trim($_POST["codigo_barras"]) : ($productoActual["codigo_barras"] ?? "");
    $motivo = trim($_POST["motivo"] ?? "Modificación / corrección de producto");

    $presentacionesValidas = ["unidad", "caja", "bulto"];

    if ($nombre === "" || mb_strlen($nombre) > 150) {
        responderJson(["error" => "Ingresá un nombre de producto válido (máx. 150 caracteres)."], 400);
    }
    if ($precioVenta <= 0) {
        responderJson(["error" => "El precio de venta debe ser mayor a 0."], 400);
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

    $codigoBarrasParam = $codigoBarras !== "" ? $codigoBarras : null;

    if (mb_strlen($proveedor) > 150) {
        responderJson(["error" => "El nombre del proveedor supera los 150 caracteres."], 400);
    }
    $proveedorParam = $proveedor !== "" ? $proveedor : null;
    $usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;
    $usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Admin") . " " . ($_SESSION["usuario_apellido"] ?? ""));

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
            $nombreArchivo = "prod_" . $id . "_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
            $destino = $dirUploads . $nombreArchivo;
            if (move_uploaded_file($file["tmp_name"], $destino)) {
                $imagenUrl = "uploads/productos/" . $nombreArchivo;
            }
        }
    }

    // Si se especificó categoría_id y no se pasó nombre, buscarlo
    if ($categoriaId !== null && $categoriaNombre === "") {
        $catDoc = $firestore->obtenerDocumento("categorias", (string)$categoriaId);
        if ($catDoc) {
            $categoriaNombre = $catDoc["nombre"] ?? "";
        }
    }

    // Validar código de barras no duplicado en otro producto
    if ($codigoBarrasParam !== null) {
        $existentesCb = $firestore->consultar("productos", [
            ["codigo_barras", "==", $codigoBarrasParam]
        ]);
        foreach ($existentesCb as $ecb) {
            $eId = (int) ($ecb["id"] ?? $ecb["_id"] ?? 0);
            if ($eId !== $id) {
                responderJson(["error" => "Ya existe otro producto con el mismo código de barras."], 409);
            }
        }
    }

    $stockAnterior = (int) ($productoActual["stock"] ?? 0);
    $precioAnterior = (float) ($productoActual["precio"] ?? 0);
    $diferenciaStock = $stock - $stockAnterior;

    // Si no se proporcionó nuevo precio de costo, mantener el actual
    $precioCostoFinal = $precioCosto !== null ? $precioCosto : floatval($productoActual["precio_costo"] ?? 0);

    // Si no se subió nueva imagen ni se envió URL, conservar la anterior
    $imagenFinal = $imagenUrl !== "" ? $imagenUrl : ($productoActual["imagen_url"] ?? null);

    $camposActualizados = [
        "nombre" => $nombre,
        "descripcion" => $descripcion !== "" ? $descripcion : null,
        "codigo_barras" => $codigoBarrasParam,
        "precio" => $precioVenta,
        "precio_venta" => $precioVenta,
        "precio_costo" => $precioCostoFinal,
        "stock" => $stock,
        "categoria_id" => $categoriaId,
        "categoria" => $categoriaNombre !== "" ? $categoriaNombre : null,
        "categoria_nombre" => $categoriaNombre !== "" ? $categoriaNombre : null,
        "imagen_url" => $imagenFinal,
        "presentacion" => $presentacion,
        "unidades_por_bulto" => $unidadesPorBulto,
        "fecha_vencimiento" => $vencimientoParam,
        "proveedor" => $proveedorParam,
        "modificado_el" => date("Y-m-d H:i:s")
    ];

    $firestore->actualizarCampos("productos", (string)$id, $camposActualizados);

    // Si hubo incremento de stock, registrar en Kardex de ingresos
    if ($diferenciaStock > 0) {
        $sinFacturaMod = isset($_POST["sin_factura"]) && ($_POST["sin_factura"] === "1" || $_POST["sin_factura"] === "true" || $_POST["sin_factura"] === "on");
        $numeroFacturaMod = trim($_POST["numero_factura"] ?? "");
        $facturaFinalMod = ($sinFacturaMod || $numeroFacturaMod === "") ? ($sinFacturaMod ? "Sin Factura" : null) : $numeroFacturaMod;

        $cantidadBultosIngresados = ($unidadesPorBulto > 1) ? intdiv($diferenciaStock, $unidadesPorBulto) : $diferenciaStock;
        if ($cantidadBultosIngresados === 0) {
            $cantidadBultosIngresados = 1;
        }

        $ingresoId = $firestore->obtenerSiguienteId("contadores", "ingresos", "ultimo_id");
        $motivoIngreso = "Ajuste de inventario (+" . $diferenciaStock . " un.): " . $motivo . ($facturaFinalMod ? " [Factura: {$facturaFinalMod}]" : "");

        $ingresoDatos = [
            "id" => $ingresoId,
            "producto_id" => $id,
            "producto_nombre" => $nombre,
            "cantidad" => $cantidadBultosIngresados,
            "presentacion" => $presentacion,
            "unidades_por_bulto" => $unidadesPorBulto,
            "total_unidades" => $diferenciaStock,
            "precio_unitario" => $precioVenta,
            "precio_costo" => $precioCostoFinal,
            "precio_venta" => $precioVenta,
            "proveedor" => $proveedorParam,
            "fecha_vencimiento" => $vencimientoParam,
            "numero_factura" => $facturaFinalMod,
            "sin_factura" => $sinFacturaMod,
            "usuario_id" => $usuarioId,
            "motivo" => $motivoIngreso,
            "fecha" => date("Y-m-d H:i:s")
        ];

        $firestore->guardarDocumento("ingresos_stock", (string)$ingresoId, $ingresoDatos);
    }

    // Registrar en trazabilidad de movimientos
    $tipoMovimiento = ($diferenciaStock !== 0) ? "AJUSTE_STOCK" : "EDICION_DATOS";
    $descripcionMov = "Modificación: Costo: $" . number_format($precioCostoFinal, 2) . " | Venta: $" . number_format($precioVenta, 2);
    if ($diferenciaStock !== 0) {
        $descripcionMov .= " | Variación: " . ($diferenciaStock > 0 ? "+{$diferenciaStock}" : "{$diferenciaStock}") . " un. ($motivo)";
    }

    FirestoreConexion::registrarMovimientoProducto(
        productoId: $id,
        tipo: $tipoMovimiento,
        descripcion: $descripcionMov,
        cantidadAnterior: $stockAnterior,
        cantidadNueva: $stock,
        diferencia: $diferenciaStock !== 0 ? $diferenciaStock : null,
        precioAnterior: $precioAnterior,
        precioNuevo: $precioVenta,
        usuarioId: $usuarioId,
        usuarioNombre: $usuarioNombre
    );

    responderJson([
        "success" => true,
        "id" => $id,
        "nombre" => $nombre,
        "precio_costo" => $precioCostoFinal,
        "precio_venta" => $precioVenta,
        "imagen_url" => $imagenFinal,
        "mensaje" => "Producto modificado exitosamente."
    ]);
} catch (Throwable $e) {
    error_log("Error al modificar producto: " . $e->getMessage());
    responderJson(["error" => "No se pudo actualizar el producto: " . $e->getMessage()], 500);
}
?>
