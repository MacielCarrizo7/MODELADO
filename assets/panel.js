const rolPanel = document.body.dataset.rol;
const esAdmin = rolPanel === "admin";
const esVendedor = rolPanel === "vendedor";
const csrfToken = document.body.dataset.csrf;
const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });
const formatoFecha = new Intl.DateTimeFormat("es-AR", { dateStyle: "short", timeStyle: "short" });
const formatoFechaCorta = new Intl.DateTimeFormat("es-AR", { dateStyle: "medium" });

let productosCache = [];
let proveedoresCache = [];
let solicitudesCache = [];
let html5QrScannerInstance = null;
let callbackScannerActivo = null;

function celda(texto, clase = "") {
    const td = document.createElement("td");
    td.className = clase;
    td.textContent = texto;
    return td;
}

function mensajeEnTabla(tbody, columnas, mensaje, esError = false) {
    if (!tbody) return;
    tbody.replaceChildren();
    const fila = document.createElement("tr");
    const td = celda(mensaje, `empty-state ${esError ? "text-danger" : ""}`);
    td.colSpan = columnas;
    fila.appendChild(td);
    tbody.appendChild(fila);
}

async function solicitar(url, opciones = {}) {
    const config = { ...opciones, headers: { ...(opciones.headers || {}) } };
    if ((config.method || "GET").toUpperCase() !== "GET") {
        config.headers["X-CSRF-Token"] = csrfToken;
    }
    const respuesta = await fetch(url, config);
    const datos = await respuesta.json().catch(() => ({}));
    if (respuesta.status === 401) {
        window.location.href = "login.php";
        throw new Error("Sesión finalizada");
    }
    if (!respuesta.ok) throw new Error(datos.error || "No se pudo completar la operación.");
    return datos;
}

function fechaLegible(valor) {
    if (!valor) return "—";
    const fecha = new Date(String(valor).replace(" ", "T"));
    return Number.isNaN(fecha.getTime()) ? valor : formatoFecha.format(fecha);
}

// Política de semáforo FIFO:
// - Rojo: ≤ 45 días (o vencido)
// - Amarillo: 46 a 90 días
// - Verde: > 90 días
function obtenerCategoriaSemaforo(fechaIso) {
    if (!fechaIso) return "sin_fecha";
    const partes = fechaIso.split("-");
    if (partes.length !== 3) return "sin_fecha";
    
    const vencimiento = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    const diferenciaTiempo = vencimiento.getTime() - hoy.getTime();
    const diferenciaDias = Math.ceil(diferenciaTiempo / (1000 * 60 * 60 * 24));

    if (diferenciaDias <= 45) return "rojo";
    if (diferenciaDias <= 90) return "amarillo";
    return "verde";
}

function formatearFechaVencimiento(fechaIso) {
    if (!fechaIso) return { texto: "Sin fecha", clase: "sin-vencimiento", dias: null, categoria: "sin_fecha" };
    const partes = fechaIso.split("-");
    if (partes.length !== 3) return { texto: fechaIso, clase: "sin-vencimiento", dias: null, categoria: "sin_fecha" };
    
    const vencimiento = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    const diferenciaTiempo = vencimiento.getTime() - hoy.getTime();
    const diferenciaDias = Math.ceil(diferenciaTiempo / (1000 * 60 * 60 * 24));
    const fechaTexto = formatoFechaCorta.format(vencimiento);

    if (diferenciaDias <= 45) {
        if (diferenciaDias < 0) {
            return { texto: `Vencido (${fechaTexto})`, clase: "vencido", dias: diferenciaDias, categoria: "rojo" };
        } else {
            return { texto: `Vence en ${diferenciaDias} d (${fechaTexto})`, clase: "vencido", dias: diferenciaDias, categoria: "rojo" };
        }
    } else if (diferenciaDias <= 90) {
        return { texto: `Vence en ${diferenciaDias} d (${fechaTexto})`, clase: "vence-pronto", dias: diferenciaDias, categoria: "amarillo" };
    } else {
        return { texto: fechaTexto, clase: "vigente", dias: diferenciaDias, categoria: "verde" };
    }
}

function crearBadgeVencimiento(fechaIso) {
    const info = formatearFechaVencimiento(fechaIso);
    const badge = document.createElement("span");
    badge.className = `badge-vencimiento ${info.clase}`;
    badge.textContent = info.texto;
    return badge;
}

// Renderizado de Productos con Código de Barras y Botón de Historial
function renderizarFilasProductos(productos) {
    const tbody = document.getElementById("productosBody");
    if (!tbody) return;
    const columnas = 8;
    if (productos.length === 0) {
        mensajeEnTabla(tbody, columnas, "No se encontraron productos con los filtros seleccionados.");
        return;
    }
    tbody.replaceChildren();

    productos.forEach((producto) => {
        const fila = document.createElement("tr");

        // 1. Código / Código de Barras
        const cbTd = document.createElement("td");
        const cbCont = document.createElement("div");
        if (producto.codigo_barras) {
            const cbBadge = document.createElement("span");
            cbBadge.className = "badge text-bg-light border font-monospace";
            cbBadge.textContent = producto.codigo_barras;
            cbCont.appendChild(cbBadge);
        } else if (producto.codigo) {
            const codBadge = document.createElement("span");
            codBadge.className = "badge text-bg-secondary";
            codBadge.textContent = producto.codigo;
            cbCont.appendChild(codBadge);
        } else {
            cbCont.innerHTML = '<span class="text-muted small">—</span>';
        }
        cbTd.appendChild(cbCont);
        fila.appendChild(cbTd);

        // 2. Nombre con Diferenciación de Proveedor
        const nombreTd = document.createElement("td");
        const nombreTitulo = document.createElement("div");
        nombreTitulo.className = "fw-bold d-flex align-items-center flex-wrap gap-1";
        nombreTitulo.textContent = producto.nombre;
        if (producto.proveedor) {
            const badgeProv = document.createElement("span");
            badgeProv.className = "badge text-bg-light border text-primary small fw-semibold";
            badgeProv.textContent = `🏢 ${producto.proveedor}`;
            nombreTitulo.appendChild(badgeProv);
        }
        nombreTd.appendChild(nombreTitulo);
        if (producto.descripcion) {
            const desc = document.createElement("small");
            desc.className = "text-muted d-block";
            desc.textContent = producto.descripcion;
            nombreTd.appendChild(desc);
        }
        fila.appendChild(nombreTd);

        // 3. Presentación
        fila.append(celda(producto.presentacion ? producto.presentacion.toUpperCase() : "UNIDAD"));

        // 4. Proveedor (Columna destacada)
        const provTd = document.createElement("td");
        if (producto.proveedor) {
            provTd.innerHTML = `<span class="fw-semibold text-dark">🏢 ${producto.proveedor}</span>`;
        } else {
            provTd.innerHTML = '<span class="text-muted small">—</span>';
        }
        fila.appendChild(provTd);

        // 5. Vencimiento (FIFO)
        const vencimientoTd = document.createElement("td");
        vencimientoTd.appendChild(crearBadgeVencimiento(producto.fecha_vencimiento));
        fila.appendChild(vencimientoTd);

        // 6. Precio
        fila.append(celda(formatoMoneda.format(producto.precio), "fw-bold text-primary"));

        // 7. Stock
        const stockTd = document.createElement("td");
        const stock = Number(producto.stock);
        const unidadesPorBulto = Number(producto.unidades_por_bulto) || 1;
        const badge = document.createElement("span");
        badge.className = `estado-stock ${stock <= 0 ? "agotado" : stock <= 5 ? "bajo" : ""}`;
        
        if (stock <= 0) {
            badge.textContent = "Sin stock";
        } else if (producto.presentacion && producto.presentacion !== "unidad" && unidadesPorBulto > 1) {
            const bultos = Math.floor(stock / unidadesPorBulto);
            const resto = stock % unidadesPorBulto;
            let textoEmpaque = `${stock} un.`;
            if (bultos > 0) {
                textoEmpaque += ` (${bultos} ${producto.presentacion}${bultos > 1 ? "s" : ""}${resto > 0 ? ` + ${resto} un.` : ""})`;
            }
            badge.textContent = textoEmpaque;
        } else {
            badge.textContent = `${stock} un. disponibles`;
        }
        stockTd.appendChild(badge);
        fila.appendChild(stockTd);

        // 8. Acciones (Historial + Editar + Eliminar)
        const accion = document.createElement("td");
        accion.className = "text-end";
        const grupo = document.createElement("div");
        grupo.className = "d-inline-flex gap-1";

        // Botón Historial de Movimientos
        const btnHistorial = document.createElement("button");
        btnHistorial.type = "button";
        btnHistorial.className = "btn btn-outline-secondary btn-sm";
        btnHistorial.title = "Ver historial y trazabilidad de movimientos";
        btnHistorial.innerHTML = "📜 Historial";
        btnHistorial.addEventListener("click", () => abrirModalHistorialProducto(producto.id));
        grupo.appendChild(btnHistorial);

        if (esAdmin) {
            const btnEditar = document.createElement("button");
            btnEditar.type = "button";
            btnEditar.className = "btn btn-outline-primary btn-sm";
            btnEditar.textContent = "Editar";
            btnEditar.addEventListener("click", () => abrirModalEditarProducto(producto));

            const btnEliminar = document.createElement("button");
            btnEliminar.type = "button";
            btnEliminar.className = "btn btn-outline-danger btn-sm";
            btnEliminar.textContent = "Eliminar";
            btnEliminar.addEventListener("click", () => eliminarProducto(producto.id, producto.nombre));

            grupo.append(btnEditar, btnEliminar);
        }
        accion.appendChild(grupo);
        fila.appendChild(accion);

        tbody.appendChild(fila);
    });
}

function filtrarYRenderizarProductos() {
    const inputBusqueda = document.getElementById("filtroProductoBusqueda");
    const selectSemaforo = document.getElementById("filtroProductoSemaforo");
    const selectPresentacion = document.getElementById("filtroProductoPresentacion");
    const selectProveedor = document.getElementById("filtroProductoProveedor");

    const query = inputBusqueda ? inputBusqueda.value.toLowerCase().trim() : "";
    const semaforoFiltro = selectSemaforo ? selectSemaforo.value : "";
    const presentacionFiltro = selectPresentacion ? selectPresentacion.value : "";
    const proveedorFiltro = selectProveedor ? selectProveedor.value.toLowerCase().trim() : "";

    const filtrados = productosCache.filter((producto) => {
        if (query !== "") {
            const nombre = (producto.nombre || "").toLowerCase();
            const proveedor = (producto.proveedor || "").toLowerCase();
            const codigo = (producto.codigo || "").toLowerCase();
            const cb = (producto.codigo_barras || "").toLowerCase();
            if (!nombre.includes(query) && !proveedor.includes(query) && !codigo.includes(query) && !cb.includes(query)) {
                return false;
            }
        }

        if (proveedorFiltro !== "") {
            const prov = (producto.proveedor || "").toLowerCase().trim();
            if (prov !== proveedorFiltro) return false;
        }

        if (presentacionFiltro !== "") {
            const pres = (producto.presentacion || "unidad").toLowerCase();
            if (pres !== presentacionFiltro) return false;
        }

        if (semaforoFiltro !== "") {
            const cat = obtenerCategoriaSemaforo(producto.fecha_vencimiento);
            if (cat !== semaforoFiltro) return false;
        }

        return true;
    });

    renderizarFilasProductos(filtrados);
}

async function cargarProductos() {
    const tbody = document.getElementById("productosBody");
    if (!tbody) return;
    const columnas = 8;
    mensajeEnTabla(tbody, columnas, "Cargando productos...");
    try {
        productosCache = await solicitar("obtener_productos.php");
        
        const resProd = document.getElementById("resumenProductos");
        const resStock = document.getElementById("resumenStock");
        if (resProd) resProd.textContent = productosCache.length;
        if (resStock) resStock.textContent = productosCache.reduce((total, p) => total + Number(p.stock), 0);
        
        filtrarYRenderizarProductos();
        llenarSelectoresProductos();
        llenarSelectorBarcodeProductos();
    } catch (error) {
        mensajeEnTabla(tbody, columnas, error.message, true);
    }
}

