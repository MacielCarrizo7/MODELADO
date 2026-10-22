<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["vendedor"]);
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Vendedor") . " " . ($_SESSION["usuario_apellido"] ?? ""));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Control Stock">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.json">
    <title>Panel de ventas | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="assets/estilos.css" rel="stylesheet">
    <!-- Librerías para Códigos de Barra, Escáner y Códigos QR -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</head>
<body data-rol="vendedor" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= htmlspecialchars((string)($_SESSION['usuario_limite_descuento'] ?? 15), ENT_QUOTES, 'UTF-8') ?>">
    <nav class="navbar app-navbar sticky-top py-2 px-3">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <!-- Título Dinámico: Nombre o Rol del Vendedor -->
            <div class="d-flex align-items-center gap-2">
                <div>
                    <span class="d-block fw-bold fs-5 lh-1 text-dark"><?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></span>
                    <span class="badge text-bg-success-subtle text-success border border-success-subtle rounded-pill small" style="font-size: 0.72rem;">Ventas</span>
                </div>
            </div>

            <!-- Botones Derecha: Escáner y Silueta de Usuario con Menú -->
            <div class="d-flex align-items-center gap-2">
                <!-- Botón Escáner Global -->
                <button class="btn btn-nav-icon" type="button" id="btnAbrirScannerGlobal" title="Escanear código con cámara" aria-label="Escanear código">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" x2="17" y1="12" y2="12"/></svg>
                </button>

                <!-- Menú Desplegable con Silueta de Usuario -->
                <div class="dropdown">
                    <button class="btn btn-user-avatar" type="button" id="dropdownMenuVendedor" data-bs-toggle="dropdown" aria-expanded="false" title="Menú de Usuario">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-2 py-2" style="min-width: 250px;" aria-labelledby="dropdownMenuVendedor">
                        <li class="px-3 py-2 border-bottom mb-1">
                            <div class="fw-bold small text-dark"><?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">Vendedor Autorizado</div>
                        </li>
                        <li><h6 class="dropdown-header text-uppercase small text-muted">Accesos Rápidos</h6></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2.5" href="catalogo.php">
                                <svg class="text-primary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                <span>Catálogo Visual de Productos</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2.5" href="venta_form.php">
                                <svg class="text-success" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                                <span>Punto de Venta (POS)</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2.5 text-danger fw-semibold" href="logout.php">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                <span>Cerrar Sesión</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-3 py-md-4">
        <?php if (isset($_GET["mfa_configurado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                Tu autenticador se configuró correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Acciones Rápidas Didácticas (Salida / Consulta) -->
        <section class="action-cards-container" aria-label="Acciones Rápidas">
            <!-- Botón Gigante: Salida / Venta -->
            <a href="venta_form.php" class="action-card-btn btn-venta shadow-sm">
                <div class="action-card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                </div>
                <div class="action-card-body">
                    <h3>Nueva Venta</h3>
                    <p>Registrar salida rápida en POS</p>
                </div>
            </a>

            <!-- Botón Gigante: Catálogo Visual / Consulta -->
            <a href="catalogo.php" class="action-card-btn btn-ingreso shadow-sm">
                <div class="action-card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                </div>
                <div class="action-card-body">
                    <h3>Catálogo Visual</h3>
                    <p>Consultar stock, fotos y precios</p>
                </div>
            </a>
        </section>

        <!-- Navegación Modular por Pestañas -->
        <div class="mb-4">
            <ul class="nav nav-tabs-app" id="vendedorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-vendedor-resumen-btn" data-bs-toggle="tab" data-bs-target="#pestana-resumen" type="button" role="tab" aria-controls="pestana-resumen" aria-selected="true">
                        <span>Resumen</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-productos-btn" data-bs-toggle="tab" data-bs-target="#pestana-productos" type="button" role="tab" aria-controls="pestana-productos" aria-selected="false">
                        <span>Inventario y Vencimientos</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vendedor-ventas-btn" data-bs-toggle="tab" data-bs-target="#pestana-ventas" type="button" role="tab" aria-controls="pestana-ventas" aria-selected="false">
                        <span>Registro de Ventas</span>
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

                <div class="seccion-card">
                    <h2 class="h5 fw-bold mb-3">Rotación de Stock (FIFO)</h2>
                    <p class="texto-secundario mb-3">Filtrar productos según urgencia de vencimiento:</p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="rojo" title="Filtrar productos próximos a vencer">
                            <span class="badge-vencimiento vencido">Rojo: Crítico</span>
                            <span class="text-muted">Prioridad de venta</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="amarillo" title="Filtrar productos con rotación intermedia">
                            <span class="badge-vencimiento vence-pronto">Amarillo: Próximo</span>
                            <span class="text-muted">Rotación activa</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="verde" title="Filtrar productos vigentes">
                            <span class="badge-vencimiento vigente">Verde: Normal</span>
                            <span class="text-muted">Stock vigente</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: Inventario -->
            <div class="tab-pane fade" id="pestana-productos" role="tabpanel" aria-labelledby="tab-vendedor-productos-btn">
                <section class="seccion-card" aria-labelledby="titulo-productos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Inventario</p>
                            <h2 id="titulo-productos" class="h4 fw-bold mb-0">Productos Disponibles</h2>
                        </div>
                        <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoTabla" title="Buscar con lector o cámara">Escanear código</button>
                    </div>

                    <!-- Filtros de Inventario con Semáforo FIFO, Proveedor y Código de Barras -->
                    <form id="formFiltrosProductos" class="row g-3 align-items-end mb-3 p-3 bg-light rounded-3 border" onsubmit="return false;">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProductoBusqueda" class="form-label">Buscar producto / código</label>
                            <div class="input-group">
                                <input type="text" id="filtroProductoBusqueda" name="busqueda" class="form-control" placeholder="Nombre, código...">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearFiltro" title="Escanear con cámara">Escanear</button>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProductoProveedor" class="form-label">Filtrar por Proveedor</label>
                            <select id="filtroProductoProveedor" name="proveedor" class="form-select">
                                <option value="">Todos los proveedores</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroProductoSemaforo" class="form-label">Vencimiento</label>
                            <select id="filtroProductoSemaforo" name="semaforo" class="form-select">
                                <option value="">Todos</option>
                                <option value="rojo">Rojo (Crítico)</option>
                                <option value="amarillo">Amarillo (Próximo)</option>
                                <option value="verde">Verde (Normal)</option>
                                <option value="sin_fecha">Sin fecha</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroProductoPresentacion" class="form-label">Presentación</label>
                            <select id="filtroProductoPresentacion" name="presentacion" class="form-select">
                                <option value="">Todas</option>
                                <option value="unidad">Unidades</option>
                                <option value="caja">Cajas</option>
                                <option value="bulto">Bultos</option>
                                <option value="Litros">Litros</option>
                                <option value="Kg">Kg</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-12 col-lg-2">
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
                        <a class="btn btn-primary" href="venta_form.php">+ Registrar venta</a>
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
                    <h2 id="tituloModalScanner" class="modal-title fs-5 fw-bold">Escanear Código de Barras</h2>
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
                        <input type="number" inputmode="numeric" min="1" id="modificarCantidad" name="cantidad" class="form-control" required>
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

    <!-- Modal Credencial QR del Cliente -->
    <div class="modal fade" id="modalQrCliente" tabindex="-1" aria-labelledby="tituloModalQrCliente" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="tituloModalQrCliente" class="modal-title fs-5 fw-bold">Credencial Digital con QR</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="tarjeta-credencial-qr mx-auto" style="max-width: 340px;">
                        <div class="small text-uppercase tracking-wide opacity-75">Control Stock - Cliente</div>
                        <h3 class="h5 fw-bold mt-1 mb-0" id="modalQrClienteNombre">Nombre del Cliente</h3>
                        <p class="small text-white-50 mb-2" id="modalQrClienteDni">DNI: —</p>
                        
                        <div class="qr-box">
                            <div id="modalQrClienteContenedor"></div>
                        </div>

                        <div class="small font-monospace opacity-75" id="modalQrClienteId">CLIENTE:0</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnImprimirQrCliente">Imprimir Credencial</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón Flotante de Acción Rápida (FAB Mobile) -->
    <a href="venta_form.php" class="mobile-fab" title="Registrar Venta Rápida" aria-label="Nueva Venta">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    </a>

    <!-- Barra de Navegación Inferior (Bottom Navigation) Mobile -->
    <nav class="app-bottom-nav" aria-label="Navegación Móvil">
        <a href="vendedor.php" class="app-bottom-nav-item active" onclick="const b=document.getElementById('tab-vendedor-resumen-btn');if(b)b.click();">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Inicio</span>
        </a>
        <a href="#pestana-productos" class="app-bottom-nav-item" onclick="const b=document.getElementById('tab-vendedor-productos-btn');if(b)b.click();return false;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span>Inventario</span>
        </a>
        <a href="venta_form.php" class="app-bottom-nav-item text-primary fw-bold">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <span>Vender</span>
        </a>
        <a href="#pestana-ventas" class="app-bottom-nav-item" onclick="const b=document.getElementById('tab-vendedor-ventas-btn');if(b)b.click();return false;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Historial</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/panel.js"></script>
</body>
</html>
