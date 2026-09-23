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
    <title><?= $esEdicion ? "Editar Producto" : "Nuevo Producto" ?> | Control Stock</title>
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
        <div class="row justify-content-center">
            <div class="col-12 col-lg-9">
                <!-- Encabezado de Página -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <a href="admin.php" class="text-decoration-none text-muted small">← Volver a Inventario</a>
                        <h1 class="h3 fw-bold mt-1 mb-0"><?= $esEdicion ? "📝 Editar Producto #{$id}" : "✨ Alta de Nuevo Producto" ?></h1>
                        <p class="text-muted small mb-0"><?= $esEdicion ? "Modificá la información comercial, precios, categoría y stock del producto." : "Completá los datos del artículo para ingresarlo al catálogo e inventario." ?></p>
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
                                <label for="prodNombre" class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="prodNombre" name="nombre" value="<?= htmlspecialchars($producto['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Coca Cola 1.5L Original" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="prodCategoria" class="form-label">Categoría *</label>
                                <select class="form-select" id="prodCategoria" name="categoria_id" required>
                                    <option value="">-- Seleccionar Categoría --</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <?php 
                                            $sel = (isset($producto['categoria_id']) && (int)$producto['categoria_id'] === (int)$cat['id']) || 
                                                   (isset($producto['categoria_nombre']) && $producto['categoria_nombre'] === $cat['nombre']) ||
                                                   (isset($producto['categoria']) && $producto['categoria'] === $cat['nombre']);
                                        ?>
                                        <option value="<?= $cat['id'] ?>" data-nombre="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>" <?= $sel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($cat['icono'] ?? '🏷️') . ' ' . $cat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="prodCategoriaNombre" name="categoria_nombre" value="<?= htmlspecialchars($producto['categoria_nombre'] ?? $producto['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mt-1">
                                    <a href="categorias.php" target="_blank" class="small text-decoration-none">➕ Gestionar categorías</a>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="prodDescripcion" class="form-label">Descripción Comercial (para el catálogo visual)</label>
                                <textarea class="form-control" id="prodDescripcion" name="descripcion" rows="3" placeholder="Detalles de presentación, sabor, contenido neto, etc. Se mostrará en el catálogo de clientes y vendedores."><?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
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
                                                    <span id="imgPlaceholder" class="text-muted small">📷 Sin imagen</span>
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
                                    <button class="btn btn-outline-secondary" type="button" id="btnEscanearCb" title="Escanear con cámara">📷 Escanear</button>
                                    <button class="btn btn-outline-secondary" type="button" id="btnGenerarCb" title="Generar código aleatorio">🎲 Generar</button>
                                </div>
                                <div id="previewBarcodeStandalone" class="mt-2 text-center d-none">
                                    <svg id="svgBarcode"></svg>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="prodProveedor" class="form-label">🏢 Proveedor</label>
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
                                    <label for="prodPrecioCosto" class="form-label fw-bold">💰 Precio de Costo ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold" id="prodPrecioCosto" name="precio_costo" value="<?= htmlspecialchars((string)($producto['precio_costo'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00" required>
                                    </div>
                                    <small class="text-muted">Costo unitario de compra al proveedor.</small>
                                </div>

                                <div class="col-12 col-md-5">
                                    <label for="prodPrecioVenta" class="form-label fw-bold text-success">🏷️ Precio de Venta ($) *</label>
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
                                <label for="prodStock" class="form-label fw-bold"><?= $esEdicion ? "Stock Total (Unidades) *" : "Cantidad Inicial *" ?></label>
                                <input type="number" min="0" class="form-control" id="prodStock" name="stock" value="<?= htmlspecialchars((string)($producto['stock'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" required>
                                <small class="text-muted">Unidades físicas totales disponibles.</small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="prodVencimiento" class="form-label">Fecha de Vencimiento (FIFO)</label>
                                <input type="date" class="form-control" id="prodVencimiento" name="fecha_vencimiento" value="<?= htmlspecialchars($producto['fecha_vencimiento'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <!-- Factura de Ingreso -->
                            <div class="col-12 col-md-6">
                                <label for="prodNumeroFactura" class="form-label">📄 N° Factura / Remito</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="prodNumeroFactura" name="numero_factura" placeholder="Ej: FC-0001-12345678">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0 me-1" type="checkbox" id="prodSinFactura" name="sin_factura">
                                        <label class="form-check-label small" for="prodSinFactura">Sin factura</label>
                                    </div>
                                </div>
                            </div>

                            <?php if ($esEdicion): ?>
                                <div class="col-12">
                                    <label for="prodMotivo" class="form-label small text-muted">Motivo de la Modificación (para auditoría)</label>
                                    <input type="text" class="form-control form-control-sm" id="prodMotivo" name="motivo" value="Modificación / corrección de producto">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Botones de Acción Fijos / Finales -->
                    <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border shadow-sm flex-wrap gap-2 sticky-bottom mb-5">
                        <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 fs-6 fw-bold" id="btnGuardarProducto">
                            <?= $esEdicion ? "💾 Guardar Cambios" : "✨ Registrar Producto" ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Modal Escáner Cámara Standalone -->
    <div class="modal fade" id="modalScannerCamara" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold">📷 Escanear Código de Barras</h2>
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
        const endpointGuardar = esEdicion ? "modificar_producto.php" : "guardar_producto.php";

        // Margen Bruto en vivo
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

        // Categoría nombre sincronizado
        const selCat = document.getElementById("prodCategoria");
        const inputCatNombre = document.getElementById("prodCategoriaNombre");
        selCat.addEventListener("change", () => {
            const opt = selCat.selectedOptions[0];
            if (opt && opt.dataset.nombre) {
                inputCatNombre.value = opt.dataset.nombre;
            } else {
                inputCatNombre.value = "";
            }
        });

        // Presentación toggle unidades
        const selPres = document.getElementById("prodPresentacion");
        const contBulto = document.getElementById("contenedorUnidadesBulto");
        const inputUnidBulto = document.getElementById("prodUnidadesBulto");
        selPres.addEventListener("change", () => {
            if (selPres.value === "caja" || selPres.value === "bulto") {
                contBulto.classList.remove("d-none");
                if (parseFloat(inputUnidBulto.value) <= 1) inputUnidBulto.value = selPres.value === "caja" ? 12 : 24;
            } else {
                contBulto.classList.add("d-none");
                inputUnidBulto.value = 1;
            }
        });

        // Sin Factura toggle
        const chkSinFactura = document.getElementById("prodSinFactura");
        const inputFactura = document.getElementById("prodNumeroFactura");
        chkSinFactura.addEventListener("change", () => {
            inputFactura.disabled = chkSinFactura.checked;
            if (chkSinFactura.checked) inputFactura.value = "";
        });

        // Previsualización de Imagen
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

        // Generador de Código EAN-13
        const btnGenerarCb = document.getElementById("btnGenerarCb");
        const inputCb = document.getElementById("prodCodigoBarras");
        btnGenerarCb.addEventListener("click", () => {
            inputCb.value = "779" + Math.floor(Math.random() * 1000000000).toString().padStart(9, "0");
        });

        // Escáner de Código de Barras
        let html5Qr = null;
        const btnScan = document.getElementById("btnEscanearCb");
        const modalScan = new bootstrap.Modal(document.getElementById("modalScannerCamara"));

        btnScan.addEventListener("click", () => {
            modalScan.show();
            setTimeout(iniciarCamara, 300);
        });

        function iniciarCamara() {
            if (html5Qr) html5Qr.clear().catch(() => {});
            html5Qr = new Html5Qrcode("qr-reader");
            html5Qr.start(
                { facingMode: "environment" },
                { fps: 15, qrbox: { width: 250, height: 150 } },
                (decodedText) => {
                    inputCb.value = decodedText;
                    html5Qr.stop().then(() => {
                        html5Qr.clear();
                        html5Qr = null;
                    });
                    modalScan.hide();
                },
                () => {}
            ).catch(err => {
                const res = document.getElementById("scannerResultado");
                res.textContent = "Error al abrir cámara: " + err;
                res.classList.remove("d-none");
            });
        }

        document.getElementById("modalScannerCamara").addEventListener("hidden.bs.modal", () => {
            if (html5Qr) {
                html5Qr.stop().then(() => {
                    html5Qr.clear();
                    html5Qr = null;
                }).catch(() => {});
            }
        });

        // Envío del Formulario
        const form = document.getElementById("formProductoStandalone");
        const alertErr = document.getElementById("alertaError");
        const alertOk = document.getElementById("alertaExito");
        const btnSubmit = document.getElementById("btnGuardarProducto");

        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            alertErr.classList.add("d-none");
            alertOk.classList.add("d-none");

            btnSubmit.disabled = true;
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando...`;

            try {
                const formData = new FormData(form);
                const resp = await fetch(endpointGuardar, {
                    method: "POST",
                    headers: {
                        "X-CSRF-Token": document.body.dataset.csrf
                    },
                    body: formData
                });
                const data = await resp.json().catch(() => ({}));

                if (!resp.ok) {
                    throw new Error(data.error || "No se pudo guardar el producto.");
                }

                alertOk.textContent = data.mensaje || "¡Producto guardado exitosamente!";
                alertOk.classList.remove("d-none");

                setTimeout(() => {
                    window.location.href = "admin.php";
                }, 1000);

            } catch (err) {
                alertErr.textContent = err.message;
                alertErr.classList.remove("d-none");
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        });
    </script>
</body>
</html>