function llenarSelectoresProductos() {
    const venta = document.getElementById("ventaProducto");
    const filtroVentas = document.getElementById("filtroProducto");
    const filtroIngresos = document.getElementById("filtroIngresoProducto");

    if (filtroVentas) {
        const filtroActual = filtroVentas.value;
        filtroVentas.replaceChildren(new Option("Todos los productos", ""));
        productosCache.forEach((producto) => {
            const provTxt = producto.proveedor ? ` [🏢 ${producto.proveedor}]` : "";
            filtroVentas.appendChild(new Option(`${producto.nombre}${provTxt}`, producto.id));
        });
        filtroVentas.value = filtroActual;
    }

    if (filtroIngresos) {
        const filtroActual = filtroIngresos.value;
        filtroIngresos.replaceChildren(new Option("Todos los productos", ""));
        productosCache.forEach((producto) => {
            const provTxt = producto.proveedor ? ` [🏢 ${producto.proveedor}]` : "";
            filtroIngresos.appendChild(new Option(`${producto.nombre}${provTxt}`, producto.id));
        });
        filtroIngresos.value = filtroActual;
    }

    if (venta) {
        const valorActual = venta.value;
        venta.replaceChildren(new Option("-- Seleccionar producto --", ""));
        productosCache.forEach((producto) => {
            const cbTxt = producto.codigo_barras ? ` [CB: ${producto.codigo_barras}]` : "";
            const provTxt = producto.proveedor ? ` [🏢 ${producto.proveedor}]` : "";
            const opt = new Option(`${producto.nombre}${provTxt}${cbTxt} (Stock: ${producto.stock} un. - ${formatoMoneda.format(producto.precio)})`, producto.id);
            opt.dataset.precio = producto.precio;
            opt.dataset.stock = producto.stock;
            opt.dataset.presentacion = producto.presentacion || "unidad";
            opt.dataset.unidadesBulto = producto.unidades_por_bulto || 1;
            opt.dataset.codigoBarras = producto.codigo_barras || "";
            opt.dataset.proveedor = producto.proveedor || "";
            venta.appendChild(opt);
        });
        venta.value = valorActual;
    }
}

// -------------------------------------------------------------
// HISTORIAL Y TRAZABILIDAD DE MOVIMIENTOS DE PRODUCTO
// -------------------------------------------------------------
async function abrirModalHistorialProducto(productoId) {
    const modalEl = document.getElementById("modalHistorialProducto");
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    const lblNombre = document.getElementById("historialProductoNombre");
    const lblDetalles = document.getElementById("historialProductoDetalles");
    const lblStock = document.getElementById("historialProductoStock");
    const lblPrecio = document.getElementById("historialProductoPrecio");
    const tbody = document.getElementById("historialProductoBody");

    mensajeEnTabla(tbody, 5, "Cargando trazabilidad de movimientos...");
    modal.show();

    try {
        const respuesta = await solicitar(`obtener_movimientos_producto.php?producto_id=${productoId}`);
        const prod = respuesta.producto;
        const movimientos = respuesta.movimientos || [];

        if (lblNombre) lblNombre.textContent = prod.nombre;
        if (lblDetalles) {
            lblDetalles.textContent = `Código: ${prod.codigo || "—"} | Código de Barras: ${prod.codigo_barras || "—"} | Proveedor: ${prod.proveedor || "—"}`;
        }
        if (lblStock) lblStock.textContent = `${prod.stock_actual} un.`;
        if (lblPrecio) lblPrecio.textContent = formatoMoneda.format(prod.precio_actual);

        if (movimientos.length === 0) {
            mensajeEnTabla(tbody, 5, "No hay movimientos registrados para este producto aún.");
            return;
        }

        tbody.replaceChildren();
        movimientos.forEach((m) => {
            const fila = document.createElement("tr");

            // Fecha
            fila.append(celda(fechaLegible(m.fecha), "small text-nowrap"));

            // Tipo de Movimiento con Badge
            const tipoTd = document.createElement("td");
            const badge = document.createElement("span");
            let badgeClase = "badge-mov-edicion";
            let icono = "📝";

            switch (m.tipo) {
                case "ALTA_INICIAL":
                    badgeClase = "badge-mov-alta";
                    icono = "✨";
                    break;
                case "INGRESO_STOCK":
                    badgeClase = "badge-mov-ingreso";
                    icono = "📥";
                    break;
                case "VENTA":
                    badgeClase = "badge-mov-venta";
                    icono = "🛒";
                    break;
                case "VENTA_CANCELADA":
                    badgeClase = "badge-mov-cancelada";
                    icono = "↩️";
                    break;
                case "VENTA_MODIFICADA":
                case "AJUSTE_STOCK":
                    badgeClase = "badge-mov-ajuste";
                    icono = "⚖️";
                    break;
            }

            badge.className = `badge-mov ${badgeClase}`;
            badge.textContent = `${icono} ${m.tipo.replace(/_/g, " ")}`;
            tipoTd.appendChild(badge);
            fila.appendChild(tipoTd);

            // Detalle / Descripción
            fila.append(celda(m.descripcion || "Operación registrada", "small"));

            // Variación de Stock
            const varTd = document.createElement("td");
            if (m.diferencia !== null && m.diferencia !== undefined) {
                const dif = Number(m.diferencia);
                const spanVar = document.createElement("span");
                if (dif > 0) {
                    spanVar.className = "text-success fw-bold";
                    spanVar.textContent = `+${dif} un.`;
                } else if (dif < 0) {
                    spanVar.className = "text-danger fw-bold";
                    spanVar.textContent = `${dif} un.`;
                } else {
                    spanVar.className = "text-muted";
                    spanVar.textContent = "0 un.";
                }
                varTd.appendChild(spanVar);
                if (m.cantidad_nueva !== null && m.cantidad_nueva !== undefined) {
                    const smallSaldo = document.createElement("small");
                    smallSaldo.className = "text-muted d-block";
                    smallSaldo.textContent = `Saldo: ${m.cantidad_nueva} un.`;
                    varTd.appendChild(smallSaldo);
                }
            } else {
                varTd.innerHTML = '<span class="text-muted">—</span>';
            }
            fila.appendChild(varTd);

            // Responsable
            fila.append(celda(m.usuario_nombre || "Sistema", "small fw-semibold text-secondary"));

            tbody.appendChild(fila);
        });
    } catch (error) {
        mensajeEnTabla(tbody, 5, error.message, true);
    }
}

// -------------------------------------------------------------
// GESTIÓN COMPLETA DE PROVEEDORES
// -------------------------------------------------------------
async function cargarProveedores() {
    const tbody = document.getElementById("proveedoresBody");
    if (!tbody) return;
    const columnas = esAdmin ? 7 : 6;
    mensajeEnTabla(tbody, columnas, "Cargando proveedores...");

    try {
        proveedoresCache = await solicitar("obtener_proveedores.php");
        renderizarFilasProveedores(proveedoresCache);
        llenarSelectoresProveedores();
        
        const resProv = document.getElementById("resumenProveedores");
        if (resProv) resProv.textContent = proveedoresCache.length;
    } catch (error) {
        mensajeEnTabla(tbody, columnas, error.message, true);
    }
}

function renderizarFilasProveedores(proveedores) {
    const tbody = document.getElementById("proveedoresBody");
    if (!tbody) return;
    const columnas = esAdmin ? 7 : 6;

    if (proveedores.length === 0) {
        mensajeEnTabla(tbody, columnas, "No hay proveedores registrados.");
        return;
    }

    tbody.replaceChildren();
    proveedores.forEach((prov) => {
        const fila = document.createElement("tr");

        // 1. Nombre / Empresa
        const nomTd = document.createElement("td");
        const nomDiv = document.createElement("div");
        nomDiv.className = "fw-bold text-dark";
        nomDiv.textContent = prov.nombre || prov.proveedor;
        nomTd.appendChild(nomDiv);
        fila.appendChild(nomTd);

        // 2. CUIT / CUIL
        fila.append(celda(prov.cuit_cuil || "—", "font-monospace small text-muted"));

        // 3. Teléfono
        fila.append(celda(prov.telefono || "—", "small"));

        // 4. Correo
        const mailTd = document.createElement("td");
        if (prov.email) {
            const aMail = document.createElement("a");
            aMail.href = `mailto:${prov.email}`;
            aMail.className = "text-primary text-decoration-none small";
            aMail.textContent = prov.email;
            mailTd.appendChild(aMail);
        } else {
            mailTd.innerHTML = '<span class="text-muted small">—</span>';
        }
        fila.appendChild(mailTd);

        // 5. Dirección
        fila.append(celda(prov.direccion || "—", "small text-muted"));

        // 6. Catálogo Asignado
        const catTd = document.createElement("td");
        const badgeCat = document.createElement("span");
        badgeCat.className = "badge text-bg-light border";
        badgeCat.textContent = `${prov.total_productos} productos`;
        catTd.appendChild(badgeCat);
        if (prov.productos_lista && prov.productos_lista !== "—") {
            const smallList = document.createElement("small");
            smallList.className = "text-muted d-block mt-1 text-truncate";
            smallList.style.maxWidth = "220px";
            smallList.title = prov.productos_lista;
            smallList.textContent = prov.productos_lista;
            catTd.appendChild(smallList);
        }
        fila.appendChild(catTd);

        // 7. Acciones (Editar / Eliminar solo Admin)
        if (esAdmin) {
            const accTd = document.createElement("td");
            accTd.className = "text-end";
            const divAcc = document.createElement("div");
            divAcc.className = "d-inline-flex gap-1";

            const btnEdit = document.createElement("button");
            btnEdit.type = "button";
            btnEdit.className = "btn btn-outline-primary btn-sm";
            btnEdit.textContent = "Editar";
            btnEdit.addEventListener("click", () => abrirModalEditarProveedor(prov));

            const btnDel = document.createElement("button");
            btnDel.type = "button";
            btnDel.className = "btn btn-outline-danger btn-sm";
            btnDel.textContent = "Eliminar";
            btnDel.addEventListener("click", () => eliminarProveedor(prov.id, prov.nombre || prov.proveedor));

            divAcc.append(btnEdit, btnDel);
            accTd.appendChild(divAcc);
            fila.appendChild(accTd);
        }

        tbody.appendChild(fila);
    });
}

function llenarSelectoresProveedores() {
    const selectsForm = [
        document.getElementById("productoProveedor"),
        document.getElementById("editarProductoProveedor")
    ];

    selectsForm.forEach((sel) => {
        if (!sel) return;
        const valActual = sel.value;
        sel.replaceChildren(new Option("-- Seleccionar proveedor --", ""));
        
        proveedoresCache.forEach((p) => {
            const nom = p.nombre || p.proveedor;
            sel.appendChild(new Option(nom, nom));
        });

        const optNuevo = new Option("➕ Registrar nuevo proveedor...", "__NUEVO__");
        optNuevo.className = "fw-bold text-primary";
        sel.appendChild(optNuevo);

        sel.value = valActual;

        sel.onchange = (e) => {
            if (e.target.value === "__NUEVO__") {
                abrirModalCrearProveedor();
                e.target.value = "";
            }
        };
    });

    // Filtro de proveedores en pestaña Inventario
    const filtroProv = document.getElementById("filtroProductoProveedor");
    if (filtroProv) {
        const valActual = filtroProv.value;
        filtroProv.replaceChildren(new Option("Todos los proveedores", ""));
        const provsUnicos = new Set();
        proveedoresCache.forEach((p) => {
            const nom = (p.nombre || p.proveedor || "").trim();
            if (nom) provsUnicos.add(nom);
        });
        productosCache.forEach((p) => {
            const nom = (p.proveedor || "").trim();
            if (nom) provsUnicos.add(nom);
        });
        Array.from(provsUnicos).sort().forEach((nom) => {
            filtroProv.appendChild(new Option(`🏢 ${nom}`, nom));
        });
        filtroProv.value = valActual;
    }

    // Selector de proveedor en Carga Masiva
    const masivoProv = document.getElementById("masivoProveedor");
    if (masivoProv) {
        const valActual = masivoProv.value;
        masivoProv.replaceChildren(new Option("-- Seleccionar proveedor del remito --", ""));
        proveedoresCache.forEach((p) => {
            const nom = p.nombre || p.proveedor;
            masivoProv.appendChild(new Option(`🏢 ${nom}`, nom));
        });
        masivoProv.value = valActual;
    }
}

