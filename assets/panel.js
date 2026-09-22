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

// Nueva política de semáforo FIFO:
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

function crearBadgePresentacion(presentacion, unidadesPorBulto) {
    const badge = document.createElement("span");
    const pres = (presentacion || "unidad").toLowerCase();
    badge.className = `badge-presentacion ${pres}`;
    
    if (pres === "caja") {
        badge.textContent = `📦 Caja (${unidadesPorBulto || 1} un.)`;
    } else if (pres === "bulto") {
        badge.textContent = `🏷️ Bulto (${unidadesPorBulto || 1} un.)`;
    } else {
        badge.textContent = `🔹 Unidad`;
    }
    return badge;
}

function renderizarFilasProductos(listaProductos) {
    const tbody = document.getElementById("productosBody");
    if (!tbody) return;
    const columnas = esAdmin ? 7 : 6;
    tbody.replaceChildren();

    if (listaProductos.length === 0) {
        mensajeEnTabla(tbody, columnas, "No se encontraron productos para los filtros seleccionados.");
        return;
    }

    listaProductos.forEach((producto) => {
        const fila = document.createElement("tr");
        
        fila.append(celda(producto.nombre, "fw-semibold"));

        const presTd = document.createElement("td");
        presTd.appendChild(crearBadgePresentacion(producto.presentacion, producto.unidades_por_bulto));
        fila.appendChild(presTd);

        fila.append(celda(producto.proveedor || "—", "text-muted"));

        const vencTd = document.createElement("td");
        vencTd.appendChild(crearBadgeVencimiento(producto.fecha_vencimiento));
        fila.appendChild(vencTd);

        fila.append(celda(formatoMoneda.format(Number(producto.precio))));

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

        if (esAdmin) {
            const accion = document.createElement("td");
            accion.className = "text-end";
            const grupo = document.createElement("div");
            grupo.className = "d-inline-flex gap-1";

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
            accion.appendChild(grupo);
            fila.appendChild(accion);
        }
        tbody.appendChild(fila);
    });
}

