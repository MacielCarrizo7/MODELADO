<?php
require_once "seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once "conexion.php";
require_once "FirestoreConexion.php";
$pdo = Conexion::obtenerInstancia();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administrativo | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>">
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
                    <a class="nav-link nav-link-app" href="registro.php">Gestión de Usuarios</a>
                    <button class="btn btn-primary btn-sm ms-lg-2" type="button" data-bs-toggle="modal" data-bs-target="#modalVenta">+ Registrar venta</button>
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
                <p class="etiqueta text-white-50 mb-2">Panel Administrativo Global</p>
                <h1 class="display-6 fw-bold mb-2">Hola, <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, "UTF-8") ?></h1>
                <p class="lead text-white-50 mb-0">Control integral de inventario, ventas por empaque/descuentos, semáforo FIFO de vencimientos y atención a clientes.</p>
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
                                    <button class="btn btn-outline-primary w-100 p-3 text-start d-flex align-items-center gap-3" type="button" data-bs-toggle="modal" data-bs-target="#modalProducto">
                                        <span class="fs-4">➕</span>
                                        <div>
                                            <div class="fw-bold">Alta de producto</div>
                                            <small class="text-muted">Cajas, bultos y vencimiento</small>
                                        </div>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <button class="btn btn-outline-primary w-100 p-3 text-start d-flex align-items-center gap-3" type="button" data-bs-toggle="modal" data-bs-target="#modalVenta">
                                        <span class="fs-4">🛒</span>
                                        <div>
                                            <div class="fw-bold">Registrar venta</div>
                                            <small class="text-muted">Cajas, unidades y descuentos</small>
                                        </div>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <button class="btn btn-outline-secondary w-100 p-3 text-start d-flex align-items-center gap-3" type="button" onclick="bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-proveedores-btn')).show()">
                                        <span class="fs-4">🏢</span>
                                        <div>
                                            <div class="fw-bold">Lista de proveedores</div>
                                            <small class="text-muted">Historial y abastecimiento</small>
                                        </div>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <a class="btn btn-outline-secondary w-100 p-3 text-start d-flex align-items-center gap-3 text-decoration-none" href="registro.php">
                                        <span class="fs-4">👥</span>
                                        <div>
                                            <div class="fw-bold">Gestión de usuarios</div>
                                            <small class="text-muted">Clientes y personal con buscador</small>
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
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalProducto">+ Agregar producto</button>
                    </div>

                    <!-- Filtros de Inventario con Semáforo FIFO -->
                    <form id="formFiltrosProductos" class="row g-3 align-items-end mb-3 p-3 bg-light rounded-3 border" onsubmit="return false;">
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label for="filtroProductoBusqueda" class="form-label">Buscar producto / proveedor</label>
                            <input type="text" id="filtroProductoBusqueda" name="busqueda" class="form-control" placeholder="🔍 Nombre o proveedor...">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label for="filtroProductoSemaforo" class="form-label">Semáforo de vencimiento (FIFO)</label>
                            <select id="filtroProductoSemaforo" name="semaforo" class="form-select">
                                <option value="">Todos los vencimientos</option>
                                <option value="rojo">🔴 Rojo: Próximos a vencer / Vencidos (≤ 45 días)</option>
                                <option value="amarillo">🟡 Amarillo: Rotación intermedia (46 a 90 días)</option>
                                <option value="verde">🟢 Verde: Vigentes (> 90 días)</option>
                                <option value="sin_fecha">⚪ Sin fecha de vencimiento</option>
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
                        <div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
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
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
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
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalVenta">+ Registrar venta</button>
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

            <!-- Pestaña 5: Directorio de Proveedores -->
            <div class="tab-pane fade" id="pestana-proveedores" role="tabpanel" aria-labelledby="tab-proveedores-btn">
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
                                    <th>Proveedor</th>
                                    <th>Productos en Catálogo</th>
                                    <th>Entradas Registradas</th>
                                    <th>Volumen Total Abastecido</th>
                                    <th>Último Ingreso</th>
                                </tr>
                            </thead>
                            <tbody id="proveedoresBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>

        </div>
    </main>

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
                                <input class="form-control" id="productoProveedor" name="proveedor" maxlength="150" placeholder="Nombre o empresa">
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
                                <input class="form-control" id="editarProductoProveedor" name="proveedor" maxlength="150">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="editarProductoMotivo">Motivo de la corrección / ajuste (opcional)</label>
                            <input class="form-control" id="editarProductoMotivo" name="motivo" maxlength="255" placeholder="Ej: Corrección de precio / reposición">
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

    <!-- Modal Registrar Venta (con Cajas/Unidades y Descuentos) -->
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
                            <select class="form-select" id="ventaProducto" name="producto_id" required></select>
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
                                <label class="form-label" for="ventaDescuentoPorcentaje">Descuento al cliente (opcional)</label>
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

                        <!-- Resumen y Cálculo en Vivo -->
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Unidades totales a descontar:</span>
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