// Buscador en tiempo real de proveedores
const buscadorProveedoresInput = document.getElementById("buscadorProveedores");
if (buscadorProveedoresInput) {
    buscadorProveedoresInput.addEventListener("input", (e) => {
        const term = e.target.value.toLowerCase().trim();
        const filtrados = proveedoresCache.filter((p) => {
            const nom = (p.nombre || p.proveedor || "").toLowerCase();
            const cuit = (p.cuit_cuil || "").toLowerCase();
            const mail = (p.email || "").toLowerCase();
            const tel = (p.telefono || "").toLowerCase();
            return nom.includes(term) || cuit.includes(term) || mail.includes(term) || tel.includes(term);
        });
        renderizarFilasProveedores(filtrados);
    });
}

function abrirModalCrearProveedor() {
    const form = document.getElementById("formProveedor");
    if (!form) return;
    form.reset();
    document.getElementById("proveedorId").value = "";
    document.getElementById("tituloModalProveedor").textContent = "Registrar nuevo proveedor";
    document.getElementById("errorProveedor").classList.add("d-none");
    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalProveedor")).show();
}

function abrirModalEditarProveedor(prov) {
    const form = document.getElementById("formProveedor");
    if (!form) return;
    form.reset();
    document.getElementById("proveedorId").value = prov.id || "";
    document.getElementById("proveedorNombre").value = prov.nombre || prov.proveedor || "";
    document.getElementById("proveedorCuit").value = prov.cuit_cuil || "";
    document.getElementById("proveedorTelefono").value = prov.telefono || "";
    document.getElementById("proveedorEmail").value = prov.email || "";
    document.getElementById("proveedorDireccion").value = prov.direccion || "";
    document.getElementById("tituloModalProveedor").textContent = "Editar proveedor";
    document.getElementById("errorProveedor").classList.add("d-none");
    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalProveedor")).show();
}

const formProveedor = document.getElementById("formProveedor");
if (formProveedor) {
    formProveedor.addEventListener("submit", async (e) => {
        e.preventDefault();
        const errorBox = document.getElementById("errorProveedor");
        errorBox.classList.add("d-none");
        const provId = document.getElementById("proveedorId").value;
        const endpoint = provId ? "modificar_proveedor.php" : "guardar_proveedor.php";

        try {
            await solicitar(endpoint, { method: "POST", body: new FormData(formProveedor) });
            bootstrap.Modal.getInstance(document.getElementById("modalProveedor")).hide();
            formProveedor.reset();
            await Promise.all([cargarProveedores(), cargarProductos()]);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove("d-none");
        }
    });
}

async function eliminarProveedor(id, nombre) {
    if (!window.confirm(`¿Seguro que deseás eliminar al proveedor “${nombre}”?`)) return;
    const datos = new FormData();
    datos.append("id", id);
    try {
        await solicitar("eliminar_proveedor.php", { method: "POST", body: datos });
        await cargarProveedores();
    } catch (error) {
        alert(error.message);
    }
}

// -------------------------------------------------------------
// CARGA MASIVA / INGRESO POR LOTE DE PROVEEDOR
// -------------------------------------------------------------
function crearFilaMasiva(indice, datos = {}) {
    const tr = document.createElement("tr");
    tr.className = "fila-carga-masiva";

    // 1. Índice
    const tdIndex = document.createElement("td");
    tdIndex.className = "text-center fw-bold text-muted small celda-indice-masivo";
    tdIndex.textContent = String(indice);
    tr.appendChild(tdIndex);

    // 2. Nombre del Producto *
    const tdNombre = document.createElement("td");
    const inputNombre = document.createElement("input");
    inputNombre.type = "text";
    inputNombre.className = "form-control form-control-sm masivo-input-nombre";
    inputNombre.placeholder = "Ej: Coca Cola 1.5L";
    inputNombre.required = true;
    inputNombre.value = datos.nombre || "";
    inputNombre.addEventListener("input", recalcularResumenLoteMasivo);
    tdNombre.appendChild(inputNombre);
    tr.appendChild(tdNombre);

    // 3. Código de Barras (con botones 📷 y 🎲)
    const tdCb = document.createElement("td");
    const inputGroupCb = document.createElement("div");
    inputGroupCb.className = "input-group input-group-sm";
    
    const inputCb = document.createElement("input");
    inputCb.type = "text";
    inputCb.className = "form-control masivo-input-cb font-monospace";
    inputCb.placeholder = "Opcional / Auto";
    inputCb.value = datos.codigo_barras || "";

    const btnScan = document.createElement("button");
    btnScan.type = "button";
    btnScan.className = "btn btn-outline-secondary";
    btnScan.title = "Escanear con cámara";
    btnScan.innerHTML = "📷";
    btnScan.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            inputCb.value = codigo;
        });
    });

    const btnGen = document.createElement("button");
    btnGen.type = "button";
    btnGen.className = "btn btn-outline-secondary";
    btnGen.title = "Generar código aleatorio";
    btnGen.innerHTML = "🎲";
    btnGen.addEventListener("click", () => {
        inputCb.value = generarCodigoEan13();
    });

    inputGroupCb.append(inputCb, btnScan, btnGen);
    tdCb.appendChild(inputGroupCb);
    tr.appendChild(tdCb);

    // 4. Presentación
    const tdPres = document.createElement("td");
    const selectPres = document.createElement("select");
    selectPres.className = "form-select form-select-sm masivo-select-pres";
    [
        { val: "unidad", label: "Unidad" },
        { val: "caja", label: "Caja" },
        { val: "bulto", label: "Bulto" }
    ].forEach((opt) => {
        const o = new Option(opt.label, opt.val);
        if (datos.presentacion === opt.val) o.selected = true;
        selectPres.appendChild(o);
    });
    tdPres.appendChild(selectPres);
    tr.appendChild(tdPres);

    // 5. Unidades por Bulto
    const tdUnidBulto = document.createElement("td");
    const inputUnidBulto = document.createElement("input");
    inputUnidBulto.type = "number";
    inputUnidBulto.className = "form-control form-control-sm text-center masivo-input-unid-bulto";
    inputUnidBulto.min = "1";
    inputUnidBulto.value = datos.unidades_por_bulto || 1;
    inputUnidBulto.disabled = (selectPres.value === "unidad");
    inputUnidBulto.addEventListener("input", recalcularResumenLoteMasivo);
    tdUnidBulto.appendChild(inputUnidBulto);
    tr.appendChild(tdUnidBulto);

    selectPres.addEventListener("change", () => {
        if (selectPres.value === "unidad") {
            inputUnidBulto.value = 1;
            inputUnidBulto.disabled = true;
        } else {
            inputUnidBulto.disabled = false;
            if (Number(inputUnidBulto.value) <= 1) inputUnidBulto.value = 12;
        }
        recalcularResumenLoteMasivo();
    });

    // 6. Cantidad / Stock *
    const tdStock = document.createElement("td");
    const inputStock = document.createElement("input");
    inputStock.type = "number";
    inputStock.className = "form-control form-control-sm text-end masivo-input-stock";
    inputStock.min = "0";
    inputStock.value = datos.stock !== undefined ? datos.stock : 10;
    inputStock.required = true;
    inputStock.addEventListener("input", recalcularResumenLoteMasivo);
    tdStock.appendChild(inputStock);
    tr.appendChild(tdStock);

    // 7. Precio ($) *
    const tdPrecio = document.createElement("td");
    const inputPrecio = document.createElement("input");
    inputPrecio.type = "number";
    inputPrecio.step = "0.01";
    inputPrecio.min = "0";
    inputPrecio.className = "form-control form-control-sm text-end masivo-input-precio";
    inputPrecio.placeholder = "0.00";
    inputPrecio.required = true;
    inputPrecio.value = datos.precio !== undefined ? datos.precio : "";
    inputPrecio.addEventListener("input", recalcularResumenLoteMasivo);
    tdPrecio.appendChild(inputPrecio);
    tr.appendChild(tdPrecio);

    // 8. Vencimiento FIFO
    const tdVenc = document.createElement("td");
    const inputVenc = document.createElement("input");
    inputVenc.type = "date";
    inputVenc.className = "form-control form-control-sm masivo-input-venc";
    inputVenc.value = datos.fecha_vencimiento || "";
    tdVenc.appendChild(inputVenc);
    tr.appendChild(tdVenc);

    // 9. Botón Eliminar fila
    const tdAcc = document.createElement("td");
    tdAcc.className = "text-center";
    const btnDel = document.createElement("button");
    btnDel.type = "button";
    btnDel.className = "btn btn-outline-danger btn-sm p-1";
    btnDel.title = "Quitar este producto";
    btnDel.innerHTML = "🗑️";
    btnDel.addEventListener("click", () => {
        const tbody = document.getElementById("cuerpoFilasMasivas");
        if (tbody && tbody.querySelectorAll("tr").length <= 1) {
            alert("El lote debe tener al menos una fila de producto.");
            return;
        }
        tr.remove();
        renumerarFilasMasivas();
        recalcularResumenLoteMasivo();
    });
    tdAcc.appendChild(btnDel);
    tr.appendChild(tdAcc);

    return tr;
}

function renumerarFilasMasivas() {
    const filas = document.querySelectorAll("#cuerpoFilasMasivas tr");
    filas.forEach((f, idx) => {
        const celdaIdx = f.querySelector(".celda-indice-masivo");
        if (celdaIdx) celdaIdx.textContent = String(idx + 1);
    });
}

function recalcularResumenLoteMasivo() {
    const filas = document.querySelectorAll("#cuerpoFilasMasivas tr");
    let totalItems = 0;
    let totalUnidadesFisicas = 0;
    let valorTotal = 0;

    filas.forEach((fila) => {
        const inputNombre = fila.querySelector(".masivo-input-nombre");
        const selectPres = fila.querySelector(".masivo-select-pres");
        const inputUnidBulto = fila.querySelector(".masivo-input-unid-bulto");
        const inputStock = fila.querySelector(".masivo-input-stock");
        const inputPrecio = fila.querySelector(".masivo-input-precio");

        const nombre = inputNombre ? inputNombre.value.trim() : "";
        const pres = selectPres ? selectPres.value : "unidad";
        const unidBulto = Math.max(1, Number(inputUnidBulto ? inputUnidBulto.value : 1) || 1);
        const cant = Number(inputStock ? inputStock.value : 0) || 0;
        const precio = Number(inputPrecio ? inputPrecio.value : 0) || 0;

        if (nombre !== "" || cant > 0 || precio > 0) {
            totalItems++;
            const unidsFisicas = (pres === "unidad") ? cant : cant * unidBulto;
            totalUnidadesFisicas += unidsFisicas;
            valorTotal += (unidsFisicas * precio);
        }
    });

    const elProd = document.getElementById("resumenLoteTotalProd");
    const elUnid = document.getElementById("resumenLoteTotalUnidades");
    const elValor = document.getElementById("resumenLoteValorTotal");

    if (elProd) elProd.textContent = totalItems;
    if (elUnid) elUnid.textContent = `${totalUnidadesFisicas} un.`;
    if (elValor) elValor.textContent = formatoMoneda.format(valorTotal);
}

function inicializarModalCargaMasiva() {
    const tbody = document.getElementById("cuerpoFilasMasivas");
    if (!tbody) return;
    tbody.replaceChildren();

    const errBox = document.getElementById("errorCargaMasiva");
    const okBox = document.getElementById("exitoCargaMasiva");
    if (errBox) errBox.classList.add("d-none");
    if (okBox) okBox.classList.add("d-none");

    const selProv = document.getElementById("masivoProveedor");
    const txtProv = document.getElementById("masivoProveedorTexto");
    const inputFact = document.getElementById("masivoNumeroFactura");
    const chkSinFact = document.getElementById("masivoSinFactura");

    if (selProv) selProv.value = "";
    if (txtProv) txtProv.value = "";
    if (inputFact) {
        inputFact.value = "";
        inputFact.disabled = false;
    }
    if (chkSinFact) chkSinFact.checked = false;

    // Agregar 3 filas iniciales
    for (let i = 1; i <= 3; i++) {
        tbody.appendChild(crearFilaMasiva(i));
    }
    recalcularResumenLoteMasivo();
}

const chkMasivoSinFactura = document.getElementById("masivoSinFactura");
if (chkMasivoSinFactura) {
    chkMasivoSinFactura.addEventListener("change", (e) => {
        const inputFact = document.getElementById("masivoNumeroFactura");
        if (inputFact) {
            inputFact.disabled = e.target.checked;
            if (e.target.checked) inputFact.value = "";
        }
    });
}