function filtrarYRenderizarProductos() {
    const inputBusqueda = document.getElementById("filtroProductoBusqueda");
    const selectSemaforo = document.getElementById("filtroProductoSemaforo");
    const selectPresentacion = document.getElementById("filtroProductoPresentacion");

    const query = inputBusqueda ? inputBusqueda.value.toLowerCase().trim() : "";
    const semaforoFiltro = selectSemaforo ? selectSemaforo.value : "";
    const presentacionFiltro = selectPresentacion ? selectPresentacion.value : "";

    const filtrados = productosCache.filter((producto) => {
        if (query !== "") {
            const nombre = (producto.nombre || "").toLowerCase();
            const proveedor = (producto.proveedor || "").toLowerCase();
            if (!nombre.includes(query) && !proveedor.includes(query)) return false;
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
    const columnas = esAdmin ? 7 : 6;
    mensajeEnTabla(tbody, columnas, "Cargando productos...");
    try {
        productosCache = await solicitar("obtener_productos.php");
        
        const resProd = document.getElementById("resumenProductos");
        const resStock = document.getElementById("resumenStock");
        if (resProd) resProd.textContent = productosCache.length;
        if (resStock) resStock.textContent = productosCache.reduce((total, p) => total + Number(p.stock), 0);
        
        filtrarYRenderizarProductos();
        llenarSelectoresProductos();
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
            filtroVentas.appendChild(new Option(producto.nombre, producto.id));
        });
        filtroVentas.value = filtroActual;
    }

    if (filtroIngresos) {
        const filtroActual = filtroIngresos.value;
        filtroIngresos.replaceChildren(new Option("Todos los productos", ""));
        productosCache.forEach((producto) => {
            filtroIngresos.appendChild(new Option(producto.nombre, producto.id));
        });
        filtroIngresos.value = filtroActual;
    }

    if (venta) {
        const ventaActual = venta.value;
        venta.replaceChildren();
        let hayDisponibles = false;
        productosCache.forEach((producto) => {
            if (Number(producto.stock) > 0) {
                hayDisponibles = true;
                const opt = new Option(`${producto.nombre} · stock: ${producto.stock} un. · ${formatoMoneda.format(Number(producto.precio))}`, producto.id);
                venta.appendChild(opt);
            }
        });
        if (!hayDisponibles) {
            venta.appendChild(new Option("No hay productos con stock disponible", ""));
        } else if (ventaActual) {
            venta.value = ventaActual;
        }
        actualizarOpcionesVentaSegunProducto();
    }
}

function actualizarOpcionesVentaSegunProducto() {
    const selectProd = document.getElementById("ventaProducto");
    const selectTipoVenta = document.getElementById("ventaTipoVenta");
    const infoEmpaque = document.getElementById("ventaInfoEmpaque");
    if (!selectProd || !selectTipoVenta) return;

    const prodId = Number(selectProd.value);
    const prod = productosCache.find((p) => Number(p.id) === prodId);

    selectTipoVenta.replaceChildren();
    selectTipoVenta.appendChild(new Option("Unidades sueltas", "unidad"));

    if (prod && prod.presentacion && prod.presentacion !== "unidad" && Number(prod.unidades_por_bulto) > 1) {
        const presNombre = prod.presentacion === "caja" ? "Caja" : "Bulto";
        selectTipoVenta.appendChild(new Option(`${presNombre} (${prod.unidades_por_bulto} un. c/u)`, prod.presentacion));
        if (infoEmpaque) {
            infoEmpaque.textContent = `Presentación de catálogo: ${presNombre} de ${prod.unidades_por_bulto} unidades.`;
            infoEmpaque.classList.remove("d-none");
        }
    } else {
        if (infoEmpaque) infoEmpaque.classList.add("d-none");
    }

    recalcularTotalesModalVenta();
}

function recalcularTotalesModalVenta() {
    const selectProd = document.getElementById("ventaProducto");
    const selectTipoVenta = document.getElementById("ventaTipoVenta");
    const inputCantidad = document.getElementById("ventaCantidad");
    const selectDescuento = document.getElementById("ventaDescuentoPorcentaje");
    const inputDescuentoCustom = document.getElementById("ventaDescuentoCustom");
    
    const resumenSubtotal = document.getElementById("ventaResumenSubtotal");
    const resumenDescuento = document.getElementById("ventaResumenDescuento");
    const resumenTotal = document.getElementById("ventaResumenTotal");
    const resumenUnidades = document.getElementById("ventaResumenUnidades");

    if (!selectProd || !inputCantidad) return;

    const prodId = Number(selectProd.value);
    const prod = productosCache.find((p) => Number(p.id) === prodId);
    const cantidad = Math.max(1, Number(inputCantidad.value) || 1);
    const tipoVenta = selectTipoVenta ? selectTipoVenta.value : "unidad";
    
    let descuentoPct = 0;
    if (selectDescuento) {
        if (selectDescuento.value === "custom") {
            if (inputDescuentoCustom) {
                inputDescuentoCustom.classList.remove("d-none");
                descuentoPct = Math.min(100, Math.max(0, Number(inputDescuentoCustom.value) || 0));
            }
        } else {
            if (inputDescuentoCustom) inputDescuentoCustom.classList.add("d-none");
            descuentoPct = Number(selectDescuento.value) || 0;
        }
    }

    if (!prod) return;

    const precioUnitario = Number(prod.precio);
    const unidadesPorBulto = (tipoVenta !== "unidad" && Number(prod.unidades_por_bulto) > 1) ? Number(prod.unidades_por_bulto) : 1;
    const totalUnidades = cantidad * unidadesPorBulto;
    const subtotal = totalUnidades * precioUnitario;
    const descuentoMonto = subtotal * (descuentoPct / 100);
    const totalFinal = Math.max(0, subtotal - descuentoMonto);

    if (resumenUnidades) resumenUnidades.textContent = `${totalUnidades} un.`;
    if (resumenSubtotal) resumenSubtotal.textContent = formatoMoneda.format(subtotal);
    if (resumenDescuento) resumenDescuento.textContent = descuentoPct > 0 ? `-${formatoMoneda.format(descuentoMonto)} (${descuentoPct}%)` : "$ 0,00";
    if (resumenTotal) resumenTotal.textContent = formatoMoneda.format(totalFinal);
}

async function cargarClientes() {
    const select = document.getElementById("ventaCliente");
    const filtroCliente = document.getElementById("filtroCliente");
    try {
        const clientes = await solicitar("obtener_clientes.php");
        if (select) {
            select.replaceChildren(new Option("Seleccioná un cliente", ""));
            clientes.forEach((cliente) => {
                select.appendChild(new Option(`${cliente.apellido || ""} ${cliente.nombre} · DNI ${cliente.dni}`.trim(), cliente.id));
            });
        }
        if (filtroCliente) {
            const actual = filtroCliente.value;
            filtroCliente.replaceChildren(new Option("Todos los clientes", ""));
            clientes.forEach((cliente) => {
                filtroCliente.appendChild(new Option(`${cliente.apellido || ""} ${cliente.nombre} (DNI ${cliente.dni})`.trim(), cliente.id));
            });
            filtroCliente.value = actual;
        }
    } catch (error) {
        if (select) select.replaceChildren(new Option("No se pudieron cargar los clientes", ""));
    }
}

async function cargarVendedores() {
    const filtroVenta = document.getElementById("filtroVendedor");
    const filtroIngreso = document.getElementById("filtroIngresoUsuario");
    try {
        const vendedores = await solicitar("obtener_vendedores.php");
        if (filtroVenta) {
            const actual = filtroVenta.value;
            filtroVenta.replaceChildren(new Option("Todos los vendedores", ""));
            vendedores.forEach((v) => {
                const rolTexto = v.rol === "admin" ? "Admin" : "Vendedor";
                filtroVenta.appendChild(new Option(`${v.apellido || ""} ${v.nombre} (${rolTexto})`.trim(), v.id));
            });
            filtroVenta.value = actual;
        }
        if (filtroIngreso) {
            const actual = filtroIngreso.value;
            filtroIngreso.replaceChildren(new Option("Todos los usuarios", ""));
            vendedores.forEach((v) => {
                const rolTexto = v.rol === "admin" ? "Admin" : "Vendedor";
                filtroIngreso.appendChild(new Option(`${v.apellido || ""} ${v.nombre} (${rolTexto})`.trim(), v.id));
            });
            filtroIngreso.value = actual;
        }
    } catch (error) {
        console.error("Error al cargar vendedores:", error);
    }
}

function badgeEstado(estado) {
    const badge = document.createElement("span");
    const clases = { ACTIVA: "text-bg-success", MODIFICADA: "text-bg-warning", CANCELADA: "text-bg-danger" };
    badge.className = `badge ${clases[estado] || "text-bg-secondary"}`;
    badge.textContent = estado.charAt(0) + estado.slice(1).toLowerCase();
    return badge;
}

function botonesVenta(venta) {
    const contenedor = document.createElement("div");
    contenedor.className = "d-flex gap-2 flex-nowrap";
    if (venta.estado === "CANCELADA") {
        contenedor.append("—");
        return contenedor;
    }
    const modificar = document.createElement("button");
    modificar.type = "button";
    modificar.className = "btn btn-outline-primary btn-sm";
    modificar.textContent = "Cantidad";
    modificar.addEventListener("click", () => {
        document.getElementById("modificarVentaId").value = venta.id;
        document.getElementById("modificarCantidad").value = venta.cantidad;
        document.getElementById("errorModificarVenta").classList.add("d-none");
        bootstrap.Modal.getOrCreateInstance(document.getElementById("modalModificarVenta")).show();
    });
    const cancelar = document.createElement("button");
    cancelar.type = "button";
    cancelar.className = "btn btn-outline-danger btn-sm";
    cancelar.textContent = "Cancelar";
    cancelar.addEventListener("click", () => {
        document.getElementById("cancelarVentaId").value = venta.id;
        document.getElementById("errorCancelarVenta").classList.add("d-none");
        bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCancelarVenta")).show();
    });
    contenedor.append(modificar, cancelar);
    return contenedor;
}

function filtrosVentasActuales() {
    const form = document.getElementById("formFiltrosVentas");
    return form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
}

async function cargarVentas(parametros = filtrosVentasActuales()) {
    const tbody = document.getElementById("ventasBody");
    if (!tbody) return;
    const errorFiltros = document.getElementById("errorFiltros");
    if (errorFiltros) errorFiltros.textContent = "";
    mensajeEnTabla(tbody, 12, "Cargando ventas...");
    try {
        const ventas = await solicitar(`obtener_ventas.php?${parametros.toString()}`);
        tbody.replaceChildren();
        if (ventas.length === 0) {
            mensajeEnTabla(tbody, 12, "No encontramos ventas para los filtros seleccionados.");
            const resVentas = document.getElementById("resumenVentas");
            if (resVentas) resVentas.textContent = "0";
            return;
        }
        ventas.forEach((venta) => {
            const fila = document.createElement("tr");
            fila.append(celda(`#${venta.id}`, "fw-semibold"));
            fila.append(celda(fechaLegible(venta.fecha)));
            fila.append(celda(venta.cliente || "Sin cliente"));
            fila.append(celda(venta.producto_nombre));

            const cantTd = document.createElement("td");
            const cant = Number(venta.cantidad);
            const cantEmp = Number(venta.cantidad_empaque) || 1;
            const tipoVenta = venta.tipo_venta || "unidad";
            if (tipoVenta !== "unidad" && cantEmp > 0) {
                cantTd.innerHTML = `<strong>${cantEmp} ${tipoVenta}${cantEmp > 1 ? "s" : ""}</strong> <small class="text-muted">(${cant} un.)</small>`;
            } else {
                cantTd.textContent = `${cant} un.`;
            }
            fila.appendChild(cantTd);

            fila.append(celda(formatoMoneda.format(Number(venta.precio_unitario))));

            const descTd = document.createElement("td");
            const descPct = Number(venta.descuento_porcentaje) || 0;
            const descMonto = Number(venta.descuento_monto) || 0;
            if (descPct > 0) {
                descTd.innerHTML = `<span class="badge text-bg-warning">${descPct}%</span> <small class="text-danger">(-${formatoMoneda.format(descMonto)})</small>`;
            } else {
                descTd.textContent = "—";
            }
            fila.appendChild(descTd);

            fila.append(celda(formatoMoneda.format(Number(venta.total)), "fw-semibold text-primary"));
            fila.append(celda(venta.vendedor || "—"));
            
            const estadoTd = document.createElement("td");
            estadoTd.appendChild(badgeEstado(venta.estado));
            fila.appendChild(estadoTd);

            fila.append(celda(fechaLegible(venta.fecha_modificacion)));
            
            const acciones = document.createElement("td");
            acciones.appendChild(botonesVenta(venta));
            fila.appendChild(acciones);
            
            tbody.appendChild(fila);
        });
        const resVentas = document.getElementById("resumenVentas");
        if (resVentas) resVentas.textContent = ventas.length;
    } catch (error) {
        mensajeEnTabla(tbody, 12, error.message, true);
        if (errorFiltros) errorFiltros.textContent = error.message;
    }
}

function filtrosIngresosActuales() {
    const form = document.getElementById("formFiltrosIngresos");
    return form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
}

async function cargarIngresos(parametros = filtrosIngresosActuales()) {
    const tbody = document.getElementById("ingresosBody");
    if (!tbody) return;
    const errorFiltros = document.getElementById("errorFiltrosIngresos");
    if (errorFiltros) errorFiltros.textContent = "";
    mensajeEnTabla(tbody, 8, "Cargando historial de ingresos...");
    try {
        const ingresos = await solicitar(`obtener_ingresos.php?${parametros.toString()}`);
        tbody.replaceChildren();
        if (ingresos.length === 0) {
            mensajeEnTabla(tbody, 8, "No se encontraron ingresos registrados.");
            const resIngresos = document.getElementById("resumenIngresos");
            if (resIngresos) resIngresos.textContent = "0";
            return;
        }
        ingresos.forEach((ingreso) => {
            const fila = document.createElement("tr");
            fila.append(celda(`#${ingreso.id}`, "fw-semibold"));
            fila.append(celda(fechaLegible(ingreso.fecha)));
            fila.append(celda(ingreso.producto_nombre, "fw-semibold"));

            const detalleTd = document.createElement("td");
            const cant = Number(ingreso.cantidad);
            const totalUnidades = Number(ingreso.total_unidades);
            const pres = ingreso.presentacion || "unidad";
            if (pres !== "unidad" && Number(ingreso.unidades_por_bulto) > 1) {
                detalleTd.textContent = `${cant} ${pres}${cant > 1 ? "s" : ""} (${totalUnidades} un. total)`;
            } else {
                detalleTd.textContent = `${totalUnidades} unidades`;
            }
            fila.appendChild(detalleTd);

            fila.append(celda(ingreso.proveedor || "—"));

            const vencTd = document.createElement("td");
            vencTd.appendChild(crearBadgeVencimiento(ingreso.fecha_vencimiento));
            fila.appendChild(vencTd);

            fila.append(celda(ingreso.usuario || "—"));
            fila.append(celda(ingreso.motivo || "—", "text-muted small"));

            tbody.appendChild(fila);
        });

        const resIngresos = document.getElementById("resumenIngresos");
        if (resIngresos) resIngresos.textContent = ingresos.length;
    } catch (error) {
        mensajeEnTabla(tbody, 8, error.message, true);
        if (errorFiltros) errorFiltros.textContent = error.message;
    }
}

// Carga de Directorio de Proveedores
async function cargarProveedores() {
    const tbody = document.getElementById("proveedoresBody");
    if (!tbody) return;
    mensajeEnTabla(tbody, 5, "Cargando directorio de proveedores...");
    try {
        proveedoresCache = await solicitar("obtener_proveedores.php");
        tbody.replaceChildren();
        if (proveedoresCache.length === 0) {
            mensajeEnTabla(tbody, 5, "No se registraron proveedores todavía.");
            return;
        }
        proveedoresCache.forEach((prov) => {
            const fila = document.createElement("tr");
            fila.append(celda(prov.proveedor, "fw-bold text-primary"));
            fila.append(celda(String(prov.total_productos) + " productos"));
            fila.append(celda(String(prov.total_ingresos) + " entradas"));
            fila.append(celda(String(prov.total_unidades) + " unidades"));
            fila.append(celda(prov.ultimo_ingreso ? fechaLegible(prov.ultimo_ingreso) : "—"));
            tbody.appendChild(fila);
        });
        const resProv = document.getElementById("resumenProveedores");
        if (resProv) resProv.textContent = proveedoresCache.length;
    } catch (error) {
        mensajeEnTabla(tbody, 5, error.message, true);
    }
}

// Carga de Solicitudes de Atención de Clientes
async function cargarSolicitudesVendedor() {
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
            badge.textContent = esPendiente ? "Pendiente de atención" : `Atendida por ${sol.atendido_por_nombre || "Equipo"}`;
            estadoTd.appendChild(badge);
            fila.appendChild(estadoTd);

            const accionTd = document.createElement("td");
            accionTd.className = "text-end";
            if (esPendiente) {
                const btnAtender = document.createElement("button");
                btnAtender.type = "button";
                btnAtender.className = "btn btn-success btn-sm";
                btnAtender.textContent = "✓ Marcar atendida";
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

// Configuración de eventos de filtros de productos (Búsqueda + Semáforo FIFO + Presentación)
const inputFiltroProductoBusqueda = document.getElementById("filtroProductoBusqueda");
const selectFiltroProductoSemaforo = document.getElementById("filtroProductoSemaforo");
const selectFiltroProductoPresentacion = document.getElementById("filtroProductoPresentacion");
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

// Botones rápidos de semáforo
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
        sincronizarBotonesSemaforo("");
        filtrarYRenderizarProductos();
    });
}

