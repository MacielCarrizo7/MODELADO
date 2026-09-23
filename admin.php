<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

try {
    $cantidadUsuarios = FirestoreConexion::obtenerFirestore()->contarDocumentos("usuarios");
} catch (Throwable $e) {
    error_log("Error al contar usuarios en Firestore: " . $e->getMessage());
    $cantidadUsuarios = 0;
}
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Administrador") . " " . ($_SESSION["usuario_apellido"] ?? ""));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title>Panel administrativo | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <!-- Librerías para Códigos de Barra, Escáner y Códigos QR -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= htmlspecialchars((string)($_SESSION['usuario_limite_descuento'] ?? 100), ENT_QUOTES, 'UTF-8') ?>">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Control Stock</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuAdmin" aria-controls="menuAdmin" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuAdmin">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 pt-3 pt-lg-0">
                    <a class="nav-link nav-link-app active" href="admin.php">Panel Principal</a>
                    <a class="nav-link nav-link-app" href="catalogo.php">Catálogo Visual</a>
                    <a class="nav-link nav-link-app" href="categorias.php">Categorías</a>
                    <a class="nav-link nav-link-app" href="registro.php">Usuarios</a>
                    <button class="btn btn-outline-primary btn-sm ms-lg-2" type="button" id="btnAbrirScannerGlobal" title="Escanear código de barras con cámara">📷 Escáner</button>
                    <a class="btn btn-outline-primary btn-sm ms-lg-1" href="venta_form.php">🛒 Punto de Venta</a>
                    <a class="btn btn-primary btn-sm ms-lg-1" href="producto_form.php">📦 + Ingreso de Productos</a>
                    <a class="btn btn-outline-danger btn-sm ms-lg-2" href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        <?php if (isset($_GET["mfa_configurado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                Tu autenticador de segundo factor se configuró correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="hero-panel p-4 p-md-5 mb-4">
            <div class="hero-contenido">
                <p class="etiqueta text-white-50 mb-2">Panel Administrativo Cloud</p>
                <h1 class="display-6 fw-bold mb-2">Hola, <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></h1>
                <p class="lead text-white-50 mb-0">Control integral de inventario con códigos de barra, trazabilidad de movimientos, directorio de proveedores y política FIFO.</p>
            </div>
        </section>

        <!-- Navegación Modular por Pestañas -->
        <div class="mb-4">
            <ul class="nav nav-tabs-app" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-resumen-btn" data-bs-toggle="tab" data-bs-target="#pestana-resumen" type="button" role="tab" aria-controls="pestana-resumen" aria-selected="true">
                        <span>📊 Resumen General</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-productos-btn" data-bs-toggle="tab" data-bs-target="#pestana-productos" type="button" role="tab" aria-controls="pestana-productos" aria-selected="false">
                        <span>📦 Inventario y Lotes</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-ventas-btn" data-bs-toggle="tab" data-bs-target="#pestana-ventas" type="button" role="tab" aria-controls="pestana-ventas" aria-selected="false">
                        <span>💳 Registro de Ventas</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-ingresos-btn" data-bs-toggle="tab" data-bs-target="#pestana-ingresos" type="button" role="tab" aria-controls="pestana-ingresos" aria-selected="false">
                        <span>📋 Kardex de Ingresos</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-proveedores-btn" data-bs-toggle="tab" data-bs-target="#pestana-proveedores" type="button" role="tab" aria-controls="pestana-proveedores" aria-selected="false">
                        <span>🏢 Proveedores</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-barcodes-btn" data-bs-toggle="tab" data-bs-target="#pestana-barcodes" type="button" role="tab" aria-controls="pestana-barcodes" aria-selected="false">
                        <span>🏷️ Códigos de Barra</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Contenido de las Pestañas -->
        <div class="tab-content" id="adminTabsContent">
            
            <!-- Pestaña 1: Resumen General -->
            <div class="tab-pane fade show active" id="pestana-resumen" role="tabpanel" aria-labelledby="tab-resumen-btn">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Productos cargados</span>
                            <div id="resumenProductos" class="stat-valor">—</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Unidades en stock</span>
                            <div id="resumenStock" class="stat-valor">—</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Ventas realizadas</span>
                            <div id="resumenVentas" class="stat-valor">—</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="stat-card">
                            <span class="texto-secundario small fw-semibold">Proveedores registrados</span>
                            <div id="resumenProveedores" class="stat-valor">—</div>
                        </div>
                    </div>
                </div>

                <!-- Bandeja de Solicitudes de Atención de Clientes -->
                <section class="seccion-card mb-4" aria-labelledby="titulo-solicitudes">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <h2 id="titulo-solicitudes" class="h5 fw-bold mb-0">🤝 Solicitudes de Atención de Clientes</h2>
                            <span id="badgeSolicitudesPendientes" class="badge rounded-pill text-bg-danger">0</span>
                        </div>
                        <small class="text-muted">Clientes que solicitaron asistencia directa de un vendedor</small>
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

                <div class="row g-4">
                    <div class="col-12 col-lg-7">
                        <div class="seccion-card h-100">
                            <h2 class="h5 fw-bold mb-3">Accesos y Acciones Rápidas</h2>
                            <p class="texto-secundario small mb-4">Realizá operaciones clave de forma inmediata desde un solo clic.</p>
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-primary w-100 p-3 text-start d-flex align-items-center gap-3" href="producto_form.php">
                                        <span class="fs-4">📦</span>
                                        <div>
                                            <div class="fw-bold">Ingreso de Productos (1 a 50)</div>
                                            <small class="text-muted">Carga individual o multiproducto en lote</small>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-success w-100 p-3 text-start d-flex align-items-center gap-3" href="venta_form.php">
                                        <span class="fs-4">🛒</span>
                                        <div>
                                            <div class="fw-bold">Punto de Venta (POS)</div>
                                            <small class="text-muted">Carrito dinámico y escáner QR</small>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-info w-100 p-3 text-start d-flex align-items-center gap-3" href="categorias.php">
                                        <span class="fs-4">🏷️</span>
                                        <div>
                                            <div class="fw-bold">Gestión de Categorías</div>
                                            <small class="text-muted">Crear y organizar rubros</small>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-dark w-100 p-3 text-start d-flex align-items-center gap-3" href="catalogo.php">
                                        <span class="fs-4">👁️</span>
                                        <div>
                                            <div class="fw-bold">Catálogo Visual / Menú</div>
                                            <small class="text-muted">Fichas con fotos y edición rápida</small>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-secondary w-100 p-3 text-start d-flex align-items-center gap-3" href="proveedor_form.php">
                                        <span class="fs-4">🏢</span>
                                        <div>
                                            <div class="fw-bold">Nuevo Proveedor</div>
                                            <small class="text-muted">Alta, CUIT y contacto</small>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-warning w-100 p-3 text-start d-flex align-items-center gap-3" href="registro.php">
                                        <span class="fs-4">👥</span>
                                        <div>
                                            <div class="fw-bold">Usuarios y Permisos</div>
                                            <small class="text-muted">Límites de descuento y roles</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="seccion-card h-100">
                            <h2 class="h5 fw-bold mb-3">Semáforo de Política FIFO (Rotación)</h2>
                            <p class="texto-secundario small mb-3">Hacé clic en cualquier estado para filtrar los productos automáticamente:</p>
                            <div class="d-flex flex-column gap-2 small">
                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light" data-filtro-semaforo="rojo" title="Filtrar productos próximos a vencer">
                                    <span class="badge-vencimiento vencido">🔴 Rojo: ≤ 45 días</span>
                                    <span class="text-muted">Urgencia alta de venta / merma</span>
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
                </div>
            </div>

            <!-- Pestaña 2: Inventario de Productos -->
            <div class="tab-pane fade" id="pestana-productos" role="tabpanel" aria-labelledby="tab-productos-btn">
                <section class="seccion-card" aria-labelledby="titulo-productos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Inventario y Lotes</p>
                            <h2 id="titulo-productos" class="h4 fw-bold mb-0">Listado de Productos (Orden FIFO)</h2>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoTabla" title="Buscar con lector de código de barras o cámara">📷 Escanear código</button>
                            <a class="btn btn-primary" href="producto_form.php">📦 + Ingreso de Productos (1 a 50)</a>
                        </div>
                    </div>

                    <!-- Filtros de Inventario con Semáforo FIFO, Proveedor y Código de Barras -->
                    <form id="formFiltrosProductos" class="row g-3 align-items-end mb-3 p-3 bg-light rounded-3 border" onsubmit="return false;">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProductoBusqueda" class="form-label">Buscar producto / código</label>
                            <div class="input-group">
                                <input type="text" id="filtroProductoBusqueda" name="busqueda" class="form-control" placeholder="🔍 Nombre, código de barras...">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearFiltro" title="Escanear con cámara">📷</button>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="filtroProductoProveedor" class="form-label">Filtrar por Proveedor</label>
                            <select id="filtroProductoProveedor" name="proveedor" class="form-select">
                                <option value="">Todos los proveedores</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroProductoSemaforo" class="form-label">Vencimiento (FIFO)</label>
                            <select id="filtroProductoSemaforo" name="semaforo" class="form-select">
                                <option value="">Todos</option>
                                <option value="rojo">🔴 Rojo: ≤ 45 d</option>
                                <option value="amarillo">🟡 Amarillo: 46-90 d</option>
                                <option value="verde">🟢 Verde: > 90 d</option>
                                <option value="sin_fecha">⚪ Sin fecha</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroProductoPresentacion" class="form-label">Presentación</label>
                            <select id="filtroProductoPresentacion" name="presentacion" class="form-select">
                                <option value="">Todas</option>
                                <option value="unidad">Unidades</option>
                                <option value="caja">Cajas</option>
                                <option value="bulto">Bultos</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-12 col-lg-2">
                            <button type="button" id="limpiarFiltrosProductos" class="btn btn-outline-secondary w-100">Limpiar</button>
                        </div>
                    </form>

                    <!-- Botones de Acceso Rápido por Estado de Semáforo -->
                    <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
                        <span class="text-secondary small fw-semibold">Filtro rápido:</span>
                        <button type="button" class="btn btn-sm btn-outline-dark active" data-boton-semaforo="">Todos</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-boton-semaforo="rojo">🔴 Próximos a vencer (≤ 45 d)</button>
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark" data-boton-semaforo="amarillo">🟡 Rotación intermedia (46-90 d)</button>
                        <button type="button" class="btn btn-sm btn-outline-success" data-boton-semaforo="verde">🟢 Vigentes (> 90 d)</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-boton-semaforo="sin_fecha">⚪ Sin fecha</button>
                    </div>

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
            <div class="tab-pane fade" id="pestana-ventas" role="tabpanel" aria-labelledby="tab-ventas-btn">
                <section class="seccion-card" aria-labelledby="titulo-ventas">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Actividad Comercial</p>
                            <h2 id="titulo-ventas" class="h4 fw-bold mb-0">Historial de Ventas</h2>
                        </div>
                        <a class="btn btn-primary" href="venta_form.php">+ Registrar venta</a>
                    </div>

                    <!-- Filtros avanzados con Filtro de Cliente y Vendedor -->
                    <form id="formFiltrosVentas" class="row g-3 align-items-end mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroProducto" class="form-label">Producto</label>
                            <select id="filtroProducto" name="producto_id" class="form-select">
                                <option value="">Todos los productos</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroCliente" class="form-label">Cliente</label>
                            <select id="filtroCliente" name="cliente_id" class="form-select">
                                <option value="">Todos los clientes</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroVendedor" class="form-label">Vendedor</label>
                            <select id="filtroVendedor" name="vendedor_id" class="form-select">
                                <option value="">Todos los vendedores</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroEstado" class="form-label">Estado</label>
                            <select id="filtroEstado" name="estado" class="form-select">
                                <option value="">Todos</option>
                                <option value="ACTIVA">Activa</option>
                                <option value="MODIFICADA">Modificada</option>
                                <option value="CANCELADA">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <button type="button" id="limpiarFiltros" class="btn btn-outline-secondary">Limpiar filtros</button>
                            <span id="errorFiltros" class="text-danger small align-self-center"></span>
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
                                    <th>Cantidad / Empaque</th>
                                    <th>Precio unit.</th>
                                    <th>Descuento</th>
                                    <th>Total</th>
                                    <th>Vendedor</th>
                                    <th>Estado</th>
                                    <th>Última modif.</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ventasBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 4: Kardex de Ingresos -->
            <div class="tab-pane fade" id="pestana-ingresos" role="tabpanel" aria-labelledby="tab-ingresos-btn">
                <section class="seccion-card" aria-labelledby="titulo-ingresos">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
                        <div>
                            <p class="etiqueta text-primary mb-1">Auditoría de Entradas</p>
                            <h2 id="titulo-ingresos" class="h4 fw-bold mb-0">Kardex / Historial de Ingresos de Mercadería</h2>
                        </div>
                        <span class="badge text-bg-primary fs-6 px-3 py-2" id="resumenIngresos">0</span>
                    </div>

                    <form id="formFiltrosIngresos" class="row g-3 align-items-end mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroIngresoDesde" class="form-label">Desde</label>
                            <input type="date" id="filtroIngresoDesde" name="desde" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroIngresoHasta" class="form-label">Hasta</label>
                            <input type="date" id="filtroIngresoHasta" name="hasta" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroIngresoProducto" class="form-label">Producto</label>
                            <select id="filtroIngresoProducto" name="producto_id" class="form-select">
                                <option value="">Todos los productos</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroIngresoSemaforo" class="form-label">Semáforo FIFO</label>
                            <select id="filtroIngresoSemaforo" name="semaforo" class="form-select">
                                <option value="">Todos</option>
                                <option value="rojo">🔴 Rojo (≤ 45 d)</option>
                                <option value="amarillo">🟡 Amarillo (46-90 d)</option>
                                <option value="verde">🟢 Verde (> 90 d)</option>
                                <option value="sin_fecha">⚪ Sin fecha</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroIngresoProveedor" class="form-label">Proveedor</label>
                            <input type="text" id="filtroIngresoProveedor" name="proveedor" class="form-control" placeholder="Buscar...">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label for="filtroIngresoUsuario" class="form-label">Usuario</label>
                            <select id="filtroIngresoUsuario" name="usuario_id" class="form-select">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <button type="button" id="limpiarFiltrosIngresos" class="btn btn-outline-secondary">Limpiar filtros</button>
                            <span id="errorFiltrosIngresos" class="text-danger small align-self-center"></span>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>N° Factura</th>
                                    <th>Producto</th>
                                    <th>Empaque / Unidades</th>
                                    <th>Proveedor</th>
                                    <th>Vencimiento</th>
                                    <th>Responsable</th>
                                    <th>Motivo / Ajuste</th>
                                </tr>
                            </thead>
                            <tbody id="ingresosBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 5: Directorio y Gestión de Proveedores -->
            <div class="tab-pane fade" id="pestana-proveedores" role="tabpanel" aria-labelledby="tab-proveedores-btn">
                <section class="seccion-card" aria-labelledby="titulo-proveedores">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                        <div>
                            <p class="etiqueta text-primary mb-1">Cadena de Suministro</p>
                            <h2 id="titulo-proveedores" class="h4 fw-bold mb-0">Directorio de Proveedores</h2>
                        </div>
                        <div class="d-flex gap-2 w-100 w-sm-auto">
                            <input type="text" id="buscadorProveedores" class="form-control" placeholder="🔍 Buscar proveedor, CUIT o email...">
                            <a class="btn btn-primary text-nowrap" href="proveedor_form.php">+ Nuevo Proveedor</a>
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
                                    <th>Catálogo Asignado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="proveedoresBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Pestaña 6: Generador e Impresión de Códigos de Barra -->
            <div class="tab-pane fade" id="pestana-barcodes" role="tabpanel" aria-labelledby="tab-barcodes-btn">
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
                                    <small class="text-muted">Admite formatos estándar (EAN-13, CODE128, etc.).</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="barcodeInputNombre">Nombre / Descripción en etiqueta</label>
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

    <!-- Modal Agregar / Modificar Proveedor -->
    <div class="modal fade" id="modalProveedor" tabindex="-1" aria-labelledby="tituloModalProveedor" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formProveedor">
                    <input type="hidden" name="id" id="proveedorId">
                    <div class="modal-header">
                        <h2 id="tituloModalProveedor" class="modal-title fs-5 fw-bold">Registrar nuevo proveedor</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorProveedor" class="alert alert-danger d-none"></div>

                        <div class="mb-3">
                            <label class="form-label" for="proveedorNombre">Nombre o Empresa *</label>
                            <input class="form-control" id="proveedorNombre" name="nombre" maxlength="150" placeholder="Ej: Molinos Río de la Plata S.A." required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="proveedorCuit">CUIT / CUIL</label>
                                <input class="form-control" id="proveedorCuit" name="cuit_cuil" maxlength="30" placeholder="Ej: 30-12345678-9">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="proveedorTelefono">Teléfono de contacto</label>
                                <input class="form-control" id="proveedorTelefono" name="telefono" maxlength="50" placeholder="Ej: +54 9 11 1234-5678">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="proveedorEmail">Correo electrónico</label>
                            <input class="form-control" id="proveedorEmail" name="email" type="email" maxlength="150" placeholder="contacto@proveedor.com">
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="proveedorDireccion">Dirección / Localidad</label>
                            <input class="form-control" id="proveedorDireccion" name="direccion" maxlength="200" placeholder="Ej: Av. Libertador 1234, CABA">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarProveedor">Guardar proveedor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Historial de Movimientos de Producto (Auditoría / Trazabilidad) -->
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
                    <!-- Resumen del Producto Consultado -->
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

                    <!-- Tabla de Movimientos Cronológicos -->
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
                    <p class="text-muted small mb-3">Apuntá con la cámara hacia el código de barras del producto para detectarlo automáticamente.</p>
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

    <!-- Modal Carga Masiva de Productos por Lote -->
    <div class="modal fade" id="modalCargaMasiva" tabindex="-1" aria-labelledby="tituloModalCargaMasiva" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form id="formCargaMasiva" onsubmit="return false;">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4">📦</span>
                            <div>
                                <h2 id="tituloModalCargaMasiva" class="modal-title fs-5 fw-bold mb-0">Carga Masiva de Productos por Lote / Remito</h2>
                                <small class="text-muted">Ingresá múltiples productos asociados a un mismo proveedor en un solo clic.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorCargaMasiva" class="alert alert-danger d-none mb-3"></div>
                        <div id="exitoCargaMasiva" class="alert alert-success d-none mb-3"></div>

                        <!-- Selector de Proveedor y Número de Factura del Lote -->
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-md-5">
                                    <label class="form-label fw-bold" for="masivoProveedor">🏢 Proveedor del Lote *</label>
                                    <select class="form-select" id="masivoProveedor" required>
                                        <option value="">-- Seleccionar proveedor del remito --</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="masivoNumeroFactura">📄 N° Factura / Remito</label>
                                    <input type="text" class="form-control" id="masivoNumeroFactura" placeholder="Ej: FC-A-0001-00123456">
                                </div>
                                <div class="col-12 col-md-3 d-flex align-items-center pt-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="masivoSinFactura">
                                        <label class="form-check-label small" for="masivoSinFactura">Sin factura</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label text-muted small mb-1" for="masivoProveedorTexto">O escribir nombre de nuevo proveedor (opcional)</label>
                                <input type="text" class="form-control form-control-sm" id="masivoProveedorTexto" placeholder="Ej: Distribuidora Central SRL">
                            </div>
                        </div>

                        <!-- Tabla Dinámica de Productos -->
                        <div class="table-responsive border rounded-3 mb-3">
                            <table class="table table-bordered table-hover align-middle mb-0" id="tablaCargaMasiva">
                                <thead class="table-light">
                                    <tr class="small text-muted text-uppercase">
                                        <th style="width: 40px;">#</th>
                                        <th style="min-width: 200px;">Nombre del Producto *</th>
                                        <th style="min-width: 170px;">Código de Barras</th>
                                        <th style="min-width: 120px;">Presentación</th>
                                        <th style="width: 90px;">Unid/Bulto</th>
                                        <th style="width: 100px;">Cant. Lote *</th>
                                        <th style="width: 110px;">Precio ($) *</th>
                                        <th style="min-width: 140px;">Vencimiento (FIFO)</th>
                                        <th style="width: 50px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpoFilasMasivas">
                                    <!-- Filas dinámicas -->
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAgregarFilaMasiva">
                                ➕ Añadir otro producto al lote
                            </button>
                            <div class="p-2 bg-light rounded border small d-flex flex-wrap gap-3 text-secondary" id="resumenLoteMasivo">
                                <span>Total productos: <strong id="resumenLoteTotalProd" class="text-dark">0</strong></span>
                                <span>Unidades físicas: <strong id="resumenLoteTotalUnidades" class="text-dark">0 un.</strong></span>
                                <span>Valor estimado: <strong id="resumenLoteValorTotal" class="text-primary">$ 0,00</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" id="btnGuardarLoteMasivo">
                            ✓ Guardar Todo el Lote
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="tituloModalProducto" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formProducto">
                    <div class="modal-header">
                        <h2 id="tituloModalProducto" class="modal-title fs-5 fw-bold">Agregar nuevo producto</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorProducto" class="alert alert-danger d-none"></div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="productoNombre">Nombre del producto *</label>
                            <input class="form-control" id="productoNombre" name="nombre" maxlength="150" placeholder="Ej: Arroz Largo Fino 1kg" required>
                        </div>

                        <!-- Código de Barras -->
                        <div class="mb-3">
                            <label class="form-label" for="productoCodigoBarras">Código de Barras (opcional)</label>
                            <div class="input-group">
                                <input class="form-control" id="productoCodigoBarras" name="codigo_barras" maxlength="50" placeholder="Ej: 7791234567890">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearCodigoModalAlta" title="Escanear con cámara">📷</button>
                                <button class="btn btn-outline-secondary" type="button" id="btnGenerarCodigoModalAlta" title="Generar código aleatorio">🎲</button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="productoPrecio">Precio unitario ($) *</label>
                                <input class="form-control" id="productoPrecio" name="precio" type="number" min="0.01" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="productoPresentacion">Tipo de presentación *</label>
                                <select class="form-select" id="productoPresentacion" name="presentacion" required>
                                    <option value="unidad">Unidades sueltas</option>
                                    <option value="caja">Cajas</option>
                                    <option value="bulto">Bultos cerrados</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6 d-none" id="contenedorUnidadesBulto">
                                <label class="form-label" for="productoUnidadesBulto">Unidades por empaque *</label>
                                <input class="form-control" id="productoUnidadesBulto" name="unidades_por_bulto" type="number" min="1" value="1">
                                <small class="text-muted">Unidades dentro de cada caja/bulto.</small>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="productoStock">Cantidad a ingresar *</label>
                                <input class="form-control" id="productoStock" name="stock" type="number" min="0" value="0" required>
                                <small class="text-muted">En unidades, cajas o bultos según presentación.</small>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="productoVencimiento">Fecha de vencimiento</label>
                                <input class="form-control" id="productoVencimiento" name="fecha_vencimiento" type="date">
                                <small class="text-muted">Para control de rotación FIFO.</small>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="productoProveedor">Proveedor</label>
                                <select class="form-select" id="productoProveedor" name="proveedor">
                                    <option value="">-- Seleccionar proveedor --</option>
                                </select>
                            </div>
                        </div>

                        <!-- Número de Factura -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-8">
                                <label class="form-label" for="productoNumeroFactura">📄 Número de Factura / Remito</label>
                                <input class="form-control" id="productoNumeroFactura" name="numero_factura" maxlength="60" placeholder="Ej: FC-A-0001-00023456">
                            </div>
                            <div class="col-12 col-sm-4 d-flex align-items-center pt-sm-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="productoSinFactura" name="sin_factura" value="1">
                                    <label class="form-check-label small" for="productoSinFactura">Sin factura</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modificar / Editar Producto -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-labelledby="tituloModalEditarProducto" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formEditarProducto">
                    <input type="hidden" name="id" id="editarProductoId">
                    <div class="modal-header">
                        <h2 id="tituloModalEditarProducto" class="modal-title fs-5 fw-bold">Modificar producto</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorEditarProducto" class="alert alert-danger d-none"></div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="editarProductoNombre">Nombre del producto *</label>
                            <input class="form-control" id="editarProductoNombre" name="nombre" maxlength="150" required>
                        </div>

                        <!-- Código de Barras -->
                        <div class="mb-3">
                            <label class="form-label" for="editarProductoCodigoBarras">Código de Barras</label>
                            <div class="input-group">
                                <input class="form-control" id="editarProductoCodigoBarras" name="codigo_barras" maxlength="50" placeholder="Ej: 7791234567890">
                                <button class="btn btn-outline-secondary" type="button" id="btnEscanearCodigoModalEdicion" title="Escanear con cámara">📷</button>
                                <button class="btn btn-outline-secondary" type="button" id="btnGenerarCodigoModalEdicion" title="Generar código aleatorio">🎲</button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoPrecio">Precio unitario ($) *</label>
                                <input class="form-control" id="editarProductoPrecio" name="precio" type="number" min="0.01" step="0.01" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoPresentacion">Presentación *</label>
                                <select class="form-select" id="editarProductoPresentacion" name="presentacion" required>
                                    <option value="unidad">Unidades sueltas</option>
                                    <option value="caja">Cajas</option>
                                    <option value="bulto">Bultos cerrados</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6 d-none" id="contenedorEditarUnidadesBulto">
                                <label class="form-label" for="editarProductoUnidadesBulto">Unidades por empaque *</label>
                                <input class="form-control" id="editarProductoUnidadesBulto" name="unidades_por_bulto" type="number" min="1" value="1">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoStock">Stock actual (unidades) *</label>
                                <input class="form-control" id="editarProductoStock" name="stock" type="number" min="0" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoVencimiento">Fecha de vencimiento</label>
                                <input class="form-control" id="editarProductoVencimiento" name="fecha_vencimiento" type="date">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="editarProductoProveedor">Proveedor</label>
                                <select class="form-select" id="editarProductoProveedor" name="proveedor">
                                    <option value="">-- Seleccionar proveedor --</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="editarProductoMotivo">Motivo de la corrección / ajuste (opcional)</label>
                            <input class="form-control" id="editarProductoMotivo" name="motivo" maxlength="255" placeholder="Ej: Corrección de precio / reposición">
                        </div>

                        <!-- Número de Factura para Ingreso de Stock -->
                        <div class="row g-3 mb-2">
                            <div class="col-12 col-sm-8">
                                <label class="form-label" for="editarProductoNumeroFactura">📄 N° Factura (si ingresa nuevo stock)</label>
                                <input class="form-control" id="editarProductoNumeroFactura" name="numero_factura" maxlength="60" placeholder="Ej: FC-A-0001-00023456">
                            </div>
                            <div class="col-12 col-sm-4 d-flex align-items-center pt-sm-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editarProductoSinFactura" name="sin_factura" value="1">
                                    <label class="form-check-label small" for="editarProductoSinFactura">Sin factura</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Venta (Carrito Multiproducto, Lector QR de Cliente y Códigos de Barra) -->
    <div class="modal fade" id="modalVenta" tabindex="-1" aria-labelledby="tituloModalVenta" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="formVenta" onsubmit="return false;">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4">🛒</span>
                            <div>
                                <h2 id="tituloModalVenta" class="modal-title fs-5 fw-bold mb-0">Registrar Venta / Carrito de Productos</h2>
                                <small class="text-muted">Añadí uno o más productos al ticket y confirmá la venta en un solo paso.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorVenta" class="alert alert-danger d-none mb-3"></div>
                        <div id="exitoVenta" class="alert alert-success d-none mb-3"></div>
                        
                        <!-- 1. Selección de Cliente con Escáner QR y Búsqueda Manual -->
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-7">
                                    <label class="form-label fw-bold" for="ventaCliente">👤 Cliente *</label>
                                    <select class="form-select" id="ventaCliente" name="cliente_id" required>
                                        <option value="">-- Seleccionar cliente o escanear QR --</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-5 d-flex align-items-end pt-md-4">
                                    <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2" type="button" id="btnEscanearClienteQR" title="Escanear credencial QR del cliente con cámara">
                                        <span>📷</span>
                                        <span>Escanear QR de Cliente</span>
                                    </button>
                                </div>
                            </div>
                            <div id="ventaClienteSeleccionadoBadge" class="mt-2 small text-success fw-semibold d-none">
                                ✓ Cliente identificado y seleccionado
                            </div>
                        </div>

                        <!-- 2. Panel para Agregar Producto al Carrito -->
                        <div class="p-3 border rounded-3 bg-white mb-3 shadow-sm">
                            <h3 class="h6 fw-bold text-dark mb-2">➕ Agregar artículo al ticket</h3>
                            
                            <div class="mb-2">
                                <label class="form-label small text-muted" for="ventaProducto">Producto *</label>
                                <div class="input-group">
                                    <select class="form-select form-select-sm" id="ventaProducto">
                                        <option value="">-- Seleccionar producto --</option>
                                    </select>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="btnEscanearProductoVenta" title="Escanear código de barras con cámara">📷</button>
                                </div>
                                <small id="ventaInfoEmpaque" class="text-primary small d-none"></small>
                            </div>

                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-sm-4">
                                    <label class="form-label small text-muted" for="ventaTipoVenta">Presentación</label>
                                    <select class="form-select form-select-sm" id="ventaTipoVenta">
                                        <option value="unidad">Unidad</option>
                                        <option value="caja">Caja</option>
                                        <option value="bulto">Bulto</option>
                                    </select>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label class="form-label small text-muted" for="ventaCantidad">Cantidad</label>
                                    <input class="form-control form-control-sm" id="ventaCantidad" type="number" min="1" value="1">
                                </div>
                                <div class="col-6 col-sm-5">
                                    <label class="form-label small text-muted" for="ventaDescuentoPorcentaje">Descuento (%)</label>
                                    <div class="input-group input-group-sm">
                                        <select class="form-select form-select-sm" id="ventaDescuentoPorcentaje">
                                            <option value="0" selected>0%</option>
                                            <option value="5">5%</option>
                                            <option value="10">10%</option>
                                            <option value="15">15%</option>
                                            <option value="20">20%</option>
                                            <option value="25">25%</option>
                                            <option value="custom">Otro...</option>
                                        </select>
                                        <input class="form-control form-control-sm d-none" id="ventaDescuentoCustom" type="number" min="0" max="100" step="0.5" placeholder="%">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <div class="small">
                                    <span class="text-muted">Subtotal ítem:</span>
                                    <strong id="itemPreviewSubtotal" class="text-primary fs-6">$ 0,00</strong>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" id="btnAgregarAlCarrito">
                                    ➕ Agregar al carrito
                                </button>
                            </div>
                        </div>

                        <!-- 3. Tabla del Carrito de Ventas -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">🛒 Artículos en el Carrito</label>
                            <div class="table-responsive border rounded-3">
                                <table class="table table-hover align-middle mb-0" id="tablaCarritoVentas">
                                    <thead class="table-light small text-muted">
                                        <tr>
                                            <th>#</th>
                                            <th>Producto</th>
                                            <th>Empaque</th>
                                            <th>Cant.</th>
                                            <th>Precio Unit.</th>
                                            <th>Desc.</th>
                                            <th>Subtotal</th>
                                            <th class="text-center" style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoCarritoVentas">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-3 empty-state">
                                                El carrito está vacío. Seleccioná o escaneá un producto arriba.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 4. Resumen y Cálculo General -->
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Ítems / Unidades físicas:</span>
                                <strong id="ventaCarritoTotalUnidades" class="text-dark">0 un.</strong>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Subtotal general:</span>
                                <span id="ventaCarritoSubtotal">$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span>Descuentos totales:</span>
                                <span id="ventaCarritoDescuento" class="text-danger">$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between fs-5 fw-bold text-success pt-2 border-top">
                                <span>TOTAL A PAGAR:</span>
                                <span id="ventaCarritoTotal">$ 0,00</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" id="btnConfirmarVentaCarrito">
                            ✓ Confirmar Venta
                        </button>
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

    <!-- Modal Credencial QR del Cliente -->
    <div class="modal fade" id="modalQrCliente" tabindex="-1" aria-labelledby="tituloModalQrCliente" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="tituloModalQrCliente" class="modal-title fs-5 fw-bold">🪪 Credencial Digital con QR</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="tarjeta-credencial-qr mx-auto" style="max-width: 340px;">
                        <div class="small text-uppercase tracking-wide opacity-75">Control Stock - Cliente</div>
                        <h3 class="h5 fw-bold mt-1 mb-0" id="qrClienteNombreModal">Nombre del Cliente</h3>
                        <p class="small text-white-50 mb-2" id="qrClienteDniModal">DNI: —</p>
                        
                        <div class="qr-box">
                            <div id="contenedorQrCanvasCliente"></div>
                        </div>

                        <div class="small font-monospace opacity-75" id="qrClienteCodigoTexto">CLIENTE:0</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnImprimirQrCliente">🖨️ Imprimir Credencial</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/panel.js"></script>
</body>
</html>