const btnAgregarFilaMasiva = document.getElementById("btnAgregarFilaMasiva");
if (btnAgregarFilaMasiva) {
    btnAgregarFilaMasiva.addEventListener("click", () => {
        const tbody = document.getElementById("cuerpoFilasMasivas");
        if (!tbody) return;
        const nuevoIndice = tbody.querySelectorAll("tr").length + 1;
        const nuevaFila = crearFilaMasiva(nuevoIndice);
        tbody.appendChild(nuevaFila);
        const inputNombre = nuevaFila.querySelector(".masivo-input-nombre");
        if (inputNombre) inputNombre.focus();
        recalcularResumenLoteMasivo();
    });
}

const btnGuardarLoteMasivo = document.getElementById("btnGuardarLoteMasivo");
if (btnGuardarLoteMasivo) {
    btnGuardarLoteMasivo.addEventListener("click", async () => {
        const errBox = document.getElementById("errorCargaMasiva");
        const okBox = document.getElementById("exitoCargaMasiva");
        if (errBox) errBox.classList.add("d-none");
        if (okBox) okBox.classList.add("d-none");

        const selProv = document.getElementById("masivoProveedor");
        const txtProv = document.getElementById("masivoProveedorTexto");
        const proveedor = (txtProv && txtProv.value.trim()) ? txtProv.value.trim() : (selProv ? selProv.value.trim() : "");
        const numFactura = document.getElementById("masivoNumeroFactura") ? document.getElementById("masivoNumeroFactura").value.trim() : "";
        const sinFactura = document.getElementById("masivoSinFactura") ? document.getElementById("masivoSinFactura").checked : false;

        const filas = document.querySelectorAll("#cuerpoFilasMasivas tr");
        const productosLote = [];
        const erroresValidacion = [];

        filas.forEach((fila, idx) => {
            const numFila = idx + 1;
            const inputNombre = fila.querySelector(".masivo-input-nombre");
            const inputCb = fila.querySelector(".masivo-input-cb");
            const selectPres = fila.querySelector(".masivo-select-pres");
            const inputUnidBulto = fila.querySelector(".masivo-input-unid-bulto");
            const inputStock = fila.querySelector(".masivo-input-stock");
            const inputPrecio = fila.querySelector(".masivo-input-precio");
            const inputVenc = fila.querySelector(".masivo-input-venc");

            const nombre = inputNombre ? inputNombre.value.trim() : "";
            const cb = inputCb ? inputCb.value.trim() : "";
            const pres = selectPres ? selectPres.value : "unidad";
            const unidBulto = Math.max(1, Number(inputUnidBulto ? inputUnidBulto.value : 1) || 1);
            const stock = Number(inputStock ? inputStock.value : 0);
            const precio = Number(inputPrecio ? inputPrecio.value : 0);
            const venc = inputVenc ? inputVenc.value.trim() : "";

            // Ignorar filas totalmente vacías
            if (nombre === "" && stock === 0 && precio === 0 && cb === "") {
                return;
            }

            if (nombre === "") {
                erroresValidacion.push(`Fila #${numFila}: El nombre del producto es obligatorio.`);
                return;
            }

            if (precio <= 0) {
                erroresValidacion.push(`Fila #${numFila} ("${nombre}"): El precio debe ser mayor a 0.`);
                return;
            }

            if (stock < 0) {
                erroresValidacion.push(`Fila #${numFila} ("${nombre}"): La cantidad no puede ser negativa.`);
                return;
            }

            productosLote.push({
                nombre: nombre,
                codigo_barras: cb,
                presentacion: pres,
                unidades_por_bulto: unidBulto,
                stock: stock,
                precio: precio,
                fecha_vencimiento: venc
            });
        });

        if (erroresValidacion.length > 0) {
            if (errBox) {
                errBox.innerHTML = `<strong>Atención con los siguientes datos:</strong><ul class="mb-0 mt-1">${erroresValidacion.map(e => `<li>${e}</li>`).join("")}</ul>`;
                errBox.classList.remove("d-none");
            }
            return;
        }

        if (productosLote.length === 0) {
            if (errBox) {
                errBox.textContent = "Completá al menos un producto para guardar el lote.";
                errBox.classList.remove("d-none");
            }
            return;
        }

        btnGuardarLoteMasivo.disabled = true;
        const textoOriginal = btnGuardarLoteMasivo.innerHTML;
        btnGuardarLoteMasivo.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando ${productosLote.length} productos...`;

        try {
            const respuesta = await solicitar("guardar_productos_masivo.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    proveedor: proveedor,
                    numero_factura: numFactura,
                    sin_factura: sinFactura,
                    productos: productosLote
                })
            });

            if (okBox) {
                okBox.innerHTML = `<strong>¡Lote guardado con éxito!</strong> ${respuesta.mensaje || `Se registraron ${respuesta.total_guardados} productos.`}`;
                okBox.classList.remove("d-none");
            }

            // Recargar datos en el sistema
            await Promise.all([cargarProductos(), cargarProveedores(), cargarIngresos()]);

            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById("modalCargaMasiva"));
                if (modal) modal.hide();
                inicializarModalCargaMasiva();
            }, 1200);

        } catch (error) {
            if (errBox) {
                errBox.textContent = error.message || "Error al procesar la carga masiva.";
                errBox.classList.remove("d-none");
            }
        } finally {
            btnGuardarLoteMasivo.disabled = false;
            btnGuardarLoteMasivo.innerHTML = textoOriginal;
        }
    });
}

const modalCargaMasivaEl = document.getElementById("modalCargaMasiva");
if (modalCargaMasivaEl) {
    modalCargaMasivaEl.addEventListener("show.bs.modal", () => {
        const tbody = document.getElementById("cuerpoFilasMasivas");
        if (!tbody || tbody.children.length === 0) {
            inicializarModalCargaMasiva();
        }
    });
}

// -------------------------------------------------------------
// GENERADOR E IMPRESIÓN DE CÓDIGOS DE BARRA
// -------------------------------------------------------------
function llenarSelectorBarcodeProductos() {
    const selector = document.getElementById("barcodeSelectorProducto");
    if (!selector) return;
    const valActual = selector.value;
    selector.replaceChildren(new Option("-- Ingreso manual / Nuevo producto --", ""));
    productosCache.forEach((p) => {
        const provTxt = p.proveedor ? ` [🏢 ${p.proveedor}]` : "";
        const opt = new Option(`${p.nombre}${provTxt} (Stock: ${p.stock} un. - ${formatoMoneda.format(p.precio)})`, p.id);
        selector.appendChild(opt);
    });
    selector.value = valActual;
}

function actualizarPreviewBarcode() {
    const inputCodigo = document.getElementById("barcodeInputCodigo");
    const inputNombre = document.getElementById("barcodeInputNombre");
    const inputPrecio = document.getElementById("barcodeInputPrecio");
    const selectFormato = document.getElementById("barcodeInputFormato");
    const svgElement = document.getElementById("previewBarcodeSvg");
    const lblNombre = document.getElementById("previewEtiquetaNombre");
    const lblPrecio = document.getElementById("previewEtiquetaPrecio");

    if (!svgElement) return;

    const codigo = inputCodigo && inputCodigo.value.trim() ? inputCodigo.value.trim() : "779123456789";
    const nombre = inputNombre && inputNombre.value.trim() ? inputNombre.value.trim() : "Nombre del Producto";
    const precio = inputPrecio && inputPrecio.value ? Number(inputPrecio.value) : 0;
    const formato = selectFormato && selectFormato.value ? selectFormato.value : "CODE128";

    if (lblNombre) lblNombre.textContent = nombre;
    if (lblPrecio) lblPrecio.textContent = precio > 0 ? formatoMoneda.format(precio) : "$ 0,00";

    // Limpiar contenido previo del SVG
    svgElement.innerHTML = "";

    if (typeof JsBarcode === "function") {
        let formatoFinal = formato;
        if (formatoFinal === "EAN13" && (!/^\d{13}$/.test(codigo))) {
            formatoFinal = "CODE128";
        }

        try {
            JsBarcode(svgElement, codigo, {
                format: formatoFinal,
                lineColor: "#0f172a",
                width: 2,
                height: 50,
                displayValue: true,
                fontSize: 14,
                font: "monospace"
            });
        } catch (e) {
            // Fallback a CODE128 si falla validación
            try {
                svgElement.innerHTML = "";
                JsBarcode(svgElement, codigo, {
                    format: "CODE128",
                    lineColor: "#0f172a",
                    width: 2,
                    height: 50,
                    displayValue: true,
                    fontSize: 14,
                    font: "monospace"
                });
            } catch (_) {}
        }
    }
}

const barcodeSelector = document.getElementById("barcodeSelectorProducto");
if (barcodeSelector) {
    barcodeSelector.addEventListener("change", (e) => {
        const prodVal = String(e.target.value || "").trim();
        if (!prodVal) {
            actualizarPreviewBarcode();
            return;
        }
        const prod = productosCache.find((p) => String(p.id) === prodVal || String(p._id) === prodVal);
        if (prod) {
            const inputCodigo = document.getElementById("barcodeInputCodigo");
            const inputNombre = document.getElementById("barcodeInputNombre");
            const inputPrecio = document.getElementById("barcodeInputPrecio");
            if (inputCodigo) inputCodigo.value = prod.codigo_barras || prod.codigo || generarCodigoEan13();
            if (inputNombre) inputNombre.value = prod.nombre || "";
            if (inputPrecio) inputPrecio.value = prod.precio !== undefined ? prod.precio : "";
        }
        actualizarPreviewBarcode();
    });
}

["barcodeInputCodigo", "barcodeInputNombre", "barcodeInputPrecio", "barcodeInputFormato"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener("input", actualizarPreviewBarcode);
        el.addEventListener("change", actualizarPreviewBarcode);
    }
});

function generarCodigoEan13() {
    let codigo = "779" + Math.floor(Math.random() * 1000000000).toString().padStart(9, "0");
    return codigo;
}

const btnRandomCode = document.getElementById("btnGenerarCodigoRandom");
if (btnRandomCode) {
    btnRandomCode.addEventListener("click", () => {
        document.getElementById("barcodeInputCodigo").value = generarCodigoEan13();
        actualizarPreviewBarcode();
    });
}

const btnCopiarCode = document.getElementById("btnCopiarCodigoBarras");
if (btnCopiarCode) {
    btnCopiarCode.addEventListener("click", () => {
        const val = document.getElementById("barcodeInputCodigo").value;
        if (val) {
            navigator.clipboard.writeText(val);
            alert(`Código "${val}" copiado al portapapeles.`);
        }
    });
}

const btnImprimir = document.getElementById("btnImprimirEtiqueta");
if (btnImprimir) {
    btnImprimir.addEventListener("click", () => {
        window.print();
    });
}

// -------------------------------------------------------------
// ESCÁNER DE CÓDIGO DE BARRAS (CÁMARA WEB / MÓVIL + PISTOLA LECTORA)
// -------------------------------------------------------------
function abrirModalScannerCamara(callbackExito) {
    callbackScannerActivo = callbackExito;
    const modalEl = document.getElementById("modalScannerCamara");
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    iniciarLectorCamara();
}

function iniciarLectorCamara() {
    const readerDiv = document.getElementById("qr-reader");
    if (!readerDiv) return;

    if (html5QrScannerInstance) {
        html5QrScannerInstance.clear().catch(() => {});
    }

    html5QrScannerInstance = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: { width: 250, height: 150 } };

    html5QrScannerInstance.start(
        { facingMode: "environment" },
        config,
        (decodedText) => {
            // Sonido suave de beep o vibración
            if (navigator.vibrate) navigator.vibrate(100);
            
            detenerLectorCamara();
            bootstrap.Modal.getInstance(document.getElementById("modalScannerCamara")).hide();

            if (callbackScannerActivo) {
                callbackScannerActivo(decodedText);
            }
        },
        () => {} // Ignorar frames sin código
    ).catch((err) => {
        const resBox = document.getElementById("scannerResultado");
        if (resBox) {
            resBox.textContent = "No se pudo acceder a la cámara o no hay permisos suficientes: " + err;
            resBox.classList.remove("d-none");
            resBox.className = "alert alert-warning py-2";
        }
    });
}

function detenerLectorCamara() {
    if (html5QrScannerInstance) {
        html5QrScannerInstance.stop().then(() => {
            html5QrScannerInstance.clear();
            html5QrScannerInstance = null;
        }).catch(() => {});
    }
}

const modalScannerEl = document.getElementById("modalScannerCamara");
if (modalScannerEl) {
    modalScannerEl.addEventListener("hidden.bs.modal", detenerLectorCamara);
}

// Botones de escaneo integrados
const btnEscanearFiltro = document.getElementById("btnEscanearFiltro");
const btnEscanearProductoTabla = document.getElementById("btnEscanearProductoTabla");
const btnAbrirScannerGlobal = document.getElementById("btnAbrirScannerGlobal");

if (btnEscanearFiltro) {
    btnEscanearFiltro.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            const input = document.getElementById("filtroProductoBusqueda");
            if (input) {
                input.value = codigo;
                filtrarYRenderizarProductos();
            }
        });
    });
}

if (btnEscanearProductoTabla) {
    btnEscanearProductoTabla.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            const input = document.getElementById("filtroProductoBusqueda");
            if (input) {
                input.value = codigo;
                filtrarYRenderizarProductos();
            }
        });
    });
}

if (btnAbrirScannerGlobal) {
    btnAbrirScannerGlobal.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            // Buscar producto por código de barras
            const encontrado = productosCache.find((p) => (p.codigo_barras === codigo || p.codigo === codigo));
            if (encontrado) {
                abrirModalHistorialProducto(encontrado.id);
            } else {
                const tabProdBtn = document.getElementById("tab-productos-btn") || document.getElementById("tab-vendedor-productos-btn");
                if (tabProdBtn) bootstrap.Tab.getOrCreateInstance(tabProdBtn).show();
                const input = document.getElementById("filtroProductoBusqueda");
                if (input) {
                    input.value = codigo;
                    filtrarYRenderizarProductos();
                }
            }
        });
    });
}

// Escáner en Modal de Alta de Producto
const btnScanAlta = document.getElementById("btnEscanearCodigoModalAlta");
if (btnScanAlta) {
    btnScanAlta.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            document.getElementById("productoCodigoBarras").value = codigo;
        });
    });
}
const btnGenAlta = document.getElementById("btnGenerarCodigoModalAlta");
if (btnGenAlta) {
    btnGenAlta.addEventListener("click", () => {
        document.getElementById("productoCodigoBarras").value = generarCodigoEan13();
    });
}

// Escáner en Modal de Edición de Producto
const btnScanEdicion = document.getElementById("btnEscanearCodigoModalEdicion");
if (btnScanEdicion) {
    btnScanEdicion.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            document.getElementById("editarProductoCodigoBarras").value = codigo;
        });
    });
}
const btnGenEdicion = document.getElementById("btnGenerarCodigoModalEdicion");
if (btnGenEdicion) {
    btnGenEdicion.addEventListener("click", () => {
        document.getElementById("editarProductoCodigoBarras").value = generarCodigoEan13();
    });
}

// Escáner en Modal de Venta
const btnScanVenta = document.getElementById("btnEscanearProductoVenta");
if (btnScanVenta) {
    btnScanVenta.addEventListener("click", () => {
        abrirModalScannerCamara((codigo) => {
            const encontrado = productosCache.find((p) => (p.codigo_barras === codigo || p.codigo === codigo));
            if (encontrado) {
                const sel = document.getElementById("ventaProducto");
                if (sel) {
                    sel.value = encontrado.id;
                    sel.dispatchEvent(new Event("change"));
                }
            } else {
                alert(`No se encontró ningún producto con código "${codigo}".`);
            }
        });
    });
}

// Detección de lector de código de barras físico USB / Bluetooth (Enter tras lectura rápida)
document.addEventListener("keydown", (e) => {
    // Si el usuario presiona Enter en el buscador de productos y coincide con un código de barras
    if (e.key === "Enter" && document.activeElement && document.activeElement.id === "filtroProductoBusqueda") {
        const val = document.activeElement.value.trim();
        const prod = productosCache.find((p) => p.codigo_barras === val || p.codigo === val);
        if (prod) {
            filtrarYRenderizarProductos();
        }
    }
});

// -------------------------------------------------------------
// RESTO DE FUNCIONES DE VENTAS, INGRESOS Y CLIENTES
// -------------------------------------------------------------
async function cargarVentas(filtros = {}) {
    const tbody = document.getElementById("ventasBody");
    if (!tbody) return;
    const columnas = esAdmin ? 12 : 10;
    mensajeEnTabla(tbody, columnas, "Cargando ventas...");

    const params = new URLSearchParams();
    Object.entries(filtros).forEach(([k, v]) => {
        if (v !== "" && v !== null && v !== undefined) params.append(k, v);
    });

    try {
        const ventas = await solicitar(`obtener_ventas.php?${params.toString()}`);
        const resVentas = document.getElementById("resumenVentas");
        if (resVentas) resVentas.textContent = ventas.length;

        if (ventas.length === 0) {
            mensajeEnTabla(tbody, columnas, "No se registraron ventas.");
            return;
        }

        tbody.replaceChildren();
        ventas.forEach((venta) => {
            const fila = document.createElement("tr");
            fila.append(celda(String(venta.id)));
            fila.append(celda(fechaLegible(venta.fecha)));
            fila.append(celda(venta.cliente || "—"));
            fila.append(celda(venta.producto_nombre || "—"));

            const empaqueTexto = (venta.tipo_venta && venta.tipo_venta !== "unidad") 
                ? `${venta.cantidad_empaque || venta.cantidad} ${venta.tipo_venta}(s) (${venta.cantidad} un.)` 
                : `${venta.cantidad} un.`;
            fila.append(celda(empaqueTexto));

            fila.append(celda(formatoMoneda.format(venta.precio_unitario)));

            const descTd = document.createElement("td");
            if (Number(venta.descuento_monto) > 0) {
                descTd.innerHTML = `<span class="text-danger fw-semibold">-${Number(venta.descuento_porcentaje)}%</span><br><small class="text-muted">(${formatoMoneda.format(venta.descuento_monto)})</small>`;
            } else {
                descTd.textContent = "0%";
            }
            fila.appendChild(descTd);

            fila.append(celda(formatoMoneda.format(venta.total), "fw-bold text-primary"));

            if (esAdmin) {
                fila.append(celda(venta.vendedor || "—"));
            }

            const estadoTd = document.createElement("td");
            const badge = document.createElement("span");
            const esCancelada = venta.estado === "CANCELADA";
            const esModificada = venta.estado === "MODIFICADA";
            badge.className = `badge ${esCancelada ? "text-bg-danger" : esModificada ? "text-bg-warning" : "text-bg-success"}`;
            badge.textContent = venta.estado || "ACTIVA";
            estadoTd.appendChild(badge);
            fila.appendChild(estadoTd);

            if (esAdmin) {
                fila.append(celda(venta.fecha_modificacion ? fechaLegible(venta.fecha_modificacion) : "—"));
            }

            const accion = document.createElement("td");
            accion.className = "text-end";
            if (!esCancelada) {
                const grupo = document.createElement("div");
                grupo.className = "d-inline-flex gap-1";

                const btnModificar = document.createElement("button");
                btnModificar.type = "button";
                btnModificar.className = "btn btn-outline-primary btn-sm";
                btnModificar.textContent = "Modificar";
                btnModificar.addEventListener("click", () => {
                    document.getElementById("modificarVentaId").value = venta.id;
                    document.getElementById("modificarCantidad").value = venta.cantidad;
                    document.getElementById("motivoModificacion").value = "";
                    document.getElementById("errorModificarVenta").classList.add("d-none");
                    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalModificarVenta")).show();
                });

                const btnCancelar = document.createElement("button");
                btnCancelar.type = "button";
                btnCancelar.className = "btn btn-outline-danger btn-sm";
                btnCancelar.textContent = "Cancelar";
                btnCancelar.addEventListener("click", () => {
                    document.getElementById("cancelarVentaId").value = venta.id;
                    document.getElementById("motivoCancelacion").value = "";
                    document.getElementById("errorCancelarVenta").classList.add("d-none");
                    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCancelarVenta")).show();
                });

                grupo.append(btnModificar, btnCancelar);
                accion.appendChild(grupo);
            } else {
                accion.textContent = "—";
            }
            fila.appendChild(accion);

            tbody.appendChild(fila);
        });
    } catch (error) {
        mensajeEnTabla(tbody, columnas, error.message, true);
    }
}

async function cargarIngresos(filtros = {}) {
    const tbody = document.getElementById("ingresosBody");
    if (!tbody) return;
    const columnas = 9;
    mensajeEnTabla(tbody, columnas, "Cargando kardex de ingresos...");

    const params = new URLSearchParams();
    Object.entries(filtros).forEach(([k, v]) => {
        if (v !== "" && v !== null && v !== undefined) params.append(k, v);
    });

    try {
        const ingresos = await solicitar(`obtener_ingresos.php?${params.toString()}`);
        const resIng = document.getElementById("resumenIngresos");
        if (resIng) resIng.textContent = `${ingresos.length} entradas`;

        if (ingresos.length === 0) {
            mensajeEnTabla(tbody, columnas, "No hay ingresos de mercadería registrados.");
            return;
        }

        tbody.replaceChildren();
        ingresos.forEach((ing) => {
            const fila = document.createElement("tr");
            fila.append(celda(String(ing.id)));
            fila.append(celda(fechaLegible(ing.fecha)));

            // N° Factura
            const factTd = document.createElement("td");
            if (ing.numero_factura && ing.numero_factura !== "—" && ing.numero_factura !== "Sin Factura") {
                factTd.innerHTML = `<span class="badge text-bg-light border font-monospace">📄 ${ing.numero_factura}</span>`;
            } else if (ing.sin_factura || ing.numero_factura === "Sin Factura") {
                factTd.innerHTML = `<span class="badge text-bg-light border text-muted">Sin factura</span>`;
            } else {
                factTd.innerHTML = `<span class="text-muted small">—</span>`;
            }
            fila.appendChild(factTd);

            fila.append(celda(ing.producto_nombre, "fw-bold"));

            const cantEmpaque = (ing.presentacion && ing.presentacion !== "unidad" && Number(ing.unidades_por_bulto) > 1)
                ? `${ing.cantidad} ${ing.presentacion}(s) (${ing.total_unidades} un.)`
                : `${ing.total_unidades} un.`;
            fila.append(celda(cantEmpaque));

            fila.append(celda(ing.proveedor || "—"));

            const vencTd = document.createElement("td");
            vencTd.appendChild(crearBadgeVencimiento(ing.fecha_vencimiento));
            fila.appendChild(vencTd);

            fila.append(celda(ing.usuario || "Admin"));
            fila.append(celda(ing.motivo || "Alta de inventario", "small text-muted"));

            tbody.appendChild(fila);
        });
    } catch (error) {
        mensajeEnTabla(tbody, columnas, error.message, true);
    }
}

let clientesCache = [];

async function cargarClientes() {
    const select = document.getElementById("ventaCliente");
    const filtroCliente = document.getElementById("filtroCliente");
    if (!select && !filtroCliente) return;

    try {
        clientesCache = await solicitar("obtener_clientes.php");
        if (select) {
            const valActual = select.value;
            select.replaceChildren(new Option("-- Seleccionar cliente o escanear QR --", ""));
            clientesCache.forEach((c) => {
                select.appendChild(new Option(`${c.nombre} ${c.apellido} (DNI ${c.dni})`, c.id));
            });
            select.value = valActual;
        }
        if (filtroCliente) {
            const valActualFiltro = filtroCliente.value;
            filtroCliente.replaceChildren(new Option("Todos los clientes", ""));
            clientesCache.forEach((c) => {
                filtroCliente.appendChild(new Option(`${c.nombre} ${c.apellido}`, c.id));
            });
            filtroCliente.value = valActualFiltro;
        }
    } catch (error) {
        console.error("Error al cargar clientes:", error);
    }
}

async function cargarVendedores() {
    const filtroVendedor = document.getElementById("filtroVendedor");
    const filtroIngresoUsuario = document.getElementById("filtroIngresoUsuario");
    if (!filtroVendedor && !filtroIngresoUsuario) return;

    try {
        const vendedores = await solicitar("obtener_vendedores.php");
        if (filtroVendedor) {
            filtroVendedor.replaceChildren(new Option("Todos los vendedores", ""));
            vendedores.forEach((v) => {
                filtroVendedor.appendChild(new Option(`${v.nombre} ${v.apellido} (${v.rol})`, v.id));
            });
        }
        if (filtroIngresoUsuario) {
            filtroIngresoUsuario.replaceChildren(new Option("Todos los usuarios", ""));
            vendedores.forEach((v) => {
                filtroIngresoUsuario.appendChild(new Option(`${v.nombre} ${v.apellido}`, v.id));
            });
        }
    } catch (error) {
        console.error("Error al cargar vendedores:", error);
    }
}

// Carga de Solicitudes de Atención de Clientes (Solo Admin)
async function cargarSolicitudesVendedor() {
    if (!esAdmin) return;
    const tbody = document.getElementById("solicitudesAtencionBody");
    const badgePendientes = document.getElementById("badgeSolicitudesPendientes");
    if (!tbody) return;
    try {
        solicitudesCache = await solicitar("obtener_solicitudes_vendedor.php");
        tbody.replaceChildren();
        const pendientes = solicitudesCache.filter((s) => s.estado === "PENDIENTE");
        
        if (badgePendientes) {
            badgePendientes.textContent = pendientes.length;
            badgePendientes.className = `badge rounded-pill ${pendientes.length > 0 ? "text-bg-danger" : "text-bg-secondary"}`;
        }

        if (solicitudesCache.length === 0) {
            mensajeEnTabla(tbody, 6, "No hay solicitudes de atención registradas.");
            return;
        }

        solicitudesCache.forEach((sol) => {
            const fila = document.createElement("tr");
            fila.append(celda(`#${sol.id}`, "fw-semibold"));
            fila.append(celda(fechaLegible(sol.fecha)));
            fila.append(celda(`${sol.cliente_nombre} (DNI ${sol.cliente_dni})`, "fw-bold"));
            fila.append(celda(sol.mensaje || "Solicitud de asistencia directa.", "small text-muted"));
            
            const estadoTd = document.createElement("td");
            const esPendiente = sol.estado === "PENDIENTE";
            const badge = document.createElement("span");
            badge.className = `badge ${esPendiente ? "text-bg-warning" : "text-bg-success"}`;
            badge.textContent = esPendiente ? "Pendiente" : `Atendida por ${sol.atendido_por_nombre || "Equipo"}`;
            estadoTd.appendChild(badge);
            fila.appendChild(estadoTd);

            const accionTd = document.createElement("td");
            accionTd.className = "text-end";
            if (esPendiente) {
                const btnAtender = document.createElement("button");
                btnAtender.type = "button";
                btnAtender.className = "btn btn-success btn-sm";
                btnAtender.textContent = "✓ Atender";
                btnAtender.addEventListener("click", () => marcarSolicitudAtendida(sol.id));
                accionTd.appendChild(btnAtender);
            } else {
                accionTd.textContent = "—";
            }
            fila.appendChild(accionTd);
            tbody.appendChild(fila);
        });
    } catch (error) {
        console.error("Error al cargar solicitudes:", error);
    }
}