// Accesos directos desde las tarjetas de leyenda del semáforo FIFO
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

// Configuración de eventos de formularios y filtros de ventas
const formFiltrosVentas = document.getElementById("formFiltrosVentas");
if (formFiltrosVentas) {
    formFiltrosVentas.addEventListener("submit", (evento) => {
        evento.preventDefault();
        cargarVentas();
    });
}

const limpiarFiltros = document.getElementById("limpiarFiltros");
if (limpiarFiltros) {
    limpiarFiltros.addEventListener("click", () => {
        document.getElementById("formFiltrosVentas").reset();
        cargarVentas(new URLSearchParams());
    });
}

const formFiltrosIngresos = document.getElementById("formFiltrosIngresos");
if (formFiltrosIngresos) {
    formFiltrosIngresos.addEventListener("submit", (evento) => {
        evento.preventDefault();
        cargarIngresos();
    });
}

const limpiarFiltrosIngresos = document.getElementById("limpiarFiltrosIngresos");
if (limpiarFiltrosIngresos) {
    limpiarFiltrosIngresos.addEventListener("click", () => {
        document.getElementById("formFiltrosIngresos").reset();
        cargarIngresos(new URLSearchParams());
    });
}

const formModificarVenta = document.getElementById("formModificarVenta");
if (formModificarVenta) {
    formModificarVenta.addEventListener("submit", (evento) => {
        evento.preventDefault();
        enviarCambioVenta(evento.currentTarget, "modalModificarVenta", "errorModificarVenta");
    });
}

