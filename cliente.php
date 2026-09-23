<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["cliente"]);

$nombreUsuario = trim(($_SESSION["usuario_nombre"] ?? "Cliente") . " " . ($_SESSION["usuario_apellido"] ?? ""));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catálogo de productos disponibles">
    <title>Mi cuenta | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --color-primary: #2563eb;
            --color-primary-dark: #1d4ed8;
            --color-ink: #172033;
            --color-muted: #68738a;
            --color-surface: #ffffff;
            --color-background: #f4f7fb;
            --color-border: #e5eaf2;
        }

        .producto-card {
            height: 100%;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 1rem;
            box-shadow: 0 .5rem 1.5rem rgba(23, 32, 51, .06);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .producto-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .85rem 2rem rgba(23, 32, 51, .10);
        }

        .producto-icono {
            display: grid;
            width: 2.8rem;
            height: 2.8rem;
            place-items: center;
            color: var(--color-primary);
            background: #eff6ff;
            border-radius: .85rem;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .producto-precio {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--color-ink);
        }

        .panel-estado {
            padding: 2.75rem 1.25rem;
            text-align: center;
            background: #fff;
            border: 1px dashed #cfd7e5;
            border-radius: 1rem;
        }
    </style>
</head>
<body data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, "UTF-8") ?>">
    <nav class="navbar navbar-expand-sm app-navbar sticky-top py-3" aria-label="Navegación principal">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="#inicio">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Control Stock</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuCliente" aria-controls="menuCliente" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuCliente">
                <div class="navbar-nav ms-auto align-items-sm-center gap-sm-2 pt-3 pt-sm-0">
                    <a class="nav-link nav-link-app active" href="#inicio">Inicio</a>
                    <a class="nav-link nav-link-app text-primary fw-bold" href="catalogo.php">Catálogo</a>
                    <a class="nav-link nav-link-app" href="#productos">Productos</a>
                    <a class="nav-link nav-link-app" href="#compras">Mis compras</a>
                    <button class="btn btn-outline-primary btn-sm px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalSolicitarVendedor">
                        Solicitar vendedor
                    </button>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm px-3 ms-sm-1">Cerrar sesión</a>
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

        <!-- Alerta Dinámica de Solicitud de Atención -->
        <div id="alertaSolicitudVendedor" class="d-none mb-4"></div>

        <section id="inicio" class="hero-panel p-4 p-md-5 mb-5" aria-labelledby="saludo-cliente">
            <div class="row align-items-center g-4">
                <div class="col-12 col-lg-7">
                    <p class="etiqueta text-white-50 mb-2">Portal de Clientes</p>
                    <h1 id="saludo-cliente" class="display-6 fw-bold mb-2">
                        Hola, <?= htmlspecialchars($nombreUsuario, ENT_QUOTES, "UTF-8") ?>
                    </h1>
                    <p class="lead mb-3 text-white-50">Consultá disponibilidad de productos y solicitá atención directa de nuestro equipo cuando lo necesites.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="catalogo.php" class="btn btn-light btn-sm fw-bold px-3 py-2 text-primary">
                            Ver Catálogo
                        </a>
                        <button class="btn btn-outline-light btn-sm fw-bold px-3 py-2" type="button" data-bs-toggle="modal" data-bs-target="#modalSolicitarVendedor">
                            Solicitar atención
                        </button>
                        <button class="btn btn-outline-light btn-sm fw-bold px-3 py-2" type="button" onclick="window.print()">
                            Imprimir credencial
                        </button>
                    </div>
                </div>
                <div class="col-12 col-lg-5 text-center text-lg-end">
                    <div class="tarjeta-credencial-qr d-inline-block text-start" style="max-width: 320px; width: 100%;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-uppercase tracking-wide text-white-50">Credencial Digital</span>
                            <span class="badge text-bg-primary">Cliente</span>
                        </div>
                        <h2 class="h6 fw-bold mb-0 text-white"><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, "UTF-8") ?></h2>
                        <p class="small text-white-50 mb-2">DNI: <?= htmlspecialchars((string)($_SESSION["usuario_dni"] ?? "—"), ENT_QUOTES, "UTF-8") ?></p>
                        
                        <div class="qr-box w-100 text-center">
                            <div id="clienteQrBox" class="d-flex justify-content-center"></div>
                        </div>

                        <div class="small font-monospace text-center text-white-50 mt-1">
                            CLIENTE:<?= (int)($_SESSION["usuario_id"] ?? 0) ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="productos" aria-labelledby="titulo-productos">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-2 mb-4">
                <div>
                    <p class="text-primary fw-semibold mb-1">Catálogo</p>
                    <h2 id="titulo-productos" class="h3 fw-bold mb-1">Productos disponibles</h2>
                    <p class="texto-secundario mb-0">Encontrá los productos disponibles con precios actualizados.</p>
                </div>
            </div>

            <div id="estadoCarga" class="panel-estado" role="status">
                <div class="spinner-border spinner-border-sm mb-3 text-primary" aria-hidden="true"></div>
                <p class="texto-secundario mb-0">Cargando catálogo de productos...</p>
            </div>

            <div id="listadoProductos" class="row g-3 g-lg-4" aria-live="polite"></div>
        </section>

        <section id="compras" class="mt-5 pt-2" aria-labelledby="titulo-compras">
            <div class="mb-4">
                <p class="text-primary fw-semibold mb-1">Mi actividad</p>
                <h2 id="titulo-compras" class="h3 fw-bold mb-1">Mis compras</h2>
                <p class="texto-secundario mb-0">Consultá el estado de tus pedidos y compras realizadas.</p>
            </div>
            <div class="seccion-card p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="comprasBody">
                            <tr>
                                <td colspan="7" class="text-center texto-secundario py-4">Cargando compras...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal Solicitar Vendedor -->
    <div class="modal fade" id="modalSolicitarVendedor" tabindex="-1" aria-labelledby="tituloModalSolicitarVendedor" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formSolicitarVendedor">
                    <div class="modal-header">
                        <h2 id="tituloModalSolicitarVendedor" class="modal-title fs-5 fw-bold">Solicitar atención de un vendedor</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorSolicitarVendedor" class="alert alert-danger d-none"></div>
                        <p class="texto-secundario mb-3">¿No podés comunicarte con tu vendedor habitual o necesitás asesoramiento sobre productos y pedidos? Envianos tu solicitud y te contactaremos a la brevedad.</p>
                        
                        <div class="mb-3">
                            <label for="solicitudMensaje" class="form-label">Mensaje o detalle de la consulta (opcional)</label>
                            <textarea id="solicitudMensaje" name="mensaje" class="form-control" rows="3" maxlength="500" placeholder="Ej: Necesito cotización por cantidad de cajas o comunicarme con un vendedor disponible..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Enviar solicitud</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modificar Cantidad de Compra -->
    <div class="modal fade" id="modalClienteCantidad" tabindex="-1" aria-labelledby="tituloClienteCantidad" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formClienteCantidad">
                    <div class="modal-header">
                        <h2 id="tituloClienteCantidad" class="modal-title fs-5 fw-bold">Modificar cantidad</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorClienteCantidad" class="alert alert-danger d-none"></div>
                        <input type="hidden" name="venta_id" id="clienteVentaCantidadId">
                        <input type="hidden" name="accion" value="modificar_cantidad">
                        <label for="clienteNuevaCantidad" class="form-label">Nueva cantidad</label>
                        <input type="number" min="1" id="clienteNuevaCantidad" name="cantidad" class="form-control" required>
                        <label for="clienteMotivoCantidad" class="form-label mt-3">Motivo (opcional)</label>
                        <textarea id="clienteMotivoCantidad" name="motivo" class="form-control" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-primary">Guardar cambio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cancelar Compra -->
    <div class="modal fade" id="modalClienteCancelar" tabindex="-1" aria-labelledby="tituloClienteCancelar" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formClienteCancelar">
                    <div class="modal-header">
                        <h2 id="tituloClienteCancelar" class="modal-title fs-5 fw-bold">Cancelar compra</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="errorClienteCancelar" class="alert alert-danger d-none"></div>
                        <input type="hidden" name="venta_id" id="clienteVentaCancelarId">
                        <input type="hidden" name="accion" value="cancelar">
                        <p class="texto-secundario">La compra permanecerá registrada como cancelada.</p>
                        <label for="clienteMotivoCancelar" class="form-label">Motivo (opcional)</label>
                        <textarea id="clienteMotivoCancelar" name="motivo" class="form-control" maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-danger">Cancelar compra</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="container pb-4 pt-4 text-center">
        <small class="texto-secundario">Control Stock · Tu catálogo y compras siempre disponibles</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const estadoCarga = document.getElementById("estadoCarga");
        const listadoProductos = document.getElementById("listadoProductos");
        const formatoMoneda = new Intl.NumberFormat("es-AR", {
            style: "currency",
            currency: "ARS",
            minimumFractionDigits: 2
        });

        function crearTarjetaProducto(producto) {
            const columna = document.createElement("div");
            columna.className = "col-12 col-md-6 col-xl-4";

            const tarjeta = document.createElement("article");
            tarjeta.className = "producto-card p-4 d-flex flex-column";

            const encabezado = document.createElement("div");
            encabezado.className = "d-flex align-items-start justify-content-between gap-3 mb-4";

            const icono = document.createElement("span");
            icono.className = "producto-icono";
            icono.setAttribute("aria-hidden", "true");
            icono.textContent = "";

            const stock = Number(producto.stock);
            const disponible = Number.isFinite(stock) && stock > 0;
            const estado = document.createElement("span");
            estado.className = `estado-stock ${disponible ? "estado-disponible" : "agotado"}`;
            estado.textContent = disponible ? "Disponible" : "Sin stock";

            const nombre = document.createElement("h3");
            nombre.className = "h5 fw-bold mb-2";
            nombre.textContent = producto.nombre;

            let detalleEmpaque = "";
            if (producto.presentacion && producto.presentacion !== "unidad" && Number(producto.unidades_por_bulto) > 1) {
                detalleEmpaque = ` · Presentación en ${producto.presentacion}s (${producto.unidades_por_bulto} un.)`;
            }

            const descripcion = document.createElement("p");
            descripcion.className = "texto-secundario small mb-4";
            descripcion.textContent = disponible
                ? `${stock} unidades disponibles${detalleEmpaque}`
                : "Consultá próximamente por nueva disponibilidad.";

            const precio = document.createElement("p");
            precio.className = "producto-precio mt-auto mb-0";
            precio.textContent = formatoMoneda.format(Number(producto.precio));

            encabezado.append(icono, estado);
            tarjeta.append(encabezado, nombre, descripcion, precio);
            columna.appendChild(tarjeta);

            return columna;
        }

        function mostrarMensaje(mensaje, esError = false) {
            listadoProductos.replaceChildren();
            estadoCarga.classList.remove("d-none");
            estadoCarga.setAttribute("role", esError ? "alert" : "status");
            estadoCarga.innerHTML = "";

            const titulo = document.createElement("p");
            titulo.className = `${esError ? "text-danger" : "texto-secundario"} fw-semibold mb-1`;
            titulo.textContent = mensaje;

            const detalle = document.createElement("small");
            detalle.className = "texto-secundario";
            detalle.textContent = esError
                ? "Actualizá la página para volver a intentarlo."
                : "Volvé a consultar más tarde.";

            estadoCarga.append(titulo, detalle);
        }

        async function cargarProductos() {
            try {
                const respuesta = await fetch("obtener_productos.php", {
                    headers: { "Accept": "application/json" }
                });

                if (respuesta.status === 401) {
                    window.location.href = "login.php";
                    return;
                }

                if (!respuesta.ok) {
                    throw new Error("No fue posible obtener el catálogo.");
                }

                const productos = await respuesta.json();
                estadoCarga.classList.add("d-none");
                listadoProductos.replaceChildren();

                if (!Array.isArray(productos) || productos.length === 0) {
                    mostrarMensaje("Todavía no hay productos disponibles.");
                    return;
                }

                productos.forEach((producto) => {
                    listadoProductos.appendChild(crearTarjetaProducto(producto));
                });
            } catch (error) {
                mostrarMensaje("No pudimos cargar los productos.", true);
            }
        }

        function fechaCompraLegible(valor) {
            if (!valor) return "—";
            const fecha = new Date(String(valor).replace(" ", "T"));
            return Number.isNaN(fecha.getTime()) ? valor : fecha.toLocaleString("es-AR");
        }

        function estadoCompra(estado) {
            const badge = document.createElement("span");
            const clases = { ACTIVA: "text-bg-success", MODIFICADA: "text-bg-warning", CANCELADA: "text-bg-danger" };
            badge.className = `badge ${clases[estado] || "text-bg-secondary"}`;
            badge.textContent = estado.charAt(0) + estado.slice(1).toLowerCase();
            return badge;
        }

        function tdCompra(texto, clase = "") {
            const td = document.createElement("td");
            td.className = clase;
            td.textContent = texto;
            return td;
        }

        async function cargarCompras() {
            const tbody = document.getElementById("comprasBody");
            try {
                const respuesta = await fetch("obtener_compras_cliente.php", { headers: { "Accept": "application/json" } });
                if (!respuesta.ok) throw new Error("No se pudieron cargar las compras.");
                const compras = await respuesta.json();
                tbody.replaceChildren();
                if (compras.length === 0) {
                    const fila = document.createElement("tr");
                    const vacio = tdCompra("Todavía no tenés compras asociadas a tu cuenta.", "text-center texto-secundario py-4");
                    vacio.colSpan = 7;
                    fila.appendChild(vacio);
                    tbody.appendChild(fila);
                    return;
                }
                compras.forEach((compra) => {
                    const fila = document.createElement("tr");
                    fila.append(tdCompra(`#${compra.id}`, "fw-semibold"));
                    fila.append(tdCompra(fechaCompraLegible(compra.fecha)));
                    fila.append(tdCompra(compra.producto_nombre));
                    fila.append(tdCompra(String(compra.cantidad)));
                    fila.append(tdCompra(formatoMoneda.format(Number(compra.total)), "fw-semibold"));
                    const estadoTd = document.createElement("td");
                    estadoTd.appendChild(estadoCompra(compra.estado));
                    fila.appendChild(estadoTd);
                    const acciones = document.createElement("td");
                    acciones.className = "text-nowrap";
                    if (compra.estado !== "CANCELADA") {
                        const cantidad = document.createElement("button");
                        cantidad.type = "button";
                        cantidad.className = "btn btn-outline-primary btn-sm me-2";
                        cantidad.textContent = "Cantidad";
                        cantidad.addEventListener("click", () => {
                            document.getElementById("clienteVentaCantidadId").value = compra.id;
                            document.getElementById("clienteNuevaCantidad").value = compra.cantidad;
                            bootstrap.Modal.getOrCreateInstance(document.getElementById("modalClienteCantidad")).show();
                        });
                        const cancelar = document.createElement("button");
                        cancelar.type = "button";
                        cancelar.className = "btn btn-outline-danger btn-sm";
                        cancelar.textContent = "Cancelar";
                        cancelar.addEventListener("click", () => {
                            document.getElementById("clienteVentaCancelarId").value = compra.id;
                            bootstrap.Modal.getOrCreateInstance(document.getElementById("modalClienteCancelar")).show();
                        });
                        acciones.append(cantidad, cancelar);
                    } else {
                        acciones.textContent = "—";
                    }
                    fila.appendChild(acciones);
                    tbody.appendChild(fila);
                });
            } catch (error) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">No pudimos cargar tus compras.</td></tr>';
            }
        }

        async function cargarEstadoSolicitudes() {
            const contenedor = document.getElementById("alertaSolicitudVendedor");
            try {
                const respuesta = await fetch("obtener_solicitudes_vendedor.php");
                if (!respuesta.ok) return;
                const solicitudes = await respuesta.json();
                const pendiente = solicitudes.find((s) => s.estado === "PENDIENTE");
                if (pendiente) {
                    contenedor.className = "alert alert-warning alert-dismissible fade show";
                    contenedor.innerHTML = `<strong>Solicitud de atención registrada:</strong> Tu pedido de asistencia de un vendedor está en proceso. Un representante se contactará con vos a la brevedad. <small class="text-muted">(${fechaCompraLegible(pendiente.fecha)})</small><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>`;
                    contenedor.classList.remove("d-none");
                }
            } catch (error) {
                console.error("Error al consultar solicitudes:", error);
            }
        }

        document.getElementById("formSolicitarVendedor").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            const errorBox = document.getElementById("errorSolicitarVendedor");
            errorBox.classList.add("d-none");
            try {
                const respuesta = await fetch("solicitar_vendedor.php", {
                    method: "POST",
                    headers: { "X-CSRF-Token": document.body.dataset.csrf },
                    body: new FormData(evento.currentTarget)
                });
                const datos = await respuesta.json();
                if (!respuesta.ok) throw new Error(datos.error || "No se pudo enviar la solicitud.");
                bootstrap.Modal.getInstance(document.getElementById("modalSolicitarVendedor")).hide();
                evento.currentTarget.reset();
                alert(datos.mensaje);
                await cargarEstadoSolicitudes();
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove("d-none");
            }
        });

        async function enviarCambioCliente(formulario, modalId, errorId) {
            const errorBox = document.getElementById(errorId);
            errorBox.classList.add("d-none");
            try {
                const respuesta = await fetch("modificar_venta.php", {
                    method: "POST",
                    headers: { "X-CSRF-Token": document.body.dataset.csrf },
                    body: new FormData(formulario)
                });
                const datos = await respuesta.json();
                if (!respuesta.ok) throw new Error(datos.error || "No se pudo actualizar la compra.");
                bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                formulario.reset();
                await Promise.all([cargarProductos(), cargarCompras()]);
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove("d-none");
            }
        }

        document.getElementById("formClienteCantidad").addEventListener("submit", (evento) => {
            evento.preventDefault();
            enviarCambioCliente(evento.currentTarget, "modalClienteCantidad", "errorClienteCantidad");
        });
        document.getElementById("formClienteCancelar").addEventListener("submit", (evento) => {
            evento.preventDefault();
            enviarCambioCliente(evento.currentTarget, "modalClienteCancelar", "errorClienteCancelar");
        });

        cargarProductos();
        cargarCompras();
        cargarEstadoSolicitudes();

        // Renderizar Código QR de Credencial Digital
        const qrContainer = document.getElementById("clienteQrBox");
        if (qrContainer && typeof QRCode === "function") {
            const clienteId = "<?= (int)($_SESSION['usuario_id'] ?? 0) ?>";
            const clienteDni = "<?= htmlspecialchars((string)($_SESSION['usuario_dni'] ?? ''), ENT_QUOTES, 'UTF-8') ?>";
            new QRCode(qrContainer, {
                text: `CLIENTE:${clienteId}:DNI:${clienteDni}`,
                width: 140,
                height: 140,
                colorDark: "#0f172a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    </script>
</body>
</html>