async function marcarSolicitudAtendida(id) {
    const datos = new FormData();
    datos.append("id", id);
    try {
        await solicitar("atender_solicitud_vendedor.php", { method: "POST", body: datos });
        await cargarSolicitudesVendedor();
    } catch (error) {
        alert(error.message);
    }
}

async function eliminarProducto(id, nombre) {
    if (!window.confirm(`¿Eliminar “${nombre}”? Esta acción también afectará su historial asociado.`)) return;
    const datos = new FormData();
    datos.append("id", id);
    try {
        await solicitar("eliminar_producto.php", { method: "POST", body: datos });
        await Promise.all([cargarProductos(), cargarIngresos(), cargarVentas(), cargarProveedores()]);
    } catch (error) {
        window.alert(error.message);
    }
}

function abrirModalEditarProducto(producto) {
    document.getElementById("editarProductoId").value = producto.id;
    document.getElementById("editarProductoNombre").value = producto.nombre;
    document.getElementById("editarProductoCodigoBarras").value = producto.codigo_barras || "";
    document.getElementById("editarProductoPrecio").value = producto.precio;
    document.getElementById("editarProductoStock").value = producto.stock;
    document.getElementById("editarProductoPresentacion").value = producto.presentacion || "unidad";
    document.getElementById("editarProductoUnidadesBulto").value = producto.unidades_por_bulto || 1;
    document.getElementById("editarProductoVencimiento").value = producto.fecha_vencimiento || "";
    document.getElementById("editarProductoProveedor").value = producto.proveedor || "";
    
    configurarSelectorPresentacion("editarProductoPresentacion", "contenedorEditarUnidadesBulto", "editarProductoUnidadesBulto");
    document.getElementById("errorEditarProducto").classList.add("d-none");
    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEditarProducto")).show();
}