const formCancelarVenta = document.getElementById("formCancelarVenta");
if (formCancelarVenta) {
    formCancelarVenta.addEventListener("submit", (evento) => {
        evento.preventDefault();
        enviarCambioVenta(evento.currentTarget, "modalCancelarVenta", "errorCancelarVenta");
    });
}

const formVenta = document.getElementById("formVenta");
if (formVenta) {
    const ventaProducto = document.getElementById("ventaProducto");
    const ventaTipoVenta = document.getElementById("ventaTipoVenta");
    const ventaCantidad = document.getElementById("ventaCantidad");
    const ventaDescuento = document.getElementById("ventaDescuentoPorcentaje");
    const ventaDescuentoCustom = document.getElementById("ventaDescuentoCustom");

    if (ventaProducto) ventaProducto.addEventListener("change", actualizarOpcionesVentaSegunProducto);
    if (ventaTipoVenta) ventaTipoVenta.addEventListener("change", recalcularTotalesModalVenta);
    if (ventaCantidad) ventaCantidad.addEventListener("input", recalcularTotalesModalVenta);
    if (ventaDescuento) ventaDescuento.addEventListener("change", recalcularTotalesModalVenta);
    if (ventaDescuentoCustom) ventaDescuentoCustom.addEventListener("input", recalcularTotalesModalVenta);

    formVenta.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        const errorBox = document.getElementById("errorVenta");
        errorBox.classList.add("d-none");
        
        const datos = new FormData(evento.currentTarget);
        if (ventaDescuento && ventaDescuento.value === "custom" && ventaDescuentoCustom) {
            datos.set("descuento_porcentaje", ventaDescuentoCustom.value || "0");
        }

        try {
            await solicitar("guardar_venta.php", { method: "POST", body: datos });
            bootstrap.Modal.getInstance(document.getElementById("modalVenta")).hide();
            evento.currentTarget.reset();
            recalcularTotalesModalVenta();
            await Promise.all([cargarProductos(), cargarVentas()]);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove("d-none");
        }
    });
}

