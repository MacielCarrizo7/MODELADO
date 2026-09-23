<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin", "vendedor"]);
require_once __DIR__ . "/FirestoreConexion.php";

$rol = $_SESSION["usuario_rol"] ?? "vendedor";
$esAdmin = ($rol === "admin");
$limiteDescuento = $esAdmin ? 100 : (int)($_SESSION["usuario_limite_descuento"] ?? 15);
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));
$paginaRetorno = $esAdmin ? "admin.php" : "vendedor.php";
$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta / Registrar Venta | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .item-resultado-busqueda {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .item-resultado-busqueda:hover, .item-resultado-busqueda.active-item {
            background-color: #f1f5f9;
        }
        .tarjeta-prod-seleccionado {
            border-left: 4px solid #0d6efd !important;
        }
    </style>
</head>
<body data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>" data-limite-descuento="<?= $limiteDescuento ?>">
    <!-- Barra Superior -->
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $paginaRetorno ?>">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Punto de Venta</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="<?= $paginaRetorno ?>" class="btn btn-outline-secondary btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <!-- Encabezado -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="<?= $paginaRetorno ?>" class="text-decoration-none text-muted small">← Volver al Panel</a>
                <h1 class="h3 fw-bold mt-1 mb-0">🛒 Registrar Venta (Carrito Multiproducto)</h1>
                <p class="text-muted small mb-0">Buscá por nombre, código de barras o rubro, agregá productos al ticket y confirmá en un solo clic.</p>
            </div>
        </div>

        <!-- Alertas -->
        <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
        <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

        <div class="row g-4">
            <!-- Columna Izquierda: Selección de Cliente y Búsqueda Ágil de Productos -->
            <div class="col-12 col-lg-6">
                <!-- 1. Cliente -->
                <div class="seccion-card mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3">1. Cliente</h2>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-8">
                            <label for="ventaCliente" class="form-label">Seleccionar Cliente *</label>
                            <select class="form-select" id="ventaCliente" required>
                                <option value="">Cargando lista de clientes...</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-1" type="button" id="btnEscanearClienteQR">
                                <span>📷</span>
                                <span>Escanear QR</span>
                            </button>
                        </div>
                    </div>
                    <div id="clienteFeedback" class="mt-2 small text-success fw-semibold d-none">
                        ✓ Cliente seleccionado
                    </div>
                </div>

                <!-- 2. Buscador Inteligente y Rápido de Producto -->
                <div class="seccion-card mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3">2. Buscar y Agregar Artículo</h2>
                    
                    <div class="mb-3 position-relative">
                        <label for="buscadorVentaProducto" class="form-label fw-bold">🔍 Buscar Producto *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="buscadorVentaProducto" placeholder="Escribí nombre, categoría, código de barras o descripción..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btnLimpiarBuscadorProd" title="Limpiar búsqueda">✕</button>
                            <button class="btn btn-outline-primary" type="button" id="btnEscanearProductoCb" title="Escanear código con cámara">📷</button>
                        </div>
                        
                        <!-- Lista flotante de sugerencias en vivo -->
                        <div id="dropdownResultadosBusqueda" class="position-absolute w-100 bg-white border rounded-3 shadow-lg mt-1 d-none" style="z-index: 1050; max-height: 280px; overflow-y: auto;">
                        </div>

                        <!-- Selector sincronizado interno -->
                        <select class="form-select d-none" id="ventaProducto">
                            <option value="">Cargando productos...</option>
                        </select>
                    </div>

                    <!-- Ficha del Producto Seleccionado -->
                    <div id="tarjetaProductoSeleccionado" class="p-3 bg-white rounded-3 border tarjeta-prod-seleccionado mb-3 shadow-sm d-none">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <span class="badge bg-primary rounded-pill mb-1" id="selProdCat">General</span>
                                <h3 class="h6 fw-bold mb-0 text-dark" id="selProdNombre">Nombre del Producto</h3>
                                <small class="text-muted" id="selProdDetalles">Cód: — | Proveedor: —</small>
                            </div>
                            <div class="text-end">
                                <span class="badge text-bg-success fs-6 fw-bold" id="selProdPrecio">$ 0,00</span>
                                <small class="d-block text-muted" id="selProdStock">Stock: 0 un.</small>
                            </div>
                        </div>
                        <small id="ventaInfoEmpaque" class="text-primary small d-none mt-2 d-block"></small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-4">
                            <label for="ventaTipoVenta" class="form-label small text-muted">Presentación</label>
                            <select class="form-select" id="ventaTipoVenta">
                                <option value="unidad">Unidad</option>
                                <option value="caja">Caja</option>
                                <option value="bulto">Bulto</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="ventaCantidad" class="form-label small text-muted">Cantidad *</label>
                            <input class="form-control fw-bold" id="ventaCantidad" type="number" min="1" value="1">
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="ventaDescuento" class="form-label small text-muted">Descuento (%)</label>
                            <select class="form-select" id="ventaDescuento">
                                <option value="0" selected>0%</option>
                                <option value="5">5%</option>
                                <option value="10">10%</option>
                                <option value="15">15%</option>
                                <option value="20">20%</option>
                                <option value="25">25%</option>
                                <option value="custom">Otro...</option>
                            </select>
                            <input type="number" min="0" max="<?= $limiteDescuento ?>" class="form-control mt-1 d-none" id="ventaDescuentoCustom" placeholder="Máx <?= $limiteDescuento ?>%">
                        </div>
                    </div>

                    <!-- Previsualización del Artículo -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Unidades físicas:</span>
                            <strong id="itemPreUnidades" class="text-dark">0 un.</strong>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Precio unitario:</span>
                            <span id="itemPrePrecio">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Subtotal ítem:</span>
                            <span id="itemPreSubtotal">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold text-success pt-1 border-top">
                            <span>Subtotal con descuento:</span>
                            <span id="itemPreTotal">$ 0,00</span>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary w-100 py-2 fs-6 fw-bold" id="btnAgregarAlCarrito">
                        ➕ Agregar Artículo al Carrito
                    </button>
                </div>
            </div>

            <!-- Columna Derecha: Detalle del Carrito y Confirmación -->
            <div class="col-12 col-lg-6">
                <div class="seccion-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold text-dark mb-0">3. Carrito de Compras</h2>
                        <span class="badge text-bg-primary" id="carritoCountBadge">0 ítems</span>
                    </div>

                    <!-- Tabla de Carrito -->
                    <div class="table-responsive border rounded-3 mb-3 bg-white">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>#</th>
                                    <th>Producto</th>
                                    <th>Precio</th>
                                    <th>Desc.</th>
                                    <th>Total</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody id="carritoTablaBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        🛒 El carrito está vacío. Buscá y agregá productos desde el panel izquierdo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumen Financiero -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between text-muted mb-2">
                            <span>Total unidades físicas:</span>
                            <strong id="resumenTotalUnidades" class="text-dark">0 un.</strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-2">
                            <span>Subtotal:</span>
                            <span id="resumenSubtotal">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-2">
                            <span>Descuentos:</span>
                            <span id="resumenDescuento" class="text-danger">$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between fs-4 fw-bold text-success pt-2 border-top">
                            <span>TOTAL A PAGAR:</span>
                            <span id="resumenTotalFinal">$ 0,00</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger" id="btnVaciarCarrito">🗑️ Vaciar</button>
                        <button type="button" class="btn btn-success flex-grow-1 py-3 fs-5 fw-bold shadow" id="btnConfirmarVenta" disabled>
                            ✓ Confirmar Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Escáner Cámara Standalone -->
    <div class="modal fade" id="modalScannerCamara" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-bold">📷 Escanear con Cámara</h2>
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
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });
        const rol = document.body.dataset.rol || "vendedor";
        const limiteDescuento = parseInt(document.body.dataset.limiteDescuento) || 15;
        const csrfToken = document.body.dataset.csrf;

        let productosCache = [];
        let clientesCache = [];
        let carrito = [];
        let callbackScanActivo = null;

        // Cargar clientes y productos
        async function inicializarPOS() {
            try {
                const [prods, clients] = await Promise.all([
                    fetch("obtener_productos.php").then(r => r.json()),
                    fetch("obtener_clientes.php").then(r => r.json())
                ]);

                productosCache = prods || [];
                clientesCache = clients || [];

                // Poblar select clientes
                const selClientes = document.getElementById("ventaCliente");
                selClientes.replaceChildren(new Option("-- Seleccionar Cliente --", ""));
                clientesCache.forEach(c => {
                    selClientes.appendChild(new Option(`${c.nombre} ${c.apellido} (DNI ${c.dni})`, c.id));
                });

                // Poblar select productos (interno)
                const selProds = document.getElementById("ventaProducto");
                selProds.replaceChildren(new Option("-- Seleccionar Producto --", ""));
                productosCache.forEach(p => {
                    const prov = p.proveedor ? ` [🏢 ${p.proveedor}]` : "";
                    const cat = p.categoria_nombre ? ` [${p.categoria_nombre}]` : "";
                    const opt = new Option(`${p.nombre}${cat}${prov} (Stock: ${p.stock} un. - ${formatoMoneda.format(p.precio_venta)})`, p.id);
                    opt.dataset.precio = p.precio_venta;
                    opt.dataset.stock = p.stock;
                    opt.dataset.presentacion = p.presentacion || "unidad";
                    opt.dataset.permiteVentaUnidad = (p.permite_venta_unidad !== false && p.permite_venta_unidad !== 0 && p.permite_venta_unidad !== "0") ? "1" : "0";
                    opt.dataset.unidadesBulto = p.unidades_por_bulto || 1;
                    opt.dataset.proveedor = p.proveedor || "";
                    opt.dataset.categoria = p.categoria_nombre || "";
                    opt.dataset.nombre = p.nombre;
                    opt.dataset.codigo = p.codigo_barras || "";
                    opt.dataset.descripcion = p.descripcion || "";
                    selProds.appendChild(opt);
                });

                recalcularPrevisualizacion();

            } catch (err) {
                console.error("Error al inicializar POS:", err);
            }
        }

        // ==============================================================
        // BUSCADOR INTELIGENTE Y RÁPIDO DE PRODUCTOS (AUTO-COMPLETE)
        // ==============================================================
        const inputBuscador = document.getElementById("buscadorVentaProducto");
        const dropdownResultados = document.getElementById("dropdownResultadosBusqueda");
        const tarjetaSeleccionado = document.getElementById("tarjetaProductoSeleccionado");
        const btnLimpiarBuscador = document.getElementById("btnLimpiarBuscadorProd");
        let indiceFocoResultado = -1;

        function renderizarResultadosBusqueda(query) {
            const q = query.trim().toLowerCase();
            if (!q) {
                dropdownResultados.classList.add("d-none");
                dropdownResultados.replaceChildren();
                return;
            }

            const filtrados = productosCache.filter(p => {
                const nom = (p.nombre || "").toLowerCase();
                const cat = (p.categoria_nombre || p.categoria || "").toLowerCase();
                const cb = (p.codigo_barras || p.codigo || "").toLowerCase();
                const desc = (p.descripcion || "").toLowerCase();
                const prov = (p.proveedor || "").toLowerCase();

                return nom.includes(q) || cat.includes(q) || cb.includes(q) || desc.includes(q) || prov.includes(q);
            });

            if (filtrados.length === 0) {
                dropdownResultados.innerHTML = `
                    <div class="p-3 text-muted text-center small">
                        🔍 No se encontraron productos coincidentes con "<strong>${escapeHtml(query)}</strong>".
                    </div>
                `;
                dropdownResultados.classList.remove("d-none");
                return;
            }

            dropdownResultados.replaceChildren();
            filtrados.slice(0, 15).forEach((p, index) => {
                const itemDiv = document.createElement("div");
                itemDiv.className = "p-2 border-bottom item-resultado-busqueda d-flex justify-content-between align-items-center";
                itemDiv.dataset.id = p.id;

                const stockClass = p.stock > 10 ? "text-bg-success" : (p.stock > 0 ? "text-bg-warning" : "text-bg-danger");
                const stockText = p.stock > 0 ? `${p.stock} un.` : "Sin stock";

                const presNom = (p.presentacion || "unidad").toLowerCase();
                const permiteUnid = (presNom === "unidad") || (p.permite_venta_unidad !== false && p.permite_venta_unidad !== 0 && p.permite_venta_unidad !== "0");
                let badgePres = "";
                if (presNom === "caja" || presNom === "bulto") {
                    badgePres = permiteUnid 
                        ? `<span class="badge text-bg-info ms-1">${presNom.toUpperCase()} / UNID</span>` 
                        : `<span class="badge text-bg-warning ms-1">SOLO ${presNom.toUpperCase()}</span>`;
                }

                itemDiv.innerHTML = `
                    <div class="me-2 text-truncate">
                        <div class="fw-bold text-dark text-truncate">${escapeHtml(p.nombre)} ${badgePres}</div>
                        <div class="small text-muted text-truncate">
                            <span class="badge text-bg-light border">${escapeHtml(p.categoria_nombre || "General")}</span>
                            ${p.codigo_barras ? `<span class="font-monospace ms-1">🏷️ ${escapeHtml(p.codigo_barras)}</span>` : ""}
                            ${p.proveedor ? `<span class="ms-1">🏢 ${escapeHtml(p.proveedor)}</span>` : ""}
                        </div>
                    </div>
                    <div class="text-end text-nowrap">
                        <span class="fw-bold text-success fs-6">${formatoMoneda.format(p.precio_venta)}</span>
                        <span class="badge ${stockClass} d-block mt-1">${stockText}</span>
                    </div>
                `;

                itemDiv.addEventListener("click", () => {
                    seleccionarProducto(p);
                });

                dropdownResultados.appendChild(itemDiv);
            });

            dropdownResultados.classList.remove("d-none");
            indiceFocoResultado = -1;
        }

        function seleccionarProducto(p) {
            const selProds = document.getElementById("ventaProducto");
            selProds.value = p.id;

            const pres = (p.presentacion || "unidad").toLowerCase();
            const permiteUnidad = (pres === "unidad") || (p.permite_venta_unidad !== false && p.permite_venta_unidad !== 0 && p.permite_venta_unidad !== "0");
            const unidEmpaque = Math.max(1, parseInt(p.unidades_por_bulto) || 1);

            // Reconstruir selector de presentación estrictamente para este producto
            const selTipo = document.getElementById("ventaTipoVenta");
            selTipo.replaceChildren();

            if (pres === "caja") {
                selTipo.appendChild(new Option(`Caja (${unidEmpaque} un.)`, "caja"));
                if (permiteUnidad) {
                    selTipo.appendChild(new Option("Unidad individual", "unidad"));
                }
                selTipo.value = "caja";
            } else if (pres === "bulto") {
                selTipo.appendChild(new Option(`Bulto (${unidEmpaque} un.)`, "bulto"));
                if (permiteUnidad) {
                    selTipo.appendChild(new Option("Unidad individual", "unidad"));
                }
                selTipo.value = "bulto";
            } else {
                selTipo.appendChild(new Option("Unidad", "unidad"));
                selTipo.value = "unidad";
            }

            // Actualizar tarjeta visual de producto seleccionado
            let badgeRestriccion = "";
            if ((pres === "caja" || pres === "bulto") && !permiteUnidad) {
                badgeRestriccion = `<span class="badge text-bg-warning ms-1">🚫 Venta exclusiva por ${pres.toUpperCase()} (Fraccionamiento bloqueado)</span>`;
            } else if ((pres === "caja" || pres === "bulto") && permiteUnidad) {
                badgeRestriccion = `<span class="badge text-bg-info ms-1">✓ Fraccionable (${pres} y unidad)</span>`;
            }

            document.getElementById("selProdCat").textContent = p.categoria_nombre || "General";
            document.getElementById("selProdNombre").textContent = p.nombre;
            document.getElementById("selProdDetalles").innerHTML = `Cód: ${escapeHtml(p.codigo_barras || p.id)} | Proveedor: ${escapeHtml(p.proveedor || "General")} ${badgeRestriccion}`;
            document.getElementById("selProdPrecio").textContent = formatoMoneda.format(p.precio_venta);
            document.getElementById("selProdStock").textContent = `Stock disponible: ${p.stock} un.`;
            tarjetaSeleccionado.classList.remove("d-none");

            inputBuscador.value = p.nombre;
            dropdownResultados.classList.add("d-none");

            recalcularPrevisualizacion();

            // Auto-enfocar cantidad para rapidez
            const inCant = document.getElementById("ventaCantidad");
            inCant.focus();
            inCant.select();
        }

        function escapeHtml(text) {
            const div = document.createElement("div");
            div.textContent = text || "";
            return div.innerHTML;
        }

        inputBuscador.addEventListener("input", (e) => {
            renderizarResultadosBusqueda(e.target.value);
        });

        inputBuscador.addEventListener("keydown", (e) => {
            const items = dropdownResultados.querySelectorAll(".item-resultado-busqueda");
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (items.length > 0) {
                    indiceFocoResultado = (indiceFocoResultado + 1) % items.length;
                    items.forEach((it, i) => it.classList.toggle("active-item", i === indiceFocoResultado));
                }
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                if (items.length > 0) {
                    indiceFocoResultado = (indiceFocoResultado - 1 + items.length) % items.length;
                    items.forEach((it, i) => it.classList.toggle("active-item", i === indiceFocoResultado));
                }
            } else if (e.key === "Enter") {
                e.preventDefault();
                if (indiceFocoResultado >= 0 && items[indiceFocoResultado]) {
                    items[indiceFocoResultado].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
            } else if (e.key === "Escape") {
                dropdownResultados.classList.add("d-none");
            }
        });

        btnLimpiarBuscador.addEventListener("click", () => {
            inputBuscador.value = "";
            dropdownResultados.classList.add("d-none");
            document.getElementById("ventaProducto").value = "";
            tarjetaSeleccionado.classList.add("d-none");
            recalcularPrevisualizacion();
            inputBuscador.focus();
        });

        document.addEventListener("click", (e) => {
            if (!dropdownResultados.contains(e.target) && e.target !== inputBuscador) {
                dropdownResultados.classList.add("d-none");
            }
        });

        // Restricción de descuentos en select para vendedor
        const selDesc = document.getElementById("ventaDescuento");
        const inputDescCustom = document.getElementById("ventaDescuentoCustom");
        if (rol === "vendedor") {
            Array.from(selDesc.options).forEach(opt => {
                if (opt.value !== "custom") {
                    const num = parseInt(opt.value);
                    if (num > limiteDescuento) {
                        opt.disabled = true;
                        opt.text = `${num}% (No autorizado)`;
                    }
                }
            });
        }

        selDesc.addEventListener("change", () => {
            if (selDesc.value === "custom") {
                inputDescCustom.classList.remove("d-none");
            } else {
                inputDescCustom.classList.add("d-none");
            }
            recalcularPrevisualizacion();
        });
        inputDescCustom.addEventListener("input", recalcularPrevisualizacion);

        // Previsualización del producto activo
        const selProducto = document.getElementById("ventaProducto");
        const selTipoVenta = document.getElementById("ventaTipoVenta");
        const inputCantidad = document.getElementById("ventaCantidad");
        const infoEmpaque = document.getElementById("ventaInfoEmpaque");

        selProducto.addEventListener("change", recalcularPrevisualizacion);
        selTipoVenta.addEventListener("change", recalcularPrevisualizacion);
        inputCantidad.addEventListener("input", recalcularPrevisualizacion);

        // Presionar Enter en cantidad para agregar rápido
        inputCantidad.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                document.getElementById("btnAgregarAlCarrito").click();
            }
        });

        function obtenerCalculoActual() {
            const opt = selProducto.selectedOptions[0];
            if (!opt || !opt.dataset.precio || !selProducto.value) return null;

            const id = selProducto.value;
            const nombre = opt.dataset.nombre || opt.text.split("(")[0].trim();
            const precio = parseFloat(opt.dataset.precio) || 0;
            const stock = parseInt(opt.dataset.stock) || 0;
            const unidadesBulto = Math.max(1, parseInt(opt.dataset.unidadesBulto) || 1);
            const proveedor = opt.dataset.proveedor || "";
            const tipoVenta = selTipoVenta.value;
            const cantidad = Math.max(1, parseInt(inputCantidad.value) || 1);

            let totalUnidades = cantidad;
            if (tipoVenta === "caja" || tipoVenta === "bulto") {
                totalUnidades = cantidad * unidadesBulto;
            }

            let descPorc = 0;
            if (selDesc.value === "custom") {
                let customVal = parseFloat(inputDescCustom.value) || 0;
                if (customVal > limiteDescuento && rol === "vendedor") {
                    inputDescCustom.value = limiteDescuento;
                    customVal = limiteDescuento;
                }
                descPorc = Math.min(limiteDescuento, Math.max(0, customVal));
            } else {
                let optVal = parseFloat(selDesc.value) || 0;
                if (optVal > limiteDescuento && rol === "vendedor") {
                    selDesc.value = "0";
                    optVal = 0;
                }
                descPorc = optVal;
            }

            const subtotal = totalUnidades * precio;
            const montoDesc = subtotal * (descPorc / 100);
            const total = Math.max(0, subtotal - montoDesc);

            const presProd = (opt.dataset.presentacion || "unidad").toLowerCase();
            const permiteUnid = (presProd === "unidad") || (opt.dataset.permiteVentaUnidad === "1");

            if (tipoVenta === "unidad" && presProd !== "unidad" && !permiteUnid) {
                return { error: `El producto "${nombre}" está configurado para venta exclusiva por ${presProd.toUpperCase()}. No se permite la venta por unidad suelta.` };
            }

            return {
                producto_id: id,
                producto_nombre: nombre,
                proveedor: proveedor,
                stock_disponible: stock,
                precio_unitario: precio,
                tipo_venta: tipoVenta,
                cantidad_empaque: cantidad,
                unidades_por_bulto: unidadesBulto,
                total_unidades: totalUnidades,
                descuento_porcentaje: descPorc,
                descuento_monto: montoDesc,
                subtotal: subtotal,
                total: total
            };
        }

        function recalcularPrevisualizacion() {
            const calc = obtenerCalculoActual();
            const lblUnid = document.getElementById("itemPreUnidades");
            const lblPrecio = document.getElementById("itemPrePrecio");
            const lblSub = document.getElementById("itemPreSubtotal");
            const lblTot = document.getElementById("itemPreTotal");

            if (!calc || calc.error) {
                lblUnid.textContent = "0 un.";
                lblPrecio.textContent = "$ 0,00";
                lblSub.textContent = "$ 0,00";
                lblTot.textContent = "$ 0,00";
                infoEmpaque.classList.add("d-none");
                return;
            }

            if (calc.tipo_venta === "caja" || calc.tipo_venta === "bulto") {
                infoEmpaque.textContent = `📦 1 ${calc.tipo_venta} = ${calc.unidades_por_bulto} unidades físicas`;
                infoEmpaque.classList.remove("d-none");
            } else {
                infoEmpaque.classList.add("d-none");
            }

            lblUnid.textContent = `${calc.total_unidades} un.`;
            lblPrecio.textContent = formatoMoneda.format(calc.precio_unitario);
            lblSub.textContent = formatoMoneda.format(calc.subtotal);
            lblTot.textContent = formatoMoneda.format(calc.total);
        }

        // Agregar al carrito
        document.getElementById("btnAgregarAlCarrito").addEventListener("click", () => {
            const errBox = document.getElementById("alertaError");
            errBox.classList.add("d-none");

            const calc = obtenerCalculoActual();
            if (!calc) {
                errBox.textContent = "Buscá y seleccioná un producto antes de agregarlo.";
                errBox.classList.remove("d-none");
                inputBuscador.focus();
                return;
            }
            if (calc.error) {
                errBox.textContent = calc.error;
                errBox.classList.remove("d-none");
                return;
            }

            const yaEnCarrito = carrito
                .filter(i => String(i.producto_id) === String(calc.producto_id))
                .reduce((s, i) => s + i.total_unidades, 0);

            if ((yaEnCarrito + calc.total_unidades) > calc.stock_disponible) {
                errBox.textContent = `Stock insuficiente para "${calc.producto_nombre}". Stock disponible: ${calc.stock_disponible} un. (Ya hay ${yaEnCarrito} un. en el carrito).`;
                errBox.classList.remove("d-none");
                return;
            }

            carrito.push(calc);
            renderizarCarrito();

            // Reset rápido para el próximo producto
            selProducto.value = "";
            inputBuscador.value = "";
            tarjetaSeleccionado.classList.add("d-none");
            inputCantidad.value = 1;
            selTipoVenta.value = "unidad";
            selDesc.value = "0";
            inputDescCustom.value = "";
            inputDescCustom.classList.add("d-none");
            recalcularPrevisualizacion();
            inputBuscador.focus();
        });

        // Renderizar carrito
        function renderizarCarrito() {
            const tbody = document.getElementById("carritoTablaBody");
            const badgeCount = document.getElementById("carritoCountBadge");
            const resUnid = document.getElementById("resumenTotalUnidades");
            const resSub = document.getElementById("resumenSubtotal");
            const resDesc = document.getElementById("resumenDescuento");
            const resTot = document.getElementById("resumenTotalFinal");
            const btnConfirm = document.getElementById("btnConfirmarVenta");

            if (carrito.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">🛒 El carrito está vacío. Buscá y agregá productos desde el panel izquierdo.</td></tr>`;
                badgeCount.textContent = "0 ítems";
                resUnid.textContent = "0 un.";
                resSub.textContent = "$ 0,00";
                resDesc.textContent = "$ 0,00";
                resTot.textContent = "$ 0,00";
                btnConfirm.disabled = true;
                return;
            }

            tbody.replaceChildren();
            let totalUnidadesGen = 0;
            let subtotalGen = 0;
            let descuentoGen = 0;
            let totalGen = 0;

            carrito.forEach((item, idx) => {
                totalUnidadesGen += item.total_unidades;
                subtotalGen += item.subtotal;
                descuentoGen += item.descuento_monto;
                totalGen += item.total;

                const tr = document.createElement("tr");

                // #
                const tdNum = document.createElement("td");
                tdNum.textContent = idx + 1;
                tdNum.className = "text-muted small";
                tr.appendChild(tdNum);

                // Producto
                const tdProd = document.createElement("td");
                const strong = document.createElement("strong");
                strong.textContent = item.producto_nombre;
                tdProd.appendChild(strong);

                const smallEmp = document.createElement("small");
                smallEmp.className = "d-block text-muted";
                smallEmp.textContent = item.tipo_venta !== "unidad" 
                    ? `${item.cantidad_empaque} ${item.tipo_venta}(s) (${item.total_unidades} un.)` 
                    : `${item.total_unidades} un.`;
                tdProd.appendChild(smallEmp);
                tr.appendChild(tdProd);

                // Precio Unit
                const tdPrecio = document.createElement("td");
                tdPrecio.textContent = formatoMoneda.format(item.precio_unitario);
                tdPrecio.className = "small";
                tr.appendChild(tdPrecio);

                // Descuento
                const tdDesc = document.createElement("td");
                if (item.descuento_porcentaje > 0) {
                    tdDesc.innerHTML = `<span class="badge text-bg-danger">-${item.descuento_porcentaje}%</span>`;
                } else {
                    tdDesc.innerHTML = `<span class="text-muted small">0%</span>`;
                }
                tr.appendChild(tdDesc);

                // Total Fila
                const tdTotal = document.createElement("td");
                tdTotal.textContent = formatoMoneda.format(item.total);
                tdTotal.className = "fw-bold text-success";
                tr.appendChild(tdTotal);

                // Botón Quitar
                const tdAcc = document.createElement("td");
                tdAcc.className = "text-end";
                const btnDel = document.createElement("button");
                btnDel.type = "button";
                btnDel.className = "btn btn-outline-danger btn-sm py-0 px-2";
                btnDel.innerHTML = "✕";
                btnDel.addEventListener("click", () => {
                    carrito.splice(idx, 1);
                    renderizarCarrito();
                });
                tdAcc.appendChild(btnDel);
                tr.appendChild(tdAcc);

                tbody.appendChild(tr);
            });

            badgeCount.textContent = `${carrito.length} ítems`;
            resUnid.textContent = `${totalUnidadesGen} un.`;
            resSub.textContent = formatoMoneda.format(subtotalGen);
            resDesc.textContent = descuentoGen > 0 ? `-${formatoMoneda.format(descuentoGen)}` : "$ 0,00";
            resTot.textContent = formatoMoneda.format(totalGen);
            btnConfirm.disabled = false;
        }

        document.getElementById("btnVaciarCarrito").addEventListener("click", () => {
            if (carrito.length > 0 && confirm("¿Vaciar todo el carrito?")) {
                carrito = [];
                renderizarCarrito();
            }
        });

        // Escáner de Cliente QR
        let html5Qr = null;
        const modalScan = new bootstrap.Modal(document.getElementById("modalScannerCamara"));

        document.getElementById("btnEscanearClienteQR").addEventListener("click", () => {
            abrirLector((qr) => {
                const match = String(qr).match(/CLIENTE:([^:]+)(?::DNI:([^:]+))?/i);
                const idBuscado = match ? match[1].trim() : String(qr).trim();
                const dniBuscado = match && match[2] ? match[2].trim() : String(qr).trim();

                const encontrado = clientesCache.find(c => 
                    String(c.id).toLowerCase() === idBuscado.toLowerCase() || 
                    String(c.dni) === dniBuscado ||
                    String(c.id) === String(qr).trim()
                );

                if (encontrado) {
                    const sel = document.getElementById("ventaCliente");
                    sel.value = encontrado.id;
                    const fb = document.getElementById("clienteFeedback");
                    fb.textContent = `✓ Cliente escaneado: ${encontrado.nombre} ${encontrado.apellido} (DNI ${encontrado.dni})`;
                    fb.classList.remove("d-none");
                } else {
                    alert(`No se encontró ningún cliente para el código escaneado: "${qr}".`);
                }
            });
        });

        document.getElementById("btnEscanearProductoCb").addEventListener("click", () => {
            abrirLector((cb) => {
                const encontrado = productosCache.find(p => p.codigo_barras === cb || p.codigo === cb);
                if (encontrado) {
                    seleccionarProducto(encontrado);
                } else {
                    alert(`No se encontró producto con código "${cb}".`);
                }
            });
        });

        function abrirLector(callback) {
            callbackScanActivo = callback;
            modalScan.show();
            setTimeout(() => {
                if (html5Qr) html5Qr.clear().catch(() => {});
                html5Qr = new Html5Qrcode("qr-reader");
                html5Qr.start(
                    { facingMode: "environment" },
                    { fps: 15, qrbox: { width: 250, height: 150 } },
                    (decoded) => {
                        html5Qr.stop().then(() => {
                            html5Qr.clear();
                            html5Qr = null;
                        });
                        modalScan.hide();
                        if (callbackScanActivo) callbackScanActivo(decoded);
                    },
                    () => {}
                ).catch(err => {
                    const res = document.getElementById("scannerResultado");
                    res.textContent = "Error de cámara: " + err;
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

        // Confirmar Venta
        document.getElementById("btnConfirmarVenta").addEventListener("click", async () => {
            const errBox = document.getElementById("alertaError");
            const okBox = document.getElementById("alertaExito");
            errBox.classList.add("d-none");
            okBox.classList.add("d-none");

            const clienteId = document.getElementById("ventaCliente").value;
            if (!clienteId) {
                errBox.textContent = "Debés seleccionar o escanear un cliente antes de confirmar la venta.";
                errBox.classList.remove("d-none");
                window.scrollTo({ top: 0, behavior: "smooth" });
                return;
            }

            if (carrito.length === 0) {
                errBox.textContent = "El carrito está vacío. Agregá productos al ticket.";
                errBox.classList.remove("d-none");
                return;
            }

            const btnConfirm = document.getElementById("btnConfirmarVenta");
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Procesando venta...`;

            try {
                const resp = await fetch("guardar_venta.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": csrfToken
                    },
                    body: JSON.stringify({
                        cliente_id: clienteId,
                        items: carrito
                    })
                });

                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    throw new Error(data.error || "Error al procesar la venta.");
                }

                okBox.innerHTML = `<strong>¡Venta registrada con éxito!</strong> ${data.mensaje || ''} ${data.ticket_id ? `<br><span class="font-monospace small">Ticket: ${data.ticket_id}</span>` : ''}`;
                okBox.classList.remove("d-none");
                carrito = [];
                renderizarCarrito();
                window.scrollTo({ top: 0, behavior: "smooth" });

                setTimeout(() => {
                    window.location.href = "<?= $paginaRetorno ?>";
                }, 1500);

            } catch (err) {
                errBox.textContent = err.message;
                errBox.classList.remove("d-none");
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = "✓ Confirmar Venta";
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        });

        // Iniciar
        document.addEventListener("DOMContentLoaded", inicializarPOS);
    </script>
</body>
</html>