function configurarSelectorPresentacion(selectId, contenedorId, inputUnidadesId) {
    const select = document.getElementById(selectId);
    const contenedor = document.getElementById(contenedorId);
    const input = document.getElementById(inputUnidadesId);
    if (!select || !contenedor || !input) return;

    const actualizar = () => {
        const valor = select.value;
        if (valor === "caja" || valor === "bulto") {
            contenedor.classList.remove("d-none");
            input.required = true;
            if (Number(input.value) <= 1) input.value = valor === "caja" ? 12 : 24;
        } else {
            contenedor.classList.add("d-none");
            input.required = false;
            input.value = 1;
        }
    };

    select.addEventListener("change", actualizar);
    actualizar();
}

// -------------------------------------------------------------
// CARRITO DE VENTAS MULTIPRODUCTO Y CÁLCULO EN VIVO
// -------------------------------------------------------------
let carritoVentas = [];

function limpiarCarritoVentas() {
    carritoVentas = [];
    renderizarCarritoVentas();
    const infoEmpaque = document.getElementById("ventaInfoEmpaque");
    if (infoEmpaque) infoEmpaque.classList.add("d-none");
    const errorBox = document.getElementById("errorVenta");
    if (errorBox) errorBox.classList.add("d-none");
}

function renderizarCarritoVentas() {
    const tbody = document.getElementById("carritoVentasBody");
    const lblCount = document.getElementById("carritoVentasCount");
    const lblTotal = document.getElementById("carritoVentasTotal");
    const lblSubtotal = document.getElementById("carritoVentasResumenSubtotal");
    const lblDescuento = document.getElementById("carritoVentasResumenDescuento");
    const btnConfirmar = document.getElementById("btnConfirmarVentaCarrito");

    let subtotalGeneral = 0;
    let descuentoGeneral = 0;
    let totalGeneral = 0;
    let totalUnidadesFisicas = 0;

    if (!tbody) return;

    if (carritoVentas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">🛒 El carrito está vacío. Agregá productos arriba.</td></tr>`;
        if (lblCount) lblCount.textContent = "0 items";
        if (lblTotal) lblTotal.textContent = "$ 0,00";
        if (lblSubtotal) lblSubtotal.textContent = "$ 0,00";
        if (lblDescuento) lblDescuento.textContent = "$ 0,00";
        if (btnConfirmar) btnConfirmar.disabled = true;
        return;
    }

    tbody.replaceChildren();

    carritoVentas.forEach((item, index) => {
        subtotalGeneral += item.subtotal;
        descuentoGeneral += item.descuento_monto;
        totalGeneral += item.total;
        totalUnidadesFisicas += item.total_unidades;

        const fila = document.createElement("tr");
        fila.className = "carrito-item-row";

        // #
        fila.append(celda(String(index + 1), "text-muted small"));

        // Producto y Empaque
        const prodTd = document.createElement("td");
        const prodNombre = document.createElement("strong");
        prodNombre.textContent = item.producto_nombre;
        prodTd.appendChild(prodNombre);

        if (item.proveedor) {
            const smallProv = document.createElement("small");
            smallProv.className = "text-muted d-block";
            smallProv.textContent = `🏢 ${item.proveedor}`;
            prodTd.appendChild(smallProv);
        }

        const smallEmpaque = document.createElement("small");
        smallEmpaque.className = "badge text-bg-light border mt-1";
        if (item.tipo_venta !== "unidad" && item.unidades_por_bulto > 1) {
            smallEmpaque.textContent = `${item.cantidad_empaque} ${item.tipo_venta}(s) (${item.total_unidades} un.)`;
        } else {
            smallEmpaque.textContent = `${item.total_unidades} un.`;
        }
        prodTd.appendChild(smallEmpaque);
        fila.appendChild(prodTd);

        // Precio Unitario
        fila.append(celda(formatoMoneda.format(item.precio_unitario), "small"));

        // Descuento
        const descTd = document.createElement("td");
        if (item.descuento_porcentaje > 0) {
            descTd.innerHTML = `<span class="badge text-bg-danger">-${item.descuento_porcentaje}%</span><br><small class="text-muted">${formatoMoneda.format(item.descuento_monto)}</small>`;
        } else {
            descTd.innerHTML = `<span class="text-muted small">0%</span>`;
        }
        fila.appendChild(descTd);

        // Subtotal / Total de Fila
        fila.append(celda(formatoMoneda.format(item.total), "fw-bold text-success"));

        // Quitar Item
        const accionTd = document.createElement("td");
        accionTd.className = "text-end";
        const btnEliminar = document.createElement("button");
        btnEliminar.type = "button";
        btnEliminar.className = "btn btn-outline-danger btn-sm";
        btnEliminar.innerHTML = "🗑️";
        btnEliminar.title = "Quitar del carrito";
        btnEliminar.addEventListener("click", () => {
            carritoVentas.splice(index, 1);
            renderizarCarritoVentas();
        });
        accionTd.appendChild(btnEliminar);
        fila.appendChild(accionTd);

        tbody.appendChild(fila);
    });

    if (lblCount) lblCount.textContent = `${carritoVentas.length} item(s) (${totalUnidadesFisicas} un.)`;
    if (lblSubtotal) lblSubtotal.textContent = formatoMoneda.format(subtotalGeneral);
    if (lblDescuento) lblDescuento.textContent = descuentoGeneral > 0 ? `-${formatoMoneda.format(descuentoGeneral)}` : "$ 0,00";
    if (lblTotal) lblTotal.textContent = formatoMoneda.format(totalGeneral);
    if (btnConfirmar) btnConfirmar.disabled = false;
}

function obtenerCalculoItemActual() {
    const selectProducto = document.getElementById("ventaProducto");
    const selectTipoVenta = document.getElementById("ventaTipoVenta");
    const inputCantidad = document.getElementById("ventaCantidad");
    const selectDescuento = document.getElementById("ventaDescuentoPorcentaje");
    const inputDescuentoCustom = document.getElementById("ventaDescuentoCustom");

    if (!selectProducto || !inputCantidad) return null;

    const opt = selectProducto.selectedOptions[0];
    if (!opt || !opt.dataset.precio || !selectProducto.value) return null;

    const rol = document.body.dataset.rol || "";
    const limiteDescuento = (rol === "admin") ? 100 : (Number(document.body.dataset.limiteDescuento) || 15);

    const productoId = selectProducto.value;
    const productoNombre = opt.text.split("(")[0].trim();
    const stockDisponible = Number(opt.dataset.stock) || 0;
    const precioUnitario = Number(opt.dataset.precio) || 0;
    const unidadesPorEmpaque = Math.max(1, Number(opt.dataset.unidadesBulto) || 1);
    const proveedor = opt.dataset.proveedor || "";
    const tipoVenta = selectTipoVenta ? selectTipoVenta.value : "unidad";
    const cantidad = Math.max(1, Number(inputCantidad.value) || 1);

    let totalUnidades = cantidad;
    if (tipoVenta === "caja" || tipoVenta === "bulto") {
        totalUnidades = cantidad * unidadesPorEmpaque;
    }

    let descPorcentaje = 0;
    if (selectDescuento) {
        if (selectDescuento.value === "custom") {
            if (inputDescuentoCustom) {
                let customVal = Number(inputDescuentoCustom.value) || 0;
                if (customVal > limiteDescuento && rol === "vendedor") {
                    inputDescuentoCustom.value = limiteDescuento;
                    customVal = limiteDescuento;
                }
                descPorcentaje = Math.min(limiteDescuento, Math.max(0, customVal));
            }
        } else {
            let optVal = Number(selectDescuento.value) || 0;
            if (optVal > limiteDescuento && rol === "vendedor") {
                selectDescuento.value = "0";
                optVal = 0;
            }
            descPorcentaje = optVal;
        }
    }

    const subtotal = totalUnidades * precioUnitario;
    const montoDescuento = subtotal * (descPorcentaje / 100);
    const total = Math.max(0, subtotal - montoDescuento);

    return {
        producto_id: productoId,
        producto_nombre: productoNombre,
        proveedor: proveedor,
        stock_disponible: stockDisponible,
        precio_unitario: precioUnitario,
        tipo_venta: tipoVenta,
        cantidad_empaque: cantidad,
        unidades_por_bulto: unidadesPorEmpaque,
        total_unidades: totalUnidades,
        descuento_porcentaje: descPorcentaje,
        descuento_monto: montoDescuento,
        subtotal: subtotal,
        total: total
    };
}