if (esAdmin) {
    configurarSelectorPresentacion("productoPresentacion", "contenedorUnidadesBulto", "productoUnidadesBulto");
    
    const formProducto = document.getElementById("formProducto");
    if (formProducto) {
        formProducto.addEventListener("submit", async (evento) => {
            evento.preventDefault();
            const errorBox = document.getElementById("errorProducto");
            errorBox.classList.add("d-none");
            try {
                await solicitar("guardar_producto.php", { method: "POST", body: new FormData(evento.currentTarget) });
                bootstrap.Modal.getInstance(document.getElementById("modalProducto")).hide();
                evento.currentTarget.reset();
                configurarSelectorPresentacion("productoPresentacion", "contenedorUnidadesBulto", "productoUnidadesBulto");
                await Promise.all([cargarProductos(), cargarIngresos(), cargarProveedores()]);
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove("d-none");
            }
        });
    }

    const formEditarProducto = document.getElementById("formEditarProducto");
    if (formEditarProducto) {
        formEditarProducto.addEventListener("submit", async (evento) => {
            evento.preventDefault();
            const errorBox = document.getElementById("errorEditarProducto");
            errorBox.classList.add("d-none");
            try {
                await solicitar("modificar_producto.php", { method: "POST", body: new FormData(evento.currentTarget) });
                bootstrap.Modal.getInstance(document.getElementById("modalEditarProducto")).hide();
                evento.currentTarget.reset();
                await Promise.all([cargarProductos(), cargarIngresos(), cargarProveedores()]);
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove("d-none");
            }
        });
    }
}

