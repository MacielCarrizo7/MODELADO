<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

$firestore = FirestoreConexion::obtenerFirestore();

$proveedores = $firestore->obtenerColeccion("proveedores");
usort($proveedores, fn($a, $b) => strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? ""));

$categorias = $firestore->obtenerColeccion("categorias");
usort($categorias, fn($a, $b) => strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? ""));

$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva de Productos por Lote | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>">
    <!-- Barra Superior -->
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Alta Masiva por Lote</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="admin.php" class="btn btn-outline-secondary btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <!-- Encabezado -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="admin.php" class="text-decoration-none text-muted small">← Volver al Inventario</a>
                <h1 class="h3 fw-bold mt-1 mb-0">📦 Carga Masiva de Productos (Remito / Lote)</h1>
                <p class="text-muted small mb-0">Ingresá múltiples productos asociados a un mismo proveedor y comprobante de compra en una sola pantalla.</p>
            </div>
        </div>

        <!-- Alertas -->
        <div id="alertaError" class="alert alert-danger d-none mb-3" role="alert"></div>
        <div id="alertaExito" class="alert alert-success d-none mb-3" role="alert"></div>

        <!-- 1. Datos del Proveedor y Comprobante de Compra -->
        <div class="seccion-card mb-4">
            <h2 class="h5 fw-bold text-primary mb-3">1. Datos del Proveedor y Comprobante de Compra</h2>
            <div class="p-3 bg-light rounded-3 border">
                <div class="row g-3 align-items-center">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold" for="masivoProveedor">🏢 Proveedor del Lote *</label>
                        <select class="form-select" id="masivoProveedor" required>
                            <option value="">-- Seleccionar proveedor del remito --</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-1">
                            <input type="text" class="form-control form-control-sm mt-1" id="masivoProveedorTexto" placeholder="O escribir nombre de nuevo proveedor...">
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold" for="masivoNumeroFactura">📄 N° Factura / Remito</label>
                        <input type="text" class="form-control" id="masivoNumeroFactura" placeholder="Ej: FC-A-0001-00123456">
                    </div>

                    <div class="col-12 col-md-3 d-flex align-items-center pt-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="masivoSinFactura">
                            <label class="form-check-label small" for="masivoSinFactura">Ingreso sin comprobante / factura</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Tabla Dinámica de Productos -->
        <div class="seccion-card mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h2 class="h5 fw-bold text-primary mb-0">2. Artículos del Lote</h2>
                    <small class="text-muted">Completá una fila por cada artículo que recibiste.</small>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" id="btnAgregarFila">
                    ➕ Agregar otra fila de producto
                </button>
            </div>

            <div class="table-responsive border rounded-3 mb-3 bg-white" style="min-height: 250px;">
                <table class="table table-bordered table-hover align-middle mb-0" id="tablaMasiva">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="min-width: 200px;">Producto *</th>
                            <th style="min-width: 150px;">Categoría</th>
                            <th style="min-width: 140px;">Código Barras</th>
                            <th style="min-width: 130px;">Presentación</th>
                            <th style="width: 100px;">Cant. *</th>
                            <th style="width: 110px;">P. Costo ($)</th>
                            <th style="width: 110px;">P. Venta ($) *</th>
                            <th style="width: 140px;">Vencimiento</th>
                            <th style="width: 50px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoFilasMasivas"></tbody>
                </table>
            </div>

            <!-- Resumen del Lote -->
            <div class="p-3 bg-light rounded-3 border mb-3">
                <div class="row text-center g-3">
                    <div class="col-12 col-sm-4">
                        <span class="text-muted small d-block">Líneas de productos</span>
                        <strong class="fs-5 text-dark" id="resumenTotalProd">0</strong>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="text-muted small d-block">Unidades físicas totales</span>
                        <strong class="fs-5 text-dark" id="resumenTotalUnidades">0 un.</strong>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="text-muted small d-block">Valor total de venta del lote</span>
                        <strong class="fs-5 text-success" id="resumenValorTotal">$ 0,00</strong>
                    </div>
                </div>
            </div>

            <!-- Botón Guardar -->
            <div class="d-flex justify-content-between align-items-center pt-2">
                <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
                <button type="button" class="btn btn-success px-4 py-2 fs-5 fw-bold shadow" id="btnGuardarLote">
                    💾 Guardar Lote Completo en Firestore
                </button>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const formatoMoneda = new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS" });
        const categoriasDisponibles = <?= json_encode($categorias, JSON_UNESCAPED_UNICODE) ?>;
        const csrfToken = document.body.dataset.csrf;

        const tbody = document.getElementById("cuerpoFilasMasivas");
        const chkSinFactura = document.getElementById("masivoSinFactura");
        const inputFactura = document.getElementById("masivoNumeroFactura");

        chkSinFactura.addEventListener("change", () => {
            inputFactura.disabled = chkSinFactura.checked;
            if (chkSinFactura.checked) inputFactura.value = "";
        });

        function crearFila(indice) {
            const tr = document.createElement("tr");

            // Opciones de categoría
            let optCatHtml = `<option value="">-- Sin categoría --</option>`;
            categoriasDisponibles.forEach(c => {
                optCatHtml += `<option value="${c.id}" data-nombre="${c.nombre}">${c.icono || '🏷️'} ${c.nombre}</option>`;
            });

            tr.innerHTML = `
                <td class="text-muted small text-center">${indice}</td>
                <td>
                    <input type="text" class="form-control form-control-sm masivo-nombre" placeholder="Nombre del artículo" required>
                </td>
                <td>
                    <select class="form-select form-select-sm masivo-cat">
                        ${optCatHtml}
                    </select>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace masivo-cb" placeholder="EAN-13">
                        <button class="btn btn-outline-secondary btn-sm btn-gen-cb" type="button" title="Generar código">🎲</button>
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

            // Toggle unidades por bulto
            const selPres = tr.querySelector(".masivo-pres");
            const inputUnid = tr.querySelector(".masivo-unid-bulto");
            selPres.addEventListener("change", () => {
                if (selPres.value === "caja" || selPres.value === "bulto") {
                    inputUnid.classList.remove("d-none");
                    if (parseInt(inputUnid.value) <= 1) inputUnid.value = selPres.value === "caja" ? 12 : 24;
                } else {
                    inputUnid.classList.add("d-none");
                    inputUnid.value = 1;
                }
                recalcularResumen();
            });

            // Generar CB
            const btnGen = tr.querySelector(".btn-gen-cb");
            const inputCb = tr.querySelector(".masivo-cb");
            btnGen.addEventListener("click", () => {
                inputCb.value = "779" + Math.floor(Math.random() * 1000000000).toString().padStart(9, "0");
            });

            // Recalcular al editar
            tr.querySelectorAll("input, select").forEach(el => {
                el.addEventListener("input", recalcularResumen);
            });

            // Quitar fila
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

        // Agregar fila
        document.getElementById("btnAgregarFila").addEventListener("click", () => {
            const nueva = crearFila(tbody.children.length + 1);
            tbody.appendChild(nueva);
            nueva.querySelector(".masivo-nombre").focus();
            recalcularResumen();
        });

        // Inicializar con 4 filas
        for (let i = 1; i <= 4; i++) {
            tbody.appendChild(crearFila(i));
        }

        // Guardar lote
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
                alertErr.textContent = "Completá al menos un producto para registrar el lote.";
                alertErr.classList.remove("d-none");
                return;
            }

            const btnSave = document.getElementById("btnGuardarLote");
            btnSave.disabled = true;
            btnSave.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando ${productosLote.length} productos...`;

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
                    throw new Error(data.error || "Error al procesar la carga masiva.");
                }

                alertOk.innerHTML = `<strong>¡Lote guardado con éxito!</strong> ${data.mensaje || `Se registraron ${data.total_guardados} productos.`}`;
                alertOk.classList.remove("d-none");
                window.scrollTo({ top: 0, behavior: "smooth" });

                setTimeout(() => {
                    window.location.href = "admin.php";
                }, 1500);

            } catch (err) {
                alertErr.textContent = err.message;
                alertErr.classList.remove("d-none");
                btnSave.disabled = false;
                btnSave.innerHTML = "💾 Guardar Lote Completo en Firestore";
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        });
    </script>
</body>
</html>