function configurarCalculadoraVenta() {
    const selectProducto = document.getElementById("ventaProducto");
    const selectTipoVenta = document.getElementById("ventaTipoVenta");
    const inputCantidad = document.getElementById("ventaCantidad");
    const selectDescuento = document.getElementById("ventaDescuentoPorcentaje");
    const inputDescuentoCustom = document.getElementById("ventaDescuentoCustom");

    const lblUnidades = document.getElementById("ventaResumenUnidades");
    const lblSubtotal = document.getElementById("ventaResumenSubtotal");
    const lblDescuento = document.getElementById("ventaResumenDescuento");
    const lblTotal = document.getElementById("ventaResumenTotal");
    const infoEmpaque = document.getElementById("ventaInfoEmpaque");

    if (!selectProducto || !inputCantidad) return;

    const rol = document.body.dataset.rol || "";
    const limiteDescuento = (rol === "admin") ? 100 : (Number(document.body.dataset.limiteDescuento) || 15);

    if (selectDescuento && rol === "vendedor") {
        Array.from(selectDescuento.options).forEach((opt) => {
            if (opt.value !== "custom") {
                const valNum = Number(opt.value);
                if (valNum > limiteDescuento) {
                    opt.disabled = true;
                    opt.text = `${valNum}% (No autorizado - Máx: ${limiteDescuento}%)`;
                }
            }
        });
    }

    const recalcular = () => {
        if (selectDescuento && selectDescuento.value === "custom") {
            if (inputDescuentoCustom) {
                inputDescuentoCustom.classList.remove("d-none");
                inputDescuentoCustom.max = String(limiteDescuento);
            }
        } else {
            if (inputDescuentoCustom) inputDescuentoCustom.classList.add("d-none");
        }

        const calculo = obtenerCalculoItemActual();
        if (!calculo) {
            if (lblUnidades) lblUnidades.textContent = "0 un.";
            if (lblSubtotal) lblSubtotal.textContent = "$ 0,00";
            if (lblDescuento) lblDescuento.textContent = "$ 0,00";
            if (lblTotal) lblTotal.textContent = "$ 0,00";
            if (infoEmpaque) infoEmpaque.classList.add("d-none");
            return;
        }

        if (calculo.tipo_venta === "caja" || calculo.tipo_venta === "bulto") {
            if (infoEmpaque) {
                infoEmpaque.textContent = `📦 1 ${calculo.tipo_venta} = ${calculo.unidades_por_bulto} unidades individuales`;
                infoEmpaque.classList.remove("d-none");
            }
        } else {
            if (infoEmpaque) infoEmpaque.classList.add("d-none");
        }

        if (lblUnidades) lblUnidades.textContent = `${calculo.total_unidades} un.`;
        if (lblSubtotal) lblSubtotal.textContent = formatoMoneda.format(calculo.subtotal);
        if (lblDescuento) lblDescuento.textContent = calculo.descuento_porcentaje > 0 ? `-${calculo.descuento_porcentaje}% (${formatoMoneda.format(calculo.descuento_monto)})` : "$ 0,00";
        if (lblTotal) lblTotal.textContent = formatoMoneda.format(calculo.total);
    };

    selectProducto.addEventListener("change", recalcular);
    if (selectTipoVenta) selectTipoVenta.addEventListener("change", recalcular);
    inputCantidad.addEventListener("input", recalcular);
    if (selectDescuento) selectDescuento.addEventListener("change", recalcular);
    if (inputDescuentoCustom) inputDescuentoCustom.addEventListener("input", recalcular);
}

function agregarProductoAlCarrito() {
    const errorBox = document.getElementById("errorVenta");
    if (errorBox) errorBox.classList.add("d-none");

    const calculo = obtenerCalculoItemActual();
    if (!calculo) {
        if (errorBox) {
            errorBox.textContent = "Por favor, seleccioná un producto y una cantidad válida.";
            errorBox.classList.remove("d-none");
        }
        return;
    }

    // Validar stock considerando lo que ya está en el carrito para este producto
    const unidadesYaEnCarrito = carritoVentas
        .filter((item) => String(item.producto_id) === String(calculo.producto_id))
        .reduce((sum, item) => sum + item.total_unidades, 0);

    const totalUnidadesDemandadas = unidadesYaEnCarrito + calculo.total_unidades;
    if (totalUnidadesDemandadas > calculo.stock_disponible) {
        if (errorBox) {
            errorBox.textContent = `Stock insuficiente para "${calculo.producto_nombre}". Stock disponible: ${calculo.stock_disponible} un. (Ya hay ${unidadesYaEnCarrito} un. en el carrito).`;
            errorBox.classList.remove("d-none");
        }
        return;
    }

    carritoVentas.push(calculo);
    renderizarCarritoVentas();

    // Resetear selector de producto y cantidad para cargar el siguiente producto rápidamente
    const selectProducto = document.getElementById("ventaProducto");
    const inputCantidad = document.getElementById("ventaCantidad");
    const selectTipoVenta = document.getElementById("ventaTipoVenta");
    const selectDescuento = document.getElementById("ventaDescuentoPorcentaje");
    const inputDescuentoCustom = document.getElementById("ventaDescuentoCustom");

    if (selectProducto) selectProducto.value = "";
    if (inputCantidad) inputCantidad.value = 1;
    if (selectTipoVenta) selectTipoVenta.value = "unidad";
    if (selectDescuento) selectDescuento.value = "0";
    if (inputDescuentoCustom) {
        inputDescuentoCustom.value = "";
        inputDescuentoCustom.classList.add("d-none");
    }

    const infoEmpaque = document.getElementById("ventaInfoEmpaque");
    if (infoEmpaque) infoEmpaque.classList.add("d-none");

    const lblUnidades = document.getElementById("ventaResumenUnidades");
    const lblSubtotal = document.getElementById("ventaResumenSubtotal");
    const lblDescuento = document.getElementById("ventaResumenDescuento");
    const lblTotal = document.getElementById("ventaResumenTotal");
    if (lblUnidades) lblUnidades.textContent = "0 un.";
    if (lblSubtotal) lblSubtotal.textContent = "$ 0,00";
    if (lblDescuento) lblDescuento.textContent = "$ 0,00";
    if (lblTotal) lblTotal.textContent = "$ 0,00";
}

// Botón Agregar al Carrito
const btnAgregarAlCarrito = document.getElementById("btnAgregarAlCarrito");
if (btnAgregarAlCarrito) {
    btnAgregarAlCarrito.addEventListener("click", agregarProductoAlCarrito);
}

// Botón Limpiar Carrito
const btnLimpiarCarrito = document.getElementById("btnLimpiarCarrito");
if (btnLimpiarCarrito) {
    btnLimpiarCarrito.addEventListener("click", () => {
        if (carritoVentas.length > 0 && confirm("¿Estás seguro de vaciar el carrito?")) {
            limpiarCarritoVentas();
        }
    });
}

// -------------------------------------------------------------
// BÚSQUEDA Y ESCANEO QR DE CLIENTES
// -------------------------------------------------------------
function seleccionarClientePorCodigoQR(codigoQR) {
    const selectCliente = document.getElementById("ventaCliente");
    const feedback = document.getElementById("ventaClienteFeedback");
    if (!codigoQR) return;

    let idBuscado = "";
    let dniBuscado = "";

    // Reconocer formato CLIENTE:<id>:DNI:<dni> o CLIENTE:<id> o número directo
    const matchCompleto = String(codigoQR).match(/CLIENTE:([^:]+)(?::DNI:([^:]+))?/i);
    if (matchCompleto) {
        idBuscado = (matchCompleto[1] || "").trim();
        dniBuscado = (matchCompleto[2] || "").trim();
    } else {
        const limpia = String(codigoQR).trim();
        idBuscado = limpia;
        dniBuscado = limpia;
    }

    const clienteEncontrado = clientesCache.find((c) => {
        return (
            (idBuscado && String(c.id).toLowerCase() === idBuscado.toLowerCase()) ||
            (dniBuscado && String(c.dni) === dniBuscado) ||
            (String(c.dni) === String(codigoQR).trim()) ||
            (String(c.id) === String(codigoQR).trim())
        );
    });

    if (clienteEncontrado) {
        if (selectCliente) {
            selectCliente.value = clienteEncontrado.id;
        }
        if (feedback) {
            feedback.innerHTML = `<span class="badge text-bg-success p-2">✅ Cliente escaneado: <strong>${clienteEncontrado.nombre} ${clienteEncontrado.apellido}</strong> (DNI ${clienteEncontrado.dni})</span>`;
            feedback.classList.remove("d-none");
            setTimeout(() => {
                feedback.classList.add("d-none");
            }, 6000);
        } else {
            alert(`✅ Cliente detectado: ${clienteEncontrado.nombre} ${clienteEncontrado.apellido}`);
        }
    } else {
        alert(`No se encontró ningún cliente en el sistema para el código escaneado: "${codigoQR}". Podés seleccionarlo manualmente de la lista.`);
    }
}

const btnEscanearClienteQR = document.getElementById("btnEscanearClienteQR");
if (btnEscanearClienteQR) {
    btnEscanearClienteQR.addEventListener("click", () => {
        abrirModalScannerCamara((codigoQR) => {
            seleccionarClientePorCodigoQR(codigoQR);
        });
    });
}