// Filtro rápido para buscador de proveedores
const buscadorProveedores = document.getElementById("buscadorProveedores");
if (buscadorProveedores) {
    buscadorProveedores.addEventListener("input", (e) => {
        const query = e.target.value.toLowerCase().trim();
        const filas = document.querySelectorAll("#proveedoresBody tr");
        filas.forEach((f) => {
            f.style.display = f.textContent.toLowerCase().includes(query) ? "" : "none";
        });
    });
}

// Navegación por hash de pestañas
function sincronizarPestanaDesdeHash() {
    const hash = window.location.hash;
    if (hash) {
        const disparadorPestana = document.querySelector(`.nav-tabs-app button[data-bs-target="${hash}"]`) ||
                                  document.querySelector(`.nav-tabs-app a[href="${hash}"]`);
        if (disparadorPestana) {
            const pestana = bootstrap.Tab.getOrCreateInstance(disparadorPestana);
            pestana.show();
        }
    }
}

document.querySelectorAll('.nav-tabs-app button[data-bs-toggle="tab"]').forEach((boton) => {
    boton.addEventListener("shown.bs.tab", (evento) => {
        const objetivo = evento.target.getAttribute("data-bs-target");
        if (objetivo && objetivo.startsWith("#")) {
            history.replaceState(null, "", objetivo);
        }
    });
});

window.addEventListener("hashchange", sincronizarPestanaDesdeHash);

// Inicialización de datos
Promise.all([cargarProductos(), cargarClientes(), cargarVendedores()])
    .then(() => Promise.all([cargarVentas(), cargarIngresos(), cargarProveedores(), cargarSolicitudesVendedor()]))
    .then(() => sincronizarPestanaDesdeHash());
