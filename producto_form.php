<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$esEdicion = ($id > 0);
$producto = null;

$firestore = FirestoreConexion::obtenerFirestore();

if ($esEdicion) {
    $producto = $firestore->obtenerDocumento("productos", (string)$id);
    if (!$producto) {
        header("Location: admin.php?error=" . urlencode("El producto no existe."));
        exit;
    }
}

// Cargar categorías y proveedores para los selectores
$categorias = $firestore->obtenerTodos("categorias");
usort($categorias, fn($a, $b) => strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? ""));

$proveedores = $firestore->obtenerTodos("proveedores");
usort($proveedores, fn($a, $b) => strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? ""));

$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Administrador") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esEdicion ? "Editar Producto #{$id}" : "Ingreso y Alta de Productos (1 a 50 ítems)" ?> | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>">
    <!-- Barra Superior -->
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Control Stock</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="admin.php" class="btn btn-outline-secondary btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <?php if ($esEdicion): ?>
        <!-- ============================================================== -->
        <!-- MODO EDICIÓN INDIVIDUAL DE PRODUCTO EXISTENTE                   -->
        <!-- ============================================================== -->
        <div class="row justify-content-center">
            <div class="col-12 col-lg-9">
                <!-- Encabezado -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <a href="admin.php" class="text-decoration-none text-muted small">← Volver a Inventario</a>
                        <h1 class="h3 fw-bold mt-1 mb-0">Editar Producto #<?= $id ?></h1>
                        <p class="text-muted small mb-0">Modificación de información comercial, precios, categoría y stock.</p>
                    </div>
                </div>

                <!-- Alertas -->
                <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
                <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

                <form id="formProductoStandalone" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <!-- 1. Información General -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">1. Datos Básicos del Producto</h2>
                        
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="prodNombre" class="form-label fw-bold">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="prodNombre" name="nombre" value="<?= htmlspecialchars($producto['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Coca Cola 1.5L Original" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="prodCategoria" class="form-label fw-bold">Categoría *</label>
                                <select class="form-select" id="prodCategoria" name="categoria_id" required>
                                    <option value="">-- Seleccionar Categoría --</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <?php 
                                            $sel = (isset($producto['categoria_id']) && (int)$producto['categoria_id'] === (int)$cat['id']) || 
                                                   (isset($producto['categoria_nombre']) && $producto['categoria_nombre'] === $cat['nombre']) ||
                                                   (isset($producto['categoria']) && $producto['categoria'] === $cat['nombre']);
                                        ?>
                                        <option value="<?= $cat['id'] ?>" data-nombre="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>" <?= $sel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="prodCategoriaNombre" name="categoria_nombre" value="<?= htmlspecialchars($producto['categoria_nombre'] ?? $producto['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mt-1">
                                    <a href="categorias.php" target="_blank" class="small text-decoration-none">Gestionar categorías</a>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="prodDescripcion" class="form-label">Descripción Comercial</label>
                                <textarea class="form-control" id="prodDescripcion" name="descripcion" rows="3" placeholder="Detalles de presentación, contenido neto, etc."><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <!-- Foto del Producto -->
                            <div class="col-12">
                                <label class="form-label fw-bold">Foto o Imagen del Producto</label>
                                <div class="p-3 border rounded-3 bg-light">
                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-3 text-center">
                                            <div id="previewFotoContenedor" class="border rounded-3 bg-white p-2 d-flex align-items-center justify-content-center" style="height: 120px; overflow: hidden;">
                                                <?php if (!empty($producto['imagen_url'])): ?>
                                                    <img id="imgPreview" src="<?= htmlspecialchars($producto['imagen_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                                <?php else: ?>
                                                    <span id="imgPlaceholder" class="text-muted small">Sin imagen</span>
                                                    <img id="imgPreview" src="" alt="Foto" class="d-none" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-9">
                                            <label for="prodImagenArchivo" class="form-label small text-muted">Subir imagen desde el dispositivo (JPG, PNG, WebP)</label>
                                            <input type="file" class="form-control form-control-sm mb-2" id="prodImagenArchivo" name="imagen_archivo" accept="image/*">
                                            
                                            <label for="prodImagenUrl" class="form-label small text-muted">O ingresar enlace / URL de imagen web</label>
                                            <input type="url" class="form-control form-control-sm" id="prodImagenUrl" name="imagen_url" value="<?= htmlspecialchars($producto['imagen_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://ejemplo.com/foto.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Código de Barras y Proveedor -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">2. Identificación y Proveedor</h2>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="prodCodigoBarras" class="form-label">Código de Barras (EAN-13 / UPC / Alfanumérico)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace" id="prodCodigoBarras" name="codigo_barras" value="<?= htmlspecialchars($producto['codigo_barras'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 7791234567890">
                                    <button class="btn btn-outline-secondary" type="button" id="btnEscanearCb" title="Escanear con cámara">Escanear</button>
                                    <button class="btn btn-outline-secondary" type="button" id="btnGenerarCb" title="Generar código aleatorio">Generar</button>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="prodProveedor" class="form-label">Proveedor</label>
                                <select class="form-select" id="prodProveedor" name="proveedor">
                                    <option value="">-- Seleccionar Proveedor --</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>" <?= (isset($producto['proveedor']) && $producto['proveedor'] === $prov['nombre']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Precios y Finanzas -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">3. Precios y Márgenes</h2>
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-5">
                                    <label for="prodPrecioCosto" class="form-label fw-bold">Precio de Costo ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold" id="prodPrecioCosto" name="precio_costo" value="<?= htmlspecialchars((string)($producto['precio_costo'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00" required>
                                    </div>
                                    <small class="text-muted">Costo unitario de compra al proveedor.</small>
                                </div>

                                <div class="col-12 col-md-5">
                                    <label for="prodPrecioVenta" class="form-label fw-bold text-success">Precio de Venta ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control fw-bold text-success fs-5" id="prodPrecioVenta" name="precio_venta" value="<?= htmlspecialchars((string)($producto['precio_venta'] ?? $producto['precio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00" required>
                                    </div>
                                    <small class="text-muted">Precio final al público.</small>
                                </div>

                                <div class="col-12 col-md-2 d-flex flex-column justify-content-center text-center">
                                    <span class="small text-muted">Margen Bruto</span>
                                    <span id="lblMargenBruto" class="fw-bold fs-5 text-primary">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Stock, Presentación y Vencimiento -->
                    <div class="seccion-card mb-4">
                        <h2 class="h5 fw-bold text-primary mb-3">4. Inventario y Empaque</h2>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="prodPresentacion" class="form-label">Presentación de Venta</label>
                                <select class="form-select" id="prodPresentacion" name="presentacion">
                                    <option value="unidad" <?= (!isset($producto['presentacion']) || $producto['presentacion'] === 'unidad') ? 'selected' : '' ?>>Unidad individual</option>
                                    <option value="caja" <?= (isset($producto['presentacion']) && $producto['presentacion'] === 'caja') ? 'selected' : '' ?>>Caja</option>
                                    <option value="bulto" <?= (isset($producto['presentacion']) && $producto['presentacion'] === 'bulto') ? 'selected' : '' ?>>Bulto cerrado</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4 <?= (isset($producto['presentacion']) && ($producto['presentacion'] === 'caja' || $producto['presentacion'] === 'bulto')) ? '' : 'd-none' ?>" id="contenedorUnidadesBulto">
                                <label for="prodUnidadesBulto" class="form-label">Unidades por caja/bulto</label>
                                <input type="number" min="1" class="form-control" id="prodUnidadesBulto" name="unidades_por_bulto" value="<?= htmlspecialchars((string)($producto['unidades_por_bulto'] ?? 1), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="prodStock" class="form-label fw-bold">Stock Total (Unidades) *</label>
                                <input type="number" min="0" class="form-control" id="prodStock" name="stock" value="<?= htmlspecialchars((string)($producto['stock'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" required>
                                <small class="text-muted">Unidades físicas totales disponibles.</small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="prodVencimiento" class="form-label">Fecha de Vencimiento (FIFO)</label>
                                <input type="date" class="form-control" id="prodVencimiento" name="fecha_vencimiento" value="<?= htmlspecialchars($producto['fecha_vencimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="prodNumeroFactura" class="form-label">N° Factura / Remito</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="prodNumeroFactura" name="numero_factura" placeholder="Ej: FC-0001-12345678">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0 me-1" type="checkbox" id="prodSinFactura" name="sin_factura">
                                        <label class="form-check-label small" for="prodSinFactura">Sin factura</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="contenedorPermiteVentaUnidad">
                                <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                    <div>
                                        <label class="form-check-label fw-bold d-block text-dark" for="prodPermiteVentaUnidad">
                                            ¿Se puede vender por unidad suelta / fraccionada?
                                        </label>
                                        <small class="text-muted d-block" id="textoAyudaPermiteUnidad">
                                            <?= (isset($producto['presentacion']) && $producto['presentacion'] === 'unidad') 
                                                ? 'Los artículos con presentación "Unidad" se venden siempre por unidad.' 
                                                : 'Si está desactivado, el Punto de Venta obligará a vender únicamente en presentación empaquetada (Caja/Bulto).' ?>
                                        </small>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input fs-4" type="checkbox" role="switch" id="prodPermiteVentaUnidad" name="permite_venta_unidad" value="1" <?= (!isset($producto['permite_venta_unidad']) || $producto['permite_venta_unidad'] ? 'checked' : '') ?> <?= (isset($producto['presentacion']) && $producto['presentacion'] === 'unidad') ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="prodMotivo" class="form-label small text-muted">Motivo de la Modificación (para auditoría)</label>
                                <input type="text" class="form-control form-control-sm" id="prodMotivo" name="motivo" value="Modificación / corrección de producto">
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border shadow-sm flex-wrap gap-2 sticky-bottom mb-5">
                        <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 fs-6 fw-bold" id="btnGuardarProducto">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- ============================================================== -->
        <!-- MODO UNIFICADO DE INGRESO Y ALTA DE PRODUCTOS (1 A 50 ÍTEMS)    -->
        <!-- ============================================================== -->
        <!-- Encabezado -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="admin.php" class="text-decoration-none text-muted small">← Volver al Inventario</a>
                <h1 class="h3 fw-bold mt-1 mb-0">Ingreso de Productos</h1>
                <p class="text-muted small mb-0">Alta individual o masiva de artículos al inventario.</p>
            </div>
        </div>

        <!-- Alertas -->
        <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
        <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

        <!-- 1. Proveedor y Comprobante de Compra -->
        <div class="seccion-card mb-4">
            <h2 class="h5 fw-bold text-primary mb-3">1. Datos del Proveedor y Comprobante de Compra</h2>
            <div class="p-3 bg-light rounded-3 border">
                <div class="row g-3 align-items-center">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold" for="masivoProveedor">Proveedor *</label>
                        <select class="form-select" id="masivoProveedor" required>
                            <option value="">-- Seleccionar Proveedor --</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-1">
                            <input type="text" class="form-control form-control-sm mt-1" id="masivoProveedorTexto" placeholder="O escribir nuevo proveedor...">
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold" for="masivoNumeroFactura">N° Factura / Remito</label>
                        <input type="text" class="form-control" id="masivoNumeroFactura" placeholder="Ej: FC-A-0001-00123456">
                    </div>

                    <div class="col-12 col-md-3 d-flex align-items-center pt-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="masivoSinFactura">
                            <label class="form-check-label small" for="masivoSinFactura">Ingreso sin comprobante</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Tabla Dinámica de Productos a Ingresar -->
        <div class="seccion-card mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h2 class="h5 fw-bold text-primary mb-0">2. Artículos a Ingresar</h2>
                    <small class="text-muted">Ingreso individual o en lote (hasta 50 filas).</small>
                </div>
                <button type="button" class="btn btn-outline-primary fw-bold" id="btnAgregarFila">
                    Añadir fila
                </button>
            </div>

            <div class="table-responsive border rounded-3 mb-3 bg-white" style="min-height: 200px;">
                <table class="table table-bordered table-hover align-middle mb-0" id="tablaMasiva">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th style="width: 35px;">#</th>
                            <th style="min-width: 190px;">Nombre del Producto *</th>
                            <th style="min-width: 140px;">Categoría</th>
                            <th style="min-width: 140px;">Código Barras</th>
                            <th style="min-width: 115px;">Presentación</th>
                            <th style="min-width: 90px;" class="text-center">¿Venta x Unid.?</th>
                            <th style="width: 90px;">Cant. *</th>
                            <th style="width: 105px;">P. Costo ($)</th>
                            <th style="width: 105px;">P. Venta ($) *</th>
                            <th style="width: 135px;">Vencimiento</th>
                            <th style="width: 45px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoFilasMasivas"></tbody>
                </table>
            </div>

            <!-- Resumen Financiero del Ingreso -->
            <div class="p-3 bg-light rounded-3 border mb-3">
                <div class="row text-center g-3">
                    <div class="col-12 col-sm-4">
                        <span class="text-muted small d-block">Productos a registrar</span>
                        <strong class="fs-5 text-dark" id="resumenTotalProd">0</strong>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="text-muted small d-block">Unidades físicas totales</span>
                        <strong class="fs-5 text-dark" id="resumenTotalUnidades">0 un.</strong>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="text-muted small d-block">Valor total de venta estimado</span>
                        <strong class="fs-5 text-success" id="resumenValorTotal">$ 0,00</strong>
                    </div>
                </div>
            </div>

            <!-- Botones Guardar -->
            <div class="d-flex justify-content-between align-items-center pt-2">
                <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
                <button type="button" class="btn btn-success px-4 py-3 fs-5 fw-bold shadow" id="btnGuardarLote">
                    Guardar Ingreso
                </button>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal Escáner Cámara Standalone -->
    <div class="modal fade" id="modalScannerCamara" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold">Escanear Código de Barras</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="contenedorLectorCamara" class="p-2 mb-3">
                        <div id="qr-reader"></div>
                    </div>
                    <div id="scannerResultado" class="alert alert-info d-none mb-0 py-2 small"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const esEdicion = <?= $esEdicion ? "true" : "false" ?>;
        const categoriasDisponibles = <?= json_encode($categorias, JSON_UNESCAPED_UNICODE) ?>;
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });
        const csrfToken = document.body.dataset.csrf;

        // Escáner Cámara Global
        let html5Qr = null;
        let callbackScanActivo = null;
        const modalScan = new bootstrap.Modal(document.getElementById("modalScannerCamara"));

        function abrirCamara(cb) {
            callbackScanActivo = cb;
            modalScan.show();
            setTimeout(() => {
                if (html5Qr) html5Qr.clear().catch(() => {});
                html5Qr = new Html5Qrcode("qr-reader");
                html5Qr.start(
                    { facingMode: "environment" },
                    { fps: 15, qrbox: { width: 250, height: 150 } },
                    (decodedText) => {
                        html5Qr.stop().then(() => {
                            html5Qr.clear();
                            html5Qr = null;
                        });
                        modalScan.hide();
                        if (callbackScanActivo) callbackScanActivo(decodedText);
                    },
                    () => {}
                ).catch(err => {
                    const res = document.getElementById("scannerResultado");
                    res.textContent = "Error al abrir cámara: " + err;
                    res.classList.remove("d-none");
                });
            }, 300);
        }

        document.getElementById("modalScannerCamara").addEventListener("hidden.bs.modal", () => {
            if (html5Qr) {
                html5Qr.stop().then(() => {
                    html5Qr.clear();
                    html5Qr = null;
                }).catch(() => {});
            }
        });

        if (esEdicion) {
            // ==========================================
            // LÓGICA DE EDICIÓN INDIVIDUAL
            // ==========================================
            const inputCosto = document.getElementById("prodPrecioCosto");
            const inputVenta = document.getElementById("prodPrecioVenta");
            const lblMargen = document.getElementById("lblMargenBruto");

            function recalcularMargen() {
                const costo = parseFloat(inputCosto.value) || 0;
                const venta = parseFloat(inputVenta.value) || 0;
                if (costo > 0 && venta > 0) {
                    const margen = ((venta - costo) / costo) * 100;
                    lblMargen.textContent = (margen >= 0 ? "+" : "") + margen.toFixed(1) + "%";
                    lblMargen.className = margen >= 0 ? "fw-bold fs-5 text-success" : "fw-bold fs-5 text-danger";
                } else {
                    lblMargen.textContent = "0%";
                    lblMargen.className = "fw-bold fs-5 text-muted";
                }
            }

            inputCosto.addEventListener("input", recalcularMargen);
            inputVenta.addEventListener("input", recalcularMargen);
            recalcularMargen();

            const selCat = document.getElementById("prodCategoria");
            const inputCatNombre = document.getElementById("prodCategoriaNombre");
            selCat.addEventListener("change", () => {
                const opt = selCat.selectedOptions[0];
                inputCatNombre.value = (opt && opt.dataset.nombre) ? opt.dataset.nombre : "";
            });

            const selPres = document.getElementById("prodPresentacion");
            const contBulto = document.getElementById("contenedorUnidadesBulto");
            const inputUnidBulto = document.getElementById("prodUnidadesBulto");
            const chkPermiteUnidad = document.getElementById("prodPermiteVentaUnidad");
            const txtAyudaPermite = document.getElementById("textoAyudaPermiteUnidad");

            selPres.addEventListener("change", () => {
                if (selPres.value === "caja" || selPres.value === "bulto") {
                    contBulto.classList.remove("d-none");
                    if (parseFloat(inputUnidBulto.value) <= 1) inputUnidBulto.value = selPres.value === "caja" ? 12 : 24;
                    if (chkPermiteUnidad) chkPermiteUnidad.disabled = false;
                    if (txtAyudaPermite) txtAyudaPermite.textContent = `Si está desactivado, el Punto de Venta obligará a vender únicamente en ${selPres.value === 'caja' ? 'cajas cerradas' : 'bultos cerrados'}.`;
                } else {
                    contBulto.classList.add("d-none");
                    inputUnidBulto.value = 1;
                    if (chkPermiteUnidad) {
                        chkPermiteUnidad.checked = true;
                        chkPermiteUnidad.disabled = true;
                    }
                    if (txtAyudaPermite) txtAyudaPermite.textContent = 'Los artículos con presentación "Unidad" se venden siempre por unidad individual.';
                }
            });

            const chkSinFactura = document.getElementById("prodSinFactura");
            const inputFactura = document.getElementById("prodNumeroFactura");
            chkSinFactura.addEventListener("change", () => {
                inputFactura.disabled = chkSinFactura.checked;
                if (chkSinFactura.checked) inputFactura.value = "";
            });

            const inputImgArchivo = document.getElementById("prodImagenArchivo");
            const inputImgUrl = document.getElementById("prodImagenUrl");
            const imgPreview = document.getElementById("imgPreview");
            const imgPlaceholder = document.getElementById("imgPlaceholder");

            inputImgArchivo.addEventListener("change", (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        imgPreview.src = ev.target.result;
                        imgPreview.classList.remove("d-none");
                        if (imgPlaceholder) imgPlaceholder.classList.add("d-none");
                    };
                    reader.readAsDataURL(file);
                }
            });

            inputImgUrl.addEventListener("input", () => {
                const url = inputImgUrl.value.trim();
                if (url) {
                    imgPreview.src = url;
                    imgPreview.classList.remove("d-none");
                    if (imgPlaceholder) imgPlaceholder.classList.add("d-none");
                }
            });

            document.getElementById("btnGenerarCb").addEventListener("click", () => {
                document.getElementById("prodCodigoBarras").value = "779" + Math.floor(Math.random() * 1000000000).toString().padStart(9, "0");
            });

            document.getElementById("btnEscanearCb").addEventListener("click", () => {
                abrirCamara((decoded) => {
                    document.getElementById("prodCodigoBarras").value = decoded;
                });
            });

            const form = document.getElementById("formProductoStandalone");
            form.addEventListener("submit", async (e) => {
                e.preventDefault();
                const alertErr = document.getElementById("alertaError");
                const alertOk = document.getElementById("alertaExito");
                const btnSubmit = document.getElementById("btnGuardarProducto");

                alertErr.classList.add("d-none");
                alertOk.classList.add("d-none");

                btnSubmit.disabled = true;
                const originalText = btnSubmit.innerHTML;
                btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando...`;

                try {
                    const formData = new FormData(form);
                    const resp = await fetch("modificar_producto.php", {
                        method: "POST",
                        headers: { "X-CSRF-Token": csrfToken },
                        body: formData
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) throw new Error(data.error || "No se pudo guardar el producto.");

                    alertOk.textContent = data.mensaje || "¡Producto modificado exitosamente!";
                    alertOk.classList.remove("d-none");
                    setTimeout(() => { window.location.href = "admin.php"; }, 1000);
                } catch (err) {
                    alertErr.textContent = err.message;
                    alertErr.classList.remove("d-none");
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalText;
                    window.scrollTo({ top: 0, behavior: "smooth" });
                }
            });

        } else {
            // ==========================================
            // LÓGICA DE INGRESO DINÁMICO (1 A 50 ÍTEMS)
            // ==========================================
            const tbody = document.getElementById("cuerpoFilasMasivas");
            const chkSinFactura = document.getElementById("masivoSinFactura");
            const inputFactura = document.getElementById("masivoNumeroFactura");

            chkSinFactura.addEventListener("change", () => {
                inputFactura.disabled = chkSinFactura.checked;
                if (chkSinFactura.checked) inputFactura.value = "";
            });

            function crearFila(indice) {
                const tr = document.createElement("tr");

                let optCatHtml = `<option value="">-- Categoría --</option>`;
                categoriasDisponibles.forEach(c => {
                    optCatHtml += `<option value="${c.id}" data-nombre="${c.nombre}">${c.nombre}</option>`;
                });

                tr.innerHTML = `
                    <td class="text-muted small text-center">${indice}</td>
                    <td>
                        <input type="text" class="form-control form-control-sm masivo-nombre" placeholder="Nombre del artículo *" required>
                    </td>
                    <td>
                        <select class="form-select form-select-sm masivo-cat">
                            ${optCatHtml}
                        </select>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace masivo-cb" placeholder="EAN-13">
                            <button class="btn btn-outline-secondary btn-sm btn-gen-cb" type="button" title="Generar código aleatorio">Gen</button>
                            <button class="btn btn-outline-secondary btn-sm btn-scan-cb" type="button" title="Escanear con cámara">Cam</button>
                        </div>
                    </td>
                    <td>
                        <select class="form-select form-select-sm masivo-pres mb-1">
                            <option value="unidad">Unidad</option>
                            <option value="caja">Caja</option>
                            <option value="bulto">Bulto</option>
                        </select>
                        <input type="number" min="1" class="form-control form-control-sm masivo-unid-bulto d-none" placeholder="Unids/bulto" value="1">
                    </td>
                    <td class="text-center align-middle">
                        <div class="form-check form-switch d-inline-block m-0">
                            <input class="form-check-input masivo-venta-unidad" type="checkbox" role="switch" title="¿Se puede vender por unidad suelta?" checked disabled>
                        </div>
                        <small class="d-block text-muted masivo-lbl-unid" style="font-size: 0.72rem;">Sí</small>
                    </td>
                    <td>
                        <input type="number" min="0" class="form-control form-control-sm masivo-stock" placeholder="0" value="0">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm masivo-costo" placeholder="0.00" value="0.00">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm masivo-venta" placeholder="0.00" value="0.00">
                    </td>
                    <td>
                        <input type="date" class="form-control form-control-sm masivo-venc">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del-fila" title="Quitar fila">✕</button>
                    </td>
                `;

                const selPres = tr.querySelector(".masivo-pres");
                const inputUnid = tr.querySelector(".masivo-unid-bulto");
                const chkVentaUnid = tr.querySelector(".masivo-venta-unidad");
                const lblVentaUnid = tr.querySelector(".masivo-lbl-unid");

                chkVentaUnid.addEventListener("change", () => {
                    lblVentaUnid.textContent = chkVentaUnid.checked ? "Sí" : "No";
                    lblVentaUnid.className = chkVentaUnid.checked ? "d-block text-success fw-bold" : "d-block text-danger fw-bold";
                });

                selPres.addEventListener("change", () => {
                    if (selPres.value === "caja" || selPres.value === "bulto") {
                        inputUnid.classList.remove("d-none");
                        if (parseInt(inputUnid.value) <= 1) inputUnid.value = selPres.value === "caja" ? 12 : 24;
                        chkVentaUnid.disabled = false;
                        chkVentaUnid.checked = true;
                        lblVentaUnid.textContent = "Sí";
                        lblVentaUnid.className = "d-block text-success fw-bold";
                    } else {
                        inputUnid.classList.add("d-none");
                        inputUnid.value = 1;
                        chkVentaUnid.checked = true;
                        chkVentaUnid.disabled = true;
                        lblVentaUnid.textContent = "Sí";
                        lblVentaUnid.className = "d-block text-muted";
                    }
                    recalcularResumen();
                });

                const inputCb = tr.querySelector(".masivo-cb");
                tr.querySelector(".btn-gen-cb").addEventListener("click", () => {
                    inputCb.value = "779" + Math.floor(Math.random() * 1000000000).toString().padStart(9, "0");
                });
                tr.querySelector(".btn-scan-cb").addEventListener("click", () => {
                    abrirCamara((decoded) => {
                        inputCb.value = decoded;
                    });
                });

                tr.querySelectorAll("input, select").forEach(el => {
                    el.addEventListener("input", recalcularResumen);
                });

                tr.querySelector(".btn-del-fila").addEventListener("click", () => {
                    if (tbody.children.length > 1) {
                        tr.remove();
                        renumerarFilas();
                        recalcularResumen();
                    } else {
                        tr.querySelectorAll("input").forEach(i => i.value = "");
                        recalcularResumen();
                    }
                });

                return tr;
            }

            function renumerarFilas() {
                Array.from(tbody.children).forEach((tr, idx) => {
                    tr.children[0].textContent = idx + 1;
                });
            }

            function recalcularResumen() {
                let totalLineas = 0;
                let totalUnidadesFisicas = 0;
                let valorTotalVenta = 0;

                Array.from(tbody.children).forEach(tr => {
                    const nombre = tr.querySelector(".masivo-nombre").value.trim();
                    const stock = parseInt(tr.querySelector(".masivo-stock").value) || 0;
                    const venta = parseFloat(tr.querySelector(".masivo-venta").value) || 0;
                    const unidBulto = Math.max(1, parseInt(tr.querySelector(".masivo-unid-bulto").value) || 1);

                    if (nombre !== "" || stock > 0 || venta > 0) {
                        totalLineas++;
                        const unids = stock * unidBulto;
                        totalUnidadesFisicas += unids;
                        valorTotalVenta += (unids * venta);
                    }
                });

                document.getElementById("resumenTotalProd").textContent = totalLineas;
                document.getElementById("resumenTotalUnidades").textContent = `${totalUnidadesFisicas} un.`;
                document.getElementById("resumenValorTotal").textContent = formatoMoneda.format(valorTotalVenta);
            }

            document.getElementById("btnAgregarFila").addEventListener("click", () => {
                if (tbody.children.length >= 50) {
                    alert("Se ha alcanzado el límite máximo de 50 productos por lote.");
                    return;
                }
                const nueva = crearFila(tbody.children.length + 1);
                tbody.appendChild(nueva);
                nueva.querySelector(".masivo-nombre").focus();
                recalcularResumen();
            });

            // Inicializar con 2 filas
            tbody.appendChild(crearFila(1));
            tbody.appendChild(crearFila(2));

            // Guardar Lote
            document.getElementById("btnGuardarLote").addEventListener("click", async () => {
                const alertErr = document.getElementById("alertaError");
                const alertOk = document.getElementById("alertaExito");
                alertErr.classList.add("d-none");
                alertOk.classList.add("d-none");

                const selProv = document.getElementById("masivoProveedor");
                const txtProv = document.getElementById("masivoProveedorTexto");
                const proveedor = txtProv.value.trim() || selProv.value.trim();
                const numFactura = inputFactura.value.trim();
                const sinFactura = chkSinFactura.checked;

                const productosLote = [];
                const errores = [];

                Array.from(tbody.children).forEach((tr, idx) => {
                    const num = idx + 1;
                    const nombre = tr.querySelector(".masivo-nombre").value.trim();
                    const selCat = tr.querySelector(".masivo-cat");
                    const catId = selCat.value ? parseInt(selCat.value) : null;
                    const catNom = selCat.selectedOptions[0]?.dataset?.nombre || "";
                    const cb = tr.querySelector(".masivo-cb").value.trim();
                    const pres = tr.querySelector(".masivo-pres").value;
                    const chkUnid = tr.querySelector(".masivo-venta-unidad");
                    const permiteUnid = (pres === "unidad") ? true : (chkUnid ? chkUnid.checked : true);
                    const unidBulto = Math.max(1, parseInt(tr.querySelector(".masivo-unid-bulto").value) || 1);
                    const stock = parseInt(tr.querySelector(".masivo-stock").value) || 0;
                    const costo = parseFloat(tr.querySelector(".masivo-costo").value) || 0;
                    const venta = parseFloat(tr.querySelector(".masivo-venta").value) || 0;
                    const venc = tr.querySelector(".masivo-venc").value.trim();

                    if (nombre === "" && stock === 0 && venta === 0) return;

                    if (nombre === "") {
                        errores.push(`Fila #${num}: El nombre del producto es obligatorio.`);
                        return;
                    }
                    if (venta <= 0) {
                        errores.push(`Fila #${num} ("${nombre}"): El precio de venta debe ser mayor a 0.`);
                        return;
                    }
                    if (stock < 0) {
                        errores.push(`Fila #${num} ("${nombre}"): El stock no puede ser negativo.`);
                        return;
                    }

                    productosLote.push({
                        nombre: nombre,
                        categoria_id: catId,
                        categoria_nombre: catNom,
                        codigo_barras: cb,
                        presentacion: pres,
                        permite_venta_unidad: permiteUnid,
                        unidades_por_bulto: unidBulto,
                        stock: stock,
                        precio_costo: costo,
                        precio_venta: venta,
                        precio: venta,
                        fecha_vencimiento: venc
                    });
                });

                if (errores.length > 0) {
                    alertErr.innerHTML = `<strong>Atención con los siguientes datos:</strong><ul class="mb-0 mt-1">${errores.map(e => `<li>${e}</li>`).join("")}</ul>`;
                    alertErr.classList.remove("d-none");
                    window.scrollTo({ top: 0, behavior: "smooth" });
                    return;
                }

                if (productosLote.length === 0) {
                    alertErr.textContent = "Completá al menos un producto para registrar el ingreso.";
                    alertErr.classList.remove("d-none");
                    return;
                }

                const btnSave = document.getElementById("btnGuardarLote");
                btnSave.disabled = true;
                btnSave.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando ${productosLote.length} producto(s)...`;

                try {
                    const resp = await fetch("guardar_productos_masivo.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-Token": csrfToken
                        },
                        body: JSON.stringify({
                            proveedor: proveedor,
                            numero_factura: numFactura,
                            sin_factura: sinFactura,
                            productos: productosLote
                        })
                    });

                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) {
                        throw new Error(data.error || "Error al procesar el ingreso de productos.");
                    }

                    alertOk.innerHTML = `<strong>¡Ingreso registrado con éxito!</strong> ${data.mensaje || `Se guardaron ${data.total_guardados} productos en Firestore.`}`;
                    alertOk.classList.remove("d-none");
                    window.scrollTo({ top: 0, behavior: "smooth" });

                    setTimeout(() => {
                        window.location.href = "admin.php";
                    }, 1200);

                } catch (err) {
                    alertErr.textContent = err.message;
                    alertErr.classList.remove("d-none");
                    btnSave.disabled = false;
                    btnSave.innerHTML = "Guardar Ingreso";
                    window.scrollTo({ top: 0, behavior: "smooth" });
                }
            });
        }
    </script>
</body>
</html>