// -------------------------------------------------------------
// VISOR DE CREDENCIAL QR DEL CLIENTE (MODAL)
// -------------------------------------------------------------
function abrirModalQrCliente(clienteId) {
    const cliente = clientesCache.find((c) => String(c.id) === String(clienteId));
    if (!cliente) {
        alert("No se encontraron los datos del cliente.");
        return;
    }

    const modalEl = document.getElementById("modalQrCliente");
    if (!modalEl) return;

    const lblNombre = document.getElementById("modalQrClienteNombre");
    const lblDni = document.getElementById("modalQrClienteDni");
    const lblId = document.getElementById("modalQrClienteId");
    const contenedorQr = document.getElementById("modalQrClienteContenedor");

    if (lblNombre) lblNombre.textContent = `${cliente.nombre} ${cliente.apellido}`;
    if (lblDni) lblDni.textContent = cliente.dni || "—";
    if (lblId) lblId.textContent = `#${cliente.id}`;

    if (contenedorQr) {
        contenedorQr.replaceChildren();
        if (typeof QRCode !== "undefined") {
            new QRCode(contenedorQr, {
                text: `CLIENTE:${cliente.id}:DNI:${cliente.dni || ""}`,
                width: 190,
                height: 190,
                colorDark: "#0d6efd",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

const btnImprimirQrCliente = document.getElementById("btnImprimirQrCliente");
if (btnImprimirQrCliente) {
    btnImprimirQrCliente.addEventListener("click", () => {
        window.print();
    });
}

// Delegación global para botones de ver credencial QR
document.addEventListener("click", (e) => {
    const btn = e.target.closest('[data-accion="ver-qr-cliente"]');
    if (!btn) return;
    const clienteId = btn.getAttribute("data-cliente-id");
    if (clienteId) {
        abrirModalQrCliente(clienteId);
    }
});

// -------------------------------------------------------------
// FACTURA EN FORMULARIOS DE PRODUCTO (CHECKBOXES "SIN FACTURA")
// -------------------------------------------------------------
const chkProdSinFactura = document.getElementById("productoSinFactura");
if (chkProdSinFactura) {
    chkProdSinFactura.addEventListener("change", (e) => {
        const inputFactura = document.getElementById("productoNumeroFactura");
        if (inputFactura) {
            inputFactura.disabled = e.target.checked;
            if (e.target.checked) inputFactura.value = "";
        }
    });
}

const chkEditProdSinFactura = document.getElementById("editarProductoSinFactura");
if (chkEditProdSinFactura) {
    chkEditProdSinFactura.addEventListener("change", (e) => {
        const inputFactura = document.getElementById("editarProductoNumeroFactura");
        if (inputFactura) {
            inputFactura.disabled = e.target.checked;
            if (e.target.checked) inputFactura.value = "";
        }
    });
}

// Envío de Formulario Producto (Alta)
const formProducto = document.getElementById("formProducto");
if (formProducto) {
    configurarSelectorPresentacion("productoPresentacion", "contenedorUnidadesBulto", "productoUnidadesBulto");
    formProducto.addEventListener("submit", async (e) => {
        e.preventDefault();
        const errorBox = document.getElementById("errorProducto");
        errorBox.classList.add("d-none");
        try {
            await solicitar("guardar_producto.php", { method: "POST", body: new FormData(formProducto) });
            bootstrap.Modal.getInstance(document.getElementById("modalProducto")).hide();
            formProducto.reset();
            const inputFact = document.getElementById("productoNumeroFactura");
            if (inputFact) inputFact.disabled = false;
            configurarSelectorPresentacion("productoPresentacion", "contenedorUnidadesBulto", "productoUnidadesBulto");
            await Promise.all([cargarProductos(), cargarIngresos(), cargarProveedores()]);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove("d-none");
        }
    });
}

// Envío de Formulario Producto (Edición)
const formEditarProducto = document.getElementById("formEditarProducto");
if (formEditarProducto) {
    formEditarProducto.addEventListener("submit", async (e) => {
        e.preventDefault();
        const errorBox = document.getElementById("errorEditarProducto");
        errorBox.classList.add("d-none");
        try {
            await solicitar("modificar_producto.php", { method: "POST", body: new FormData(formEditarProducto) });
            bootstrap.Modal.getInstance(document.getElementById("modalEditarProducto")).hide();
            formEditarProducto.reset();
            const inputFact = document.getElementById("editarProductoNumeroFactura");
            if (inputFact) inputFact.disabled = false;
            await Promise.all([cargarProductos(), cargarIngresos(), cargarProveedores()]);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove("d-none");
        }
    });
}

// Envío de Formulario Venta (Confirmación con Carrito Multiproducto)
const formVenta = document.getElementById("formVenta");
if (formVenta) {
    configurarCalculadoraVenta();

    // Evento al abrir modal de venta: inicializar carrito limpio
    const modalVentaEl = document.getElementById("modalVenta");
    if (modalVentaEl) {
        modalVentaEl.addEventListener("show.bs.modal", () => {
            if (carritoVentas.length === 0) {
                renderizarCarritoVentas();
            }
        });
    }

    formVenta.addEventListener("submit", async (e) => {
        e.preventDefault();
        const errorBox = document.getElementById("errorVenta");
        errorBox.classList.add("d-none");

        const selectCliente = document.getElementById("ventaCliente");
        const clienteId = selectCliente ? selectCliente.value : "";
        if (!clienteId) {
            errorBox.textContent = "Debés seleccionar o escanear un cliente antes de confirmar la venta.";
            errorBox.classList.remove("d-none");
            return;
        }

        // Si el carrito está vacío pero hay un producto seleccionado con cantidad, agregarlo automáticamente
        if (carritoVentas.length === 0) {
            const calculo = obtenerCalculoItemActual();
            if (calculo) {
                agregarProductoAlCarrito();
            }
        }

        if (carritoVentas.length === 0) {
            errorBox.textContent = "El carrito está vacío. Agregá al menos un producto a la venta.";
            errorBox.classList.remove("d-none");
            return;
        }

        const btnConfirmar = document.getElementById("btnConfirmarVentaCarrito");
        const textoOriginal = btnConfirmar ? btnConfirmar.innerHTML : "Confirmar Venta";
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Procesando venta (${carritoVentas.length} items)...`;
        }

        try {
            const payload = {
                cliente_id: clienteId,
                items: carritoVentas
            };

            const respuesta = await solicitar("guardar_venta.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            bootstrap.Modal.getInstance(document.getElementById("modalVenta")).hide();
            formVenta.reset();
            limpiarCarritoVentas();

            await Promise.all([cargarProductos(), cargarVentas()]);

            const ticketMsg = respuesta.ticket_id ? ` (Ticket: ${respuesta.ticket_id})` : "";
            alert(`🎉 ¡Venta registrada con éxito! ${respuesta.total_items || 1} producto(s) procesados.${ticketMsg}`);

        } catch (error) {
            errorBox.textContent = error.message || "Error al registrar la venta.";
            errorBox.classList.remove("d-none");
        } finally {
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = textoOriginal;
            }
        }
    });
}

// Formularios de Modificación y Cancelación de Venta
async function enviarCambioVenta(formulario, modalId, errorId) {
    const errorBox = document.getElementById(errorId);
    errorBox.classList.add("d-none");
    try {
        await solicitar("modificar_venta.php", { method: "POST", body: new FormData(formulario) });
        bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
        formulario.reset();
        await Promise.all([cargarProductos(), cargarVentas()]);
    } catch (error) {
        errorBox.textContent = error.message;
        errorBox.classList.remove("d-none");
    }
}

const formModificarVenta = document.getElementById("formModificarVenta");
if (formModificarVenta) {
    formModificarVenta.addEventListener("submit", (e) => {
        e.preventDefault();
        enviarCambioVenta(formModificarVenta, "modalModificarVenta", "errorModificarVenta");
    });
}

const formCancelarVenta = document.getElementById("formCancelarVenta");
if (formCancelarVenta) {
    formCancelarVenta.addEventListener("submit", (e) => {
        e.preventDefault();
        enviarCambioVenta(formCancelarVenta, "modalCancelarVenta", "errorCancelarVenta");
    });
}

// Sincronización de Filtros de Semáforo
const inputFiltroProductoBusqueda = document.getElementById("filtroProductoBusqueda");
const selectFiltroProductoSemaforo = document.getElementById("filtroProductoSemaforo");
const selectFiltroProductoPresentacion = document.getElementById("filtroProductoPresentacion");
const selectFiltroProductoProveedor = document.getElementById("filtroProductoProveedor");
const btnLimpiarFiltrosProductos = document.getElementById("limpiarFiltrosProductos");

function sincronizarBotonesSemaforo(valorSeleccionado) {
    document.querySelectorAll("[data-boton-semaforo]").forEach((btn) => {
        const val = btn.getAttribute("data-boton-semaforo");
        if (val === valorSeleccionado) {
            btn.classList.add("active");
        } else {
            btn.classList.remove("active");
        }
    });
}

if (inputFiltroProductoBusqueda) inputFiltroProductoBusqueda.addEventListener("input", filtrarYRenderizarProductos);
if (selectFiltroProductoSemaforo) {
    selectFiltroProductoSemaforo.addEventListener("change", (e) => {
        sincronizarBotonesSemaforo(e.target.value);
        filtrarYRenderizarProductos();
    });
}
if (selectFiltroProductoPresentacion) selectFiltroProductoPresentacion.addEventListener("change", filtrarYRenderizarProductos);
if (selectFiltroProductoProveedor) selectFiltroProductoProveedor.addEventListener("change", filtrarYRenderizarProductos);

document.querySelectorAll("[data-boton-semaforo]").forEach((boton) => {
    boton.addEventListener("click", () => {
        const valor = boton.getAttribute("data-boton-semaforo");
        if (selectFiltroProductoSemaforo) {
            selectFiltroProductoSemaforo.value = valor;
        }
        sincronizarBotonesSemaforo(valor);
        filtrarYRenderizarProductos();
    });
});

if (btnLimpiarFiltrosProductos) {
    btnLimpiarFiltrosProductos.addEventListener("click", () => {
        if (inputFiltroProductoBusqueda) inputFiltroProductoBusqueda.value = "";
        if (selectFiltroProductoSemaforo) selectFiltroProductoSemaforo.value = "";
        if (selectFiltroProductoPresentacion) selectFiltroProductoPresentacion.value = "";
        if (selectFiltroProductoProveedor) selectFiltroProductoProveedor.value = "";
        sincronizarBotonesSemaforo("");
        filtrarYRenderizarProductos();
    });
}

// Filtros de Ventas
const formFiltrosVentas = document.getElementById("formFiltrosVentas");
if (formFiltrosVentas) {
    formFiltrosVentas.addEventListener("submit", (e) => {
        e.preventDefault();
        const datos = Object.fromEntries(new FormData(formFiltrosVentas));
        cargarVentas(datos);
    });
    const btnLimpiarVentas = document.getElementById("limpiarFiltros");
    if (btnLimpiarVentas) {
        btnLimpiarVentas.addEventListener("click", () => {
            formFiltrosVentas.reset();
            cargarVentas();
        });
    }
}

// Filtros de Ingresos
const formFiltrosIngresos = document.getElementById("formFiltrosIngresos");
if (formFiltrosIngresos) {
    formFiltrosIngresos.addEventListener("submit", (e) => {
        e.preventDefault();
        const datos = Object.fromEntries(new FormData(formFiltrosIngresos));
        cargarIngresos(datos);
    });
    const btnLimpiarIng = document.getElementById("limpiarFiltrosIngresos");
    if (btnLimpiarIng) {
        btnLimpiarIng.addEventListener("click", () => {
            formFiltrosIngresos.reset();
            cargarIngresos();
        });
    }
}

// Tarjetas de Semáforo en Resumen
document.querySelectorAll("[data-filtro-semaforo]").forEach((elemento) => {
    elemento.style.cursor = "pointer";
    elemento.addEventListener("click", () => {
        const valorSemaforo = elemento.getAttribute("data-filtro-semaforo");
        const tabProductosBtn = document.getElementById("tab-productos-btn") || document.getElementById("tab-vendedor-productos-btn");
        if (tabProductosBtn) {
            bootstrap.Tab.getOrCreateInstance(tabProductosBtn).show();
        }
        if (selectFiltroProductoSemaforo) {
            selectFiltroProductoSemaforo.value = valorSemaforo;
            sincronizarBotonesSemaforo(valorSemaforo);
            filtrarYRenderizarProductos();
        }
    });
});

// Manejador global robusto para modales en dispositivos móviles y escritorio
document.addEventListener("click", (e) => {
    const trigger = e.target.closest('[data-bs-toggle="modal"]');
    if (!trigger) return;

    const targetSelector = trigger.getAttribute("data-bs-target") || trigger.getAttribute("href");
    if (!targetSelector || !targetSelector.startsWith("#")) return;

    const modalEl = document.querySelector(targetSelector);
    if (!modalEl) return;

    // Cerrar menú navbar si está abierto en mobile
    const navCollapse = document.querySelector(".navbar-collapse.show");
    if (navCollapse && typeof bootstrap !== "undefined" && bootstrap.Collapse) {
        const bsCollapse = bootstrap.Collapse.getInstance(navCollapse) || new bootstrap.Collapse(navCollapse, { toggle: false });
        bsCollapse.hide();
    }

    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    }
});

// Auto-scroll fluido al enfocar inputs en móviles para evitar bloqueo con el teclado virtual
document.addEventListener("focusin", (e) => {
    const el = e.target;
    if (el && (el.tagName === "INPUT" || el.tagName === "SELECT" || el.tagName === "TEXTAREA")) {
        setTimeout(() => {
            el.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
        }, 280);
    }
});

// Soporte para redimensionamiento en tiempo real con visualViewport al abrir/cerrar teclado
if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", () => {
        const active = document.activeElement;
        if (active && (active.tagName === "INPUT" || active.tagName === "SELECT" || active.tagName === "TEXTAREA")) {
            setTimeout(() => {
                active.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
            }, 120);
        }
    });
}

// Inicialización general al cargar el DOM
document.addEventListener("DOMContentLoaded", async () => {
    document.querySelectorAll('[data-bs-target="#pestana-barcodes"]').forEach((btn) => {
        btn.addEventListener("shown.bs.tab", () => {
            actualizarPreviewBarcode();
        });
    });

    await Promise.all([
        cargarProductos(),
        cargarProveedores(),
        cargarVentas(),
        cargarIngresos(),
        cargarClientes(),
        cargarVendedores(),
        cargarSolicitudesVendedor()
    ]);
    actualizarPreviewBarcode();
});
