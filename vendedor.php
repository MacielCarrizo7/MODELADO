<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["vendedor"]);
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Vendedor") . " " . ($_SESSION["usuario_apellido"] ?? ""));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de ventas | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <!-- Librerías para Códigos de Barra y Escáner -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body data-rol="vendedor" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="vendedor.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Control Stock</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuVendedor" aria-controls="menuVendedor" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuVendedor">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 pt-3 pt-lg-0">
                    <a class="nav-link nav-link-app active" href="vendedor.php">Panel de Ventas</a>
                    <button class="btn btn-outline-primary btn-sm ms-lg-2" type="button" id="btnAbrirScannerGlobal" title="Escanear código con cámara">📷 Escáner</button>
                    <button class="btn btn-primary btn-sm ms-lg-1" type="button" data-bs-toggle="modal" data-bs-target="#modalVenta">+ Registrar venta</button>
                    <a class="btn btn-outline-danger btn-sm ms-lg-1" href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        <?php if (isset($_GET["mfa_configurado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                Tu autenticador se configuró correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="hero-panel p-4 p-md-5 mb-4">
            <div class="hero-contenido">
                <p class="etiqueta text-white-50 mb-2">Panel de Ventas y Mostrador</p>
                <h1 class="display-6 fw-bold mb-2">Hola, <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></h1>
                <p class="lead text-white-50 mb-0">Consultá disponibilidad de productos con código de barras, rotación FIFO (45/90 días), trazabilidad y atención a clientes.</p>
            </div>
        </section>

        <!-- Navegación Modular por Pestañas -->
        <div class="mb-4">
            <ul class="nav nav-tabs-app" id="vendedorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-vendedor-resumen-btn" data-bs-toggle="tab" data-bs-target="#pestana-resumen" type="button" role="tab" aria-controls="pestana-resumen" aria-selected="true">
                        <span>📊 Resumen</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-productos-btn" data-bs-toggle="tab" data-bs-target="#pestana-productos" type="button" role="tab" aria-controls="pestana-productos" aria-selected="false">
                        <span>📦 Inventario y Vencimientos</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-ventas-btn" data-bs-toggle="tab" data-bs-target="#pestana-ventas" type="button" role="tab" aria-controls="pestana-ventas" aria-selected="false">
                        <span>💳 Registro de Ventas</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-ingresos-btn" data-bs-toggle="tab" data-bs-target="#pestana-ingresos" type="button" role="tab" aria-controls="pestana-ingresos" aria-selected="false">
                        <span>📋 Ingresos de Mercadería</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-proveedores-btn" data-bs-toggle="tab" data-bs-target="#pestana-proveedores" type="button" role="tab" aria-controls="pestana-proveedores" aria-selected="false">
                        <span>🏢 Proveedores</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-barcodes-btn" data-bs-toggle="tab" data-bs-target="#pestana-barcodes" type="button" role="tab" aria-controls="pestana-barcodes" aria-selected="false">
                        <span>🏷️ Códigos de Barra</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="vendedorTabsContent">
            
            <!-- Pestaña 1: Resumen -->
            <div class="tab-pane fade show active" id="pestana-resumen" role="tabpanel" aria-labelledby="tab-vendedor-resumen-btn">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Productos disponibles</span>
                            <div id="resumenProductos" class="stat-valor">—</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Unidades en stock</span>
                            <div id="resumenStock" class="stat-valor">—</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Ventas totales</span>
                            <div id="resumenVentas" class="stat-valor">—</div>
                        </div>
                    </div>
                </div>

                <!-- Bandeja de Solicitudes de Atención de Clientes -->
                <section class="seccion-card mb-4" aria-labelledby="titulo-solicitudes-vendedor">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <h2 id="titulo-solicitudes-vendedor" class="h5 fw-bold mb-0">🤝 Solicitudes de Atención de Clientes</h2>
                            <span id="badgeSolicitudesPendientes" class="badge rounded-pill text-bg-danger">0</span>
                        </div>
                        <small class="text-muted">Clientes que requieren asistencia directa</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Mensaje / Consulta</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="solicitudesAtencionBody"></tbody>
                        </table>
                    </div>
                </section>

                <div class="seccion-card">
                    <h2 class="h5 fw-bold mb-3">Rotación de Stock (Primeros en Vencer / FIFO)</h2>
                    <p class="texto-secundario mb-3">Hacé clic en cualquier estado para filtrar los productos según su urgencia:</p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="rojo" title="Filtrar productos próximos a vencer">
                            <span class="badge-vencimiento vencido">🔴 Rojo: ≤ 45 días</span>
                            <span class="text-muted">Prioridad urgente de venta</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="amarillo" title="Filtrar productos con rotación intermedia">
                            <span class="badge-vencimiento vence-pronto">🟡 Amarillo: 46 a 90 días</span>
                            <span class="text-muted">Atención y rotación activa</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="verde" title="Filtrar productos vigentes">
                            <span class="badge-vencimiento vigente">🟢 Verde: > 90 días</span>
                            <span class="text-muted">Stock holgado y vigente</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: Inventario -->
            <div class="tab-pane fade" id="pestana-productos" role="tabpanel" aria-labelledby="tab-vendedor-productos-btn">
                <section class="seccion-card" aria-labelledby="titulo-productos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Inventario en tiempo real</p>
                            <h2 id="titulo-productos" class="h4 fw-bold mb-0">Productos Disponibles (Orden FIFO)</h2>
                        </div>
                        <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoTabla" title="Buscar con lector o cámara">📷 Escanear código</button>
                    </div>

                    <!-- Filtros de Inventario con Semáforo FIFO y Código de Barras -->
                    <form id="formFiltrosProductos" class="row g-3 align-items-end mb-3 p-3 bg-light rounded-3 border" onsubmit="return false;">
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label for="filtroProductoBusqueda" class="form-label">Buscar producto / código / proveedor</label>
                            <div class="input-group">
                                <input type="text" id="filtroProductoBusqueda" name="busqueda" class="form-control" placeholder="🔍 Nombre, código de barras...">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearFiltro" title="Escanear con cámara">📷</button>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProductoSemaforo" class="form-label">Semáforo de vencimiento (FIFO)</label>
                            <select id="filtroProductoSemaforo" name="semaforo" class="form-select">
                                <option value="">Todos los vencimientos</option>
                                <option value="rojo">🔴 Rojo (≤ 45 días)</option>
                                <option value="amarillo">🟡 Amarillo (46 a 90 días)</option>
                                <option value="verde">🟢 Verde (> 90 días)</option>
                                <option value="sin_fecha">⚪ Sin fecha</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProductoPresentacion" class="form-label">Presentación</label>
                            <select id="filtroProductoPresentacion" name="presentacion" class="form-select">
                                <option value="">Todas</option>
                                <option value="unidad">Unidades</option>
                                <option value="caja">Cajas</option>
                                <option value="bulto">Bultos</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <button type="button" id="limpiarFiltrosProductos" class="btn btn-outline-secondary w-100">Limpiar</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Cód. / Barras</th>
                                    <th>Producto</th>
                                    <th>Presentación</th>
                                    <th>Proveedor</th>
                                    <th>Vencimiento (FIFO)</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 3: Ventas -->
            <div class="tab-pane fade" id="pestana-ventas" role="tabpanel" aria-labelledby="tab-vendedor-ventas-btn">
                <section class="seccion-card" aria-labelledby="titulo-ventas">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Actividad Comercial</p>
                            <h2 id="titulo-ventas" class="h4 fw-bold mb-0">Historial de Ventas</h2>
                        </div>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalVenta">+ Registrar venta</button>
                    </div>

                    <!-- Filtros -->
                    <form id="formFiltrosVentas" class="row g-3 align-items-end mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProducto" class="form-label">Producto</label>
                            <select id="filtroProducto" name="producto_id" class="form-select">
                                <option value="">Todos los productos</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroCliente" class="form-label">Cliente</label>
                            <select id="filtroCliente" name="cliente_id" class="form-select">
                                <option value="">Todos los clientes</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <button type="button" id="limpiarFiltros" class="btn btn-outline-secondary">Limpiar filtros</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio unit.</th>
                                    <th>Descuento</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ventasBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 4: Ingresos -->
            <div class="tab-pane fade" id="pestana-ingresos" role="tabpanel" aria-labelledby="tab-vendedor-ingresos-btn">
                <section class="seccion-card" aria-labelledby="titulo-ingresos">
                    <div class="mb-3">
                        <p class="etiqueta text-primary mb-1">Auditoría de Entradas</p>
                        <h2 id="titulo-ingresos" class="h4 fw-bold mb-0">Ingresos de Mercadería y Lotes</h2>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Empaque / Unidades</th>
                                    <th>Proveedor</th>
                                    <th>Vencimiento</th>
                                    <th>Responsable</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="ingresosBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 5: Directorio de Proveedores -->
            <div class="tab-pane fade" id="pestana-proveedores" role="tabpanel" aria-labelledby="tab-vendedor-proveedores-btn">
                <section class="seccion-card" aria-labelledby="titulo-proveedores">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                        <div>
                            <p class="etiqueta text-primary mb-1">Cadena de Suministro</p>
                            <h2 id="titulo-proveedores" class="h4 fw-bold mb-0">Directorio de Proveedores</h2>
                        </div>
                        <div class="col-12 col-sm-4">
                            <input type="text" id="buscadorProveedores" class="form-control" placeholder="🔍 Buscar proveedor...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Proveedor / Empresa</th>
                                    <th>CUIT / CUIL</th>
                                    <th>Teléfono</th>
                                    <th>Correo Electrónico</th>
                                    <th>Dirección</th>
                                    <th>Catálogo</th>
                                </tr>
                            </thead>
                            <tbody id="proveedoresBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 6: Generador de Códigos de Barra -->
            <div class="tab-pane fade" id="pestana-barcodes" role="tabpanel" aria-labelledby="tab-vendedor-barcodes-btn">
                <section class="seccion-card" aria-labelledby="titulo-barcodes">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                        <div>
                            <p class="etiqueta text-primary mb-1">Etiquetado y Trazabilidad</p>
                            <h2 id="titulo-barcodes" class="h4 fw-bold mb-0">Generador de Códigos de Barra</h2>
                        </div>
                        <button class="btn btn-success" type="button" id="btnImprimirEtiqueta">🖨️ Imprimir Etiqueta</button>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <h3 class="h6 fw-bold mb-3">Configurar datos de la etiqueta</h3>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="barcodeSelectorProducto">Seleccionar producto existente (opcional)</label>
                                    <select class="form-select" id="barcodeSelectorProducto">
                                        <option value="">-- Ingreso manual / Nuevo producto --</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="barcodeInputCodigo">Código de Barras *</label>
                                    <div class="input-group">
                                        <input type="text" id="barcodeInputCodigo" class="form-control" placeholder="Ej: 7791234567890">
                                        <button class="btn btn-outline-secondary" type="button" id="btnGenerarCodigoRandom" title="Generar código aleatorio">🎲 Generar</button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="barcodeInputNombre">Nombre en etiqueta</label>
                                    <input type="text" id="barcodeInputNombre" class="form-control" placeholder="Ej: Arroz Largo Fino 1kg">
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label" for="barcodeInputPrecio">Precio a mostrar ($)</label>
                                        <input type="number" step="0.01" id="barcodeInputPrecio" class="form-control" placeholder="0.00">
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label" for="barcodeInputFormato">Tipo de código</label>
                                        <select id="barcodeInputFormato" class="form-select">
                                            <option value="CODE128">CODE128 (Universal)</option>
                                            <option value="EAN13">EAN-13 (13 dígitos)</option>
                                            <option value="CODE39">CODE39</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="etiqueta-barcode-card h-100 d-flex flex-column justify-content-center align-items-center">
                                <h3 class="h6 fw-bold text-muted mb-3">Vista Previa de la Etiqueta</h3>
                                
                                <div id="seccionImpresionEtiqueta">
                                    <div class="etiqueta-print-box shadow-sm">
                                        <div class="etiqueta-print-empresa">Control Stock</div>
                                        <div class="etiqueta-print-nombre" id="previewEtiquetaNombre">Nombre del Producto</div>
                                        <svg id="previewBarcodeSvg" class="barcode-svg my-2"></svg>
                                        <div class="etiqueta-print-precio" id="previewEtiquetaPrecio">$ 0,00</div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button class="btn btn-outline-primary btn-sm" type="button" id="btnCopiarCodigoBarras">📋 Copiar código</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </main>

    <!-- Modal Historial de Movimientos de Producto -->
    <div class="modal fade" id="modalHistorialProducto" tabindex="-1" aria-labelledby="tituloModalHistorialProducto" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="etiqueta text-primary mb-1">Auditoría y Trazabilidad</p>
                        <h2 id="tituloModalHistorialProducto" class="modal-title fs-5 fw-bold">Historial de Movimientos del Producto</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm-6">
                                <h3 class="h6 fw-bold mb-1" id="historialProductoNombre">—</h3>
                                <div class="text-muted small" id="historialProductoDetalles">Cód: — | Barras: —</div>
                            </div>
                            <div class="col-6 col-sm-3 text-sm-center">
                                <span class="text-muted small d-block">Stock Actual</span>
                                <strong class="fs-5 text-primary" id="historialProductoStock">—</strong>
                            </div>
                            <div class="col-6 col-sm-3 text-sm-end">
                                <span class="text-muted small d-block">Precio Vigente</span>
                                <strong class="fs-5 text-success" id="historialProductoPrecio">—</strong>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Tipo de Movimiento</th>
                                    <th>Detalle / Operación</th>
                                    <th>Variación Stock</th>
                                    <th>Responsable</th>
                                </tr>
                            </thead>
                            <tbody id="historialProductoBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Escáner de Cámara Web / Móvil -->
    <div class="modal fade" id="modalScannerCamara" tabindex="-1" aria-labelledby="tituloModalScanner" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="tituloModalScanner" class="modal-title fs-5 fw-bold">📷 Escanear Código de Barras</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="text-muted small mb-3">Apuntá con la cámara hacia el código de barras del producto.</p>
                    <div id="contenedorLectorCamara" class="p-2 mb-3">
                        <div id="qr-reader"></div>
                    </div>
                    <div id="scannerResultado" class="alert alert-info d-none mb-0 py-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="btnCerrarScanner">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Venta -->
    <div class="modal fade" id="modalVenta" tabindex="-1" aria-labelledby="tituloModalVenta" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formVenta">
                    <div class="modal-header">
                        <h2 id="tituloModalVenta" class="modal-title fs-5 fw-bold">Registrar venta</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorVenta" class="alert alert-danger d-none"></div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="ventaCliente">Cliente *</label>
                            <select class="form-select" id="ventaCliente" name="cliente_id" required></select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ventaProducto">Producto *</label>
                            <div class="input-group">
                                <select class="form-select" id="ventaProducto" name="producto_id" required></select>
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearProductoVenta" title="Escanear con cámara">📷</button>
                            </div>
                            <small id="ventaInfoEmpaque" class="text-primary small d-none"></small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="ventaTipoVenta">Tipo de venta *</label>
                                <select class="form-select" id="ventaTipoVenta" name="tipo_venta">
                                    <option value="unidad">Unidades sueltas</option>
                                    <option value="caja">Cajas</option>
                                    <option value="bulto">Bultos</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="ventaCantidad">Cantidad *</label>
                                <input class="form-control" id="ventaCantidad" name="cantidad" type="number" min="1" value="1" required>
                            </div>
                        </div>

                        <!-- Selector de Descuentos Opcionales -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-7">
                                <label class="form-label" for="ventaDescuentoPorcentaje">Descuento al cliente</label>
                                <select class="form-select" id="ventaDescuentoPorcentaje" name="descuento_porcentaje">
                                    <option value="0" selected>0% (Sin descuento)</option>
                                    <option value="5">5% de descuento</option>
                                    <option value="10">10% de descuento</option>
                                    <option value="15">15% de descuento</option>
                                    <option value="20">20% de descuento</option>
                                    <option value="25">25% de descuento</option>
                                    <option value="custom">Personalizado...</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-5">
                                <label class="form-label" for="ventaDescuentoCustom">% Descuento</label>
                                <input class="form-control d-none" id="ventaDescuentoCustom" type="number" min="0" max="100" step="0.5" placeholder="Ej: 12">
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Unidades a descontar:</span>
                                <strong id="ventaResumenUnidades" class="text-dark">1 un.</strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Subtotal:</span>
                                <span id="ventaResumenSubtotal">$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span>Descuento aplicado:</span>
                                <span id="ventaResumenDescuento" class="text-danger">$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between fs-5 fw-bold text-primary pt-2 border-top">
                                <span>Total a cobrar:</span>
                                <span id="ventaResumenTotal">$ 0,00</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar venta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modificar Venta -->
    <div class="modal fade" id="modalModificarVenta" tabindex="-1" aria-labelledby="tituloModificarVenta" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formModificarVenta">
                    <div class="modal-header">
                        <h2 id="tituloModificarVenta" class="modal-title fs-5 fw-bold">Modificar cantidad de venta</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorModificarVenta" class="alert alert-danger d-none"></div>
                        <input type="hidden" name="venta_id" id="modificarVentaId">
                        <input type="hidden" name="accion" value="modificar_cantidad">
                        <label for="modificarCantidad" class="form-label">Nueva cantidad</label>
                        <input type="number" min="1" id="modificarCantidad" name="cantidad" class="form-control" required>
                        <label for="motivoModificacion" class="form-label mt-3">Motivo (opcional)</label>
                        <textarea id="motivoModificacion" name="motivo" class="form-control" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-primary">Guardar cambio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cancelar Venta -->
    <div class="modal fade" id="modalCancelarVenta" tabindex="-1" aria-labelledby="tituloCancelarVenta" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formCancelarVenta">
                    <div class="modal-header">
                        <h2 id="tituloCancelarVenta" class="modal-title fs-5 fw-bold">Cancelar venta</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorCancelarVenta" class="alert alert-danger d-none"></div>
                        <input type="hidden" name="venta_id" id="cancelarVentaId">
                        <input type="hidden" name="accion" value="cancelar">
                        <p class="texto-secundario">La venta permanecerá en el historial como CANCELADA y el stock será devuelto automáticamente.</p>
                        <label for="motivoCancelacion" class="form-label">Motivo (opcional)</label>
                        <textarea id="motivoCancelacion" name="motivo" class="form-control" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/panel.js"></script>
</body>
</html>
