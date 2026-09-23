<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin"]);
requerirCsrfJson();

$inputRaw = file_get_contents("php://input");
$datosJson = json_decode($inputRaw, true);

if (!is_array($datosJson)) {
    $datosJson = [
        "proveedor" => trim($_POST["proveedor"] ?? ""),
        "productos" => json_decode($_POST["productos"] ?? "[]", true)
    ];
}

$proveedor = trim($datosJson["proveedor"] ?? "");
$productos = $datosJson["productos"] ?? [];

if (!is_array($productos) || empty($productos)) {
    responderJson(["error" => "Debés incluir al menos un producto para registrar la carga masiva."], 400);
}

$proveedorParam = $proveedor !== "" ? $proveedor : null;
$usuarioId = isset($_SESSION["usuario_id"]) ? (int) $_SESSION["usuario_id"] : null;
$usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "Admin") . " " . ($_SESSION["usuario_apellido"] ?? ""));

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $guardados = [];
    $errores = [];

    foreach ($productos as $idx => $prod) {
        $indiceFila = $idx + 1;
        $nombre = trim($prod["nombre"] ?? "");
        $precio = floatval($prod["precio"] ?? 0);
        $stock = intval($prod["stock"] ?? 0);
        $presentacion = trim($prod["presentacion"] ?? "unidad");
        $unidadesPorBulto = max(1, intval($prod["unidades_por_bulto"] ?? 1));
        $codigoBarras = trim($prod["codigo_barras"] ?? "");
        $fechaVencimiento = trim($prod["fecha_vencimiento"] ?? "");
        $descripcion = trim($prod["descripcion"] ?? "");

        if ($nombre === "") {
            $errores[] = "Fila #{$indiceFila}: el nombre del producto es obligatorio.";
            continue;
        }
        if ($precio <= 0) {
            $errores[] = "Fila #{$indiceFila} ({$nombre}): el precio debe ser mayor a 0.";
            continue;
        }
        if ($stock < 0) {
            $errores[] = "Fila #{$indiceFila} ({$nombre}): la cantidad no puede ser negativa.";
            continue;
        }

        if (!in_array($presentacion, ["unidad", "caja", "bulto"], true)) {
            $presentacion = "unidad";
        }
        if ($presentacion === "unidad") {
            $unidadesPorBulto = 1;
        }

        $vencimientoParam = ($fechaVencimiento !== "" && fechaIsoValida($fechaVencimiento)) ? $fechaVencimiento : null;
        
        // Generar código de barras si viene vacío
        if ($codigoBarras === "") {
            $codigoBarras = "779" . str_pad((string)random_int(100000000, 999999999), 9, "0", STR_PAD_LEFT);
        }

        $totalUnidades = $stock * $unidadesPorBulto;
        $productoId = FirestoreConexion::obtenerSiguienteIdProducto();

        $productoDoc = [
            "id" => $productoId,
            "codigo" => null,
            "codigo_barras" => $codigoBarras,
            "nombre" => $nombre,
            "descripcion" => $descripcion !== "" ? $descripcion : null,
            "presentacion" => $presentacion,
            "precio" => $precio,
            "stock" => $totalUnidades,
            "categoria" => null,
            "unidades_por_bulto" => $unidadesPorBulto,
            "fecha_vencimiento" => $vencimientoParam,
            "proveedor" => $proveedorParam,
            "creado_el" => date("Y-m-d H:i:s")
        ];

        $firestore->guardarDocumento("productos", (string)$productoId, $productoDoc);

        // Registrar en ingresos_stock si hay stock
        if ($totalUnidades > 0) {
            $ingresoId = $firestore->obtenerSiguienteId("contadores", "ingresos", "ultimo_id");
            $ingresoDoc = [
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
                "motivo" => "Alta masiva por lote (" . ($proveedorParam ? "Proveedor: {$proveedorParam}" : "Lote proveedor") . ")",
                "fecha" => date("Y-m-d H:i:s")
            ];
            $firestore->guardarDocumento("ingresos_stock", (string)$ingresoId, $ingresoDoc);
        }

        // Trazabilidad de movimientos
        FirestoreConexion::registrarMovimientoProducto(
            productoId: $productoId,
            tipo: "ALTA_INICIAL",
            descripcion: "Alta masiva por lote con stock de {$totalUnidades} un. a $" . number_format($precio, 2) . ($proveedorParam ? " [Proveedor: {$proveedorParam}]" : ""),
            cantidadAnterior: 0,
            cantidadNueva: $totalUnidades,
            diferencia: $totalUnidades,
            precioAnterior: null,
            precioNuevo: $precio,
            usuarioId: $usuarioId,
            usuarioNombre: $usuarioNombre
        );

        $guardados[] = [
            "id" => $productoId,
            "nombre" => $nombre,
            "codigo_barras" => $codigoBarras,
            "stock_unidades" => $totalUnidades,
            "precio" => $precio
        ];
    }

    if (empty($guardados)) {
        responderJson([
            "error" => "No se pudo registrar ningún producto. " . implode(" ", $errores)
        ], 400);
    }

    responderJson([
        "success" => true,
        "total_guardados" => count($guardados),
        "proveedor" => $proveedorParam,
        "guardados" => $guardados,
        "advertencias" => $errores,
        "mensaje" => "Se registraron exitosamente " . count($guardados) . " productos del lote."
    ], 201);
} catch (Throwable $e) {
    error_log("Error en guardar_productos_masivo.php: " . $e->getMessage());
    responderJson(["error" => "Ocurrió un error al procesar el lote: " . $e->getMessage()], 500);
}
?>
