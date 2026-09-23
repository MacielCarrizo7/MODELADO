<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin", "vendedor", "cliente"]);

$rol = $_SESSION["usuario_rol"] ?? "cliente";
$nombreCompleto = trim(($_SESSION["usuario_nombre"] ?? "Usuario") . " " . ($_SESSION["usuario_apellido"] ?? ""));

$paginaRetorno = match ($rol) {
    "admin" => "admin.php",
    "vendedor" => "vendedor.php",
    default => "cliente.php"
};

$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Visual de Productos | Menú</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>">
    <!-- Barra Superior -->
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $paginaRetorno ?>">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Catálogo de Productos</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="<?= $paginaRetorno ?>" class="btn btn-outline-secondary btn-sm">← Volver a Mi Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <!-- Hero Header del Catálogo -->
        <div class="hero-panel p-4 p-md-5 mb-4 text-white">
            <div class="hero-contenido">
                <span class="badge text-bg-light text-primary fw-bold mb-2">Catálogo & Menú Oficial</span>
                <h1 class="h2 fw-bold mb-2">Explorá nuestro catálogo de artículos</h1>
                <p class="text-white-50 mb-0" style="max-width: 650px;">Consultá la variedad de productos, sabores, presentaciones y novedades organizadas por rubro.</p>
            </div>
        </div>

        <!-- Buscador y Filtros Rápidos -->
        <div class="seccion-card mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white">Buscar</span>
                        <input type="text" class="form-control" id="buscadorCatalogo" placeholder="Buscar por nombre de producto o descripción...">
                    </div>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <span class="text-muted small me-2" id="lblTotalCatalogo">Cargando productos...</span>
                    <button class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltroCatalogo">Limpiar filtros</button>
                </div>
            </div>

            <!-- Botones Pills de Categorías -->
            <div class="d-flex gap-2 overflow-x-auto pt-3 pb-1" id="contenedorCategoriasPills" style="scrollbar-width: thin;">
                <button class="btn btn-sm btn-primary rounded-pill px-3 py-1 text-nowrap active pill-cat" data-categoria="">
                    Todas las Categorías
                </button>
            </div>
        </div>

        <!-- Grid de Tarjetas de Productos -->
        <div class="row g-3 g-md-4" id="gridCatalogoProductos">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando catálogo...</span>
                </div>
                <p class="text-muted mt-2">Cargando catálogo...</p>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let catalogoData = { categorias: [], productos: [] };
        let categoriaActiva = "";
        let busquedaActiva = "";

        async function cargarCatalogo() {
            try {
                const resp = await fetch("obtener_catalogo.php");
                const data = await resp.json();
                catalogoData = data || { categorias: [], productos: [] };
                
                renderizarPillsCategorias();
                filtrarYRenderizarCatalogo();
            } catch (err) {
                console.error("Error al cargar catálogo:", err);
                document.getElementById("gridCatalogoProductos").innerHTML = `
                    <div class="col-12 text-center py-5">
                        <p class="text-danger fw-bold">No se pudo cargar el catálogo de productos.</p>
                    </div>
                `;
            }
        }

        function renderizarPillsCategorias() {
            const cont = document.getElementById("contenedorCategoriasPills");
            cont.innerHTML = `
                <button class="btn btn-sm btn-primary rounded-pill px-3 py-1 text-nowrap pill-cat active" data-categoria="">
                    Todos
                </button>
            `;

            catalogoData.categorias.forEach(cat => {
                const btn = document.createElement("button");
                btn.className = "btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 text-nowrap pill-cat";
                btn.setAttribute("data-categoria", cat.nombre);
                btn.textContent = cat.nombre;
                cont.appendChild(btn);
            });

            document.querySelectorAll(".pill-cat").forEach(btn => {
                btn.addEventListener("click", () => {
                    document.querySelectorAll(".pill-cat").forEach(b => {
                        b.classList.remove("btn-primary", "active");
                        b.classList.add("btn-outline-secondary");
                    });
                    btn.classList.remove("btn-outline-secondary");
                    btn.classList.add("btn-primary", "active");
                    categoriaActiva = btn.getAttribute("data-categoria");
                    filtrarYRenderizarCatalogo();
                });
            });
        }

        function filtrarYRenderizarCatalogo() {
            const grid = document.getElementById("gridCatalogoProductos");
            const lblTotal = document.getElementById("lblTotalCatalogo");

            const filtrados = catalogoData.productos.filter(p => {
                if (categoriaActiva !== "" && p.categoria_nombre !== categoriaActiva && (String(p.categoria_id) !== categoriaActiva)) {
                    return false;
                }
                if (busquedaActiva !== "") {
                    const q = busquedaActiva.toLowerCase();
                    const n = (p.nombre || "").toLowerCase();
                    const d = (p.descripcion || "").toLowerCase();
                    const c = (p.categoria_nombre || "").toLowerCase();
                    if (!n.includes(q) && !d.includes(q) && !c.includes(q)) {
                        return false;
                    }
                }
                return true;
            });

            lblTotal.textContent = `${filtrados.length} producto(s) encontrado(s)`;

            if (filtrados.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <h3 class="h5 fw-bold text-dark">No se encontraron productos</h3>
                        <p class="text-muted small">Probá buscando con otras palabras o seleccioná otra categoría.</p>
                    </div>
                `;
                return;
            }

            grid.replaceChildren();

            filtrados.forEach(prod => {
                const col = document.createElement("div");
                col.className = "col-12 col-sm-6 col-md-4 col-lg-3";

                const card = document.createElement("div");
                card.className = "card h-100 border-0 shadow-sm rounded-4 overflow-hidden stat-card p-0 d-flex flex-column";

                // Contenedor de Imagen
                const imgWrap = document.createElement("div");
                imgWrap.className = "position-relative bg-light d-flex align-items-center justify-content-center";
                imgWrap.style.height = "190px";
                imgWrap.style.overflow = "hidden";

                if (prod.imagen_url) {
                    const img = document.createElement("img");
                    img.src = prod.imagen_url;
                    img.alt = prod.nombre;
                    img.className = "w-100 h-100";
                    img.style.objectFit = "cover";
                    img.onerror = () => {
                        imgWrap.innerHTML = `<span class="small text-muted">Sin imagen</span>`;
                    };
                    imgWrap.appendChild(img);
                } else {
                    imgWrap.innerHTML = `<span class="small text-muted">Sin imagen</span>`;
                }

                // Badge de categoría superpuesto
                const badgeCat = document.createElement("span");
                badgeCat.className = "badge bg-dark bg-opacity-75 text-white position-absolute top-0 start-0 m-2 rounded-pill px-2 py-1 small";
                badgeCat.textContent = prod.categoria_nombre || "General";
                imgWrap.appendChild(badgeCat);

                card.appendChild(imgWrap);

                // Cuerpo de la tarjeta
                const body = document.createElement("div");
                body.className = "p-3 d-flex flex-column flex-grow-1";

                const title = document.createElement("h3");
                title.className = "h6 fw-bold text-dark mb-1 text-truncate-2";
                title.title = prod.nombre;
                title.textContent = prod.nombre;
                body.appendChild(title);

                const desc = document.createElement("p");
                desc.className = "small text-muted mb-3 flex-grow-1";
                desc.textContent = prod.descripcion || "Sin descripción detallada disponible.";
                desc.style.display = "-webkit-box";
                desc.style.webkitLineClamp = "3";
                desc.style.webkitBoxOrient = "vertical";
                desc.style.overflow = "hidden";
                body.appendChild(desc);

                const footerDiv = document.createElement("div");
                footerDiv.className = "d-flex align-items-center justify-content-between pt-2 border-top mt-auto";

                const presBadge = document.createElement("span");
                presBadge.className = "badge text-bg-light border text-muted text-uppercase";
                presBadge.textContent = prod.presentacion || "Unidad";
                footerDiv.appendChild(presBadge);

                // Botón Editar exclusivo para Administradores
                const esAdmin = document.body.getAttribute("data-rol") === "admin";
                if (esAdmin) {
                    const btnEditar = document.createElement("button");
                    btnEditar.type = "button";
                    btnEditar.className = "btn btn-outline-primary btn-sm py-1 px-2";
                    btnEditar.textContent = "Editar";
                    btnEditar.title = "Modificar foto, categoría o descripción";
                    btnEditar.addEventListener("click", () => {
                        abrirModalEditarCatalogo(prod);
                    });
                    footerDiv.appendChild(btnEditar);
                }

                body.appendChild(footerDiv);
                card.appendChild(body);
                col.appendChild(card);
                grid.appendChild(col);
            });
        }

        // Modal de Edición Rápida de Catálogo (Solo Admin)
        function abrirModalEditarCatalogo(prod) {
            document.getElementById("editProdId").value = prod.id;
            document.getElementById("editProdNombre").value = prod.nombre;
            document.getElementById("editProdDesc").value = prod.descripcion || "";
            document.getElementById("editProdImgUrl").value = prod.imagen_url || "";
            document.getElementById("editProdImgArchivo").value = "";

            // Link a edición completa
            const linkAvanzado = document.getElementById("linkEdicionAvanzada");
            if (linkAvanzado) {
                linkAvanzado.href = `producto_form.php?id=${encodeURIComponent(prod.id)}`;
            }

            // Preview imagen
            const imgPreview = document.getElementById("editImgPreview");
            const imgPlaceholder = document.getElementById("editImgPlaceholder");
            if (prod.imagen_url) {
                imgPreview.src = prod.imagen_url;
                imgPreview.classList.remove("d-none");
                imgPlaceholder.classList.add("d-none");
            } else {
                imgPreview.src = "";
                imgPreview.classList.add("d-none");
                imgPlaceholder.classList.remove("d-none");
            }

            // Categorías select
            const selCat = document.getElementById("editProdCat");
            selCat.replaceChildren(new Option("-- Seleccionar Categoría --", ""));
            catalogoData.categorias.forEach(c => {
                const opt = new Option(c.nombre, c.id);
                opt.dataset.nombre = c.nombre;
                if ((prod.categoria_id && String(prod.categoria_id) === String(c.id)) || prod.categoria_nombre === c.nombre) {
                    opt.selected = true;
                }
                selCat.appendChild(opt);
            });

            document.getElementById("editAlertaError").classList.add("d-none");
            document.getElementById("editAlertaExito").classList.add("d-none");

            const modalEl = document.getElementById("modalEditarProductoCatalogo");
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalInstance.show();
        }

        // Eventos
        const inputBusqueda = document.getElementById("buscadorCatalogo");
        inputBusqueda.addEventListener("input", (e) => {
            busquedaActiva = e.target.value.trim();
            filtrarYRenderizarCatalogo();
        });

        document.getElementById("btnLimpiarFiltroCatalogo").addEventListener("click", () => {
            inputBusqueda.value = "";
            busquedaActiva = "";
            categoriaActiva = "";
            document.querySelectorAll(".pill-cat").forEach(b => {
                b.classList.remove("btn-primary", "active");
                b.classList.add("btn-outline-secondary");
            });
            const first = document.querySelector(".pill-cat");
            if (first) {
                first.classList.remove("btn-outline-secondary");
                first.classList.add("btn-primary", "active");
            }
            filtrarYRenderizarCatalogo();
        });

        // Previsualización de imagen en modal de edición
        const inputEditArchivo = document.getElementById("editProdImgArchivo");
        const inputEditUrl = document.getElementById("editProdImgUrl");
        const imgEditPreview = document.getElementById("editImgPreview");
        const imgEditPlaceholder = document.getElementById("editImgPlaceholder");

        if (inputEditArchivo) {
            inputEditArchivo.addEventListener("change", (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        imgEditPreview.src = ev.target.result;
                        imgEditPreview.classList.remove("d-none");
                        imgEditPlaceholder.classList.add("d-none");
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        if (inputEditUrl) {
            inputEditUrl.addEventListener("input", () => {
                const url = inputEditUrl.value.trim();
                if (url) {
                    imgEditPreview.src = url;
                    imgEditPreview.classList.remove("d-none");
                    imgEditPlaceholder.classList.add("d-none");
                }
            });
        }

        // Guardar edición rápida de producto
        const formEditar = document.getElementById("formEditarProductoCatalogo");
        if (formEditar) {
            formEditar.addEventListener("submit", async (e) => {
                e.preventDefault();
                const errBox = document.getElementById("editAlertaError");
                const okBox = document.getElementById("editAlertaExito");
                const btnSave = document.getElementById("btnGuardarEdicionCatalogo");

                errBox.classList.add("d-none");
                okBox.classList.add("d-none");

                btnSave.disabled = true;
                const originalText = btnSave.innerHTML;
                btnSave.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando...`;

                try {
                    const formData = new FormData(formEditar);
                    const selCat = document.getElementById("editProdCat");
                    const optSel = selCat.selectedOptions[0];
                    if (optSel && optSel.dataset.nombre) {
                        formData.set("categoria_nombre", optSel.dataset.nombre);
                    }

                    const resp = await fetch("modificar_producto.php", {
                        method: "POST",
                        headers: {
                            "X-CSRF-Token": document.body.dataset.csrf
                        },
                        body: formData
                    });

                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) {
                        throw new Error(data.error || "No se pudo actualizar el producto.");
                    }

                    okBox.textContent = "✓ ¡Producto actualizado correctamente!";
                    okBox.classList.remove("d-none");

                    // Actualizar en memoria y volver a renderizar
                    const idMod = parseInt(formData.get("id"));
                    const prodEncontrado = catalogoData.productos.find(p => p.id === idMod);
                    if (prodEncontrado) {
                        prodEncontrado.nombre = formData.get("nombre");
                        prodEncontrado.descripcion = formData.get("descripcion");
                        if (optSel && optSel.dataset.nombre) {
                            prodEncontrado.categoria_nombre = optSel.dataset.nombre;
                            prodEncontrado.categoria_id = optSel.value;
                        }
                        if (data.imagen_url) {
                            prodEncontrado.imagen_url = data.imagen_url;
                        }
                    }

                    filtrarYRenderizarCatalogo();

                    setTimeout(() => {
                        const modalEl = document.getElementById("modalEditarProductoCatalogo");
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();
                        btnSave.disabled = false;
                        btnSave.innerHTML = originalText;
                    }, 800);

                } catch (err) {
                    errBox.textContent = err.message;
                    errBox.classList.remove("d-none");
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalText;
                }
            });
        }

        document.addEventListener("DOMContentLoaded", cargarCatalogo);
    </script>

    <?php if ($rol === "admin"): ?>
    <!-- Modal de Edición Rápida para Administradores -->
    <div class="modal fade" id="modalEditarProductoCatalogo" tabindex="-1" aria-labelledby="tituloModalEditarCatalogo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="formEditarProductoCatalogo" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="editProdId">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-2">
                            <div>
                                <h2 id="tituloModalEditarCatalogo" class="modal-title fs-5 fw-bold mb-0">Editar Ficha del Producto</h2>
                                <small class="text-muted">Modificá foto, categoría y descripción para el catálogo.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="editAlertaError" class="alert alert-danger d-none mb-3"></div>
                        <div id="editAlertaExito" class="alert alert-success d-none mb-3"></div>

                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="editProdNombre" class="form-label fw-bold">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="editProdNombre" name="nombre" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="editProdCat" class="form-label fw-bold">Categoría *</label>
                                <select class="form-select" id="editProdCat" name="categoria_id" required></select>
                            </div>

                            <div class="col-12">
                                <label for="editProdDesc" class="form-label">Descripción Comercial</label>
                                <textarea class="form-control" id="editProdDesc" name="descripcion" rows="3" placeholder="Detalles de presentación, características..."></textarea>
                            </div>

                            <!-- Foto del Producto -->
                            <div class="col-12">
                                <label class="form-label fw-bold">Foto o Imagen del Producto</label>
                                <div class="p-3 border rounded-3 bg-light">
                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-sm-4 text-center">
                                            <div class="border rounded-3 bg-white p-2 d-flex align-items-center justify-content-center" style="height: 120px; overflow: hidden;">
                                                <span id="editImgPlaceholder" class="text-muted small">Sin imagen</span>
                                                <img id="editImgPreview" src="" alt="Foto" class="d-none" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-8">
                                            <label for="editProdImgArchivo" class="form-label small text-muted">Subir imagen desde el dispositivo (JPG, PNG, WebP)</label>
                                            <input type="file" class="form-control form-control-sm mb-2" id="editProdImgArchivo" name="imagen_archivo" accept="image/*">
                                            
                                            <label for="editProdImgUrl" class="form-label small text-muted">O ingresar enlace / URL de imagen web</label>
                                            <input type="url" class="form-control form-control-sm" id="editProdImgUrl" name="imagen_url" placeholder="https://ejemplo.com/foto.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <a href="#" id="linkEdicionAvanzada" class="btn btn-outline-secondary btn-sm">
                            Edición avanzada (costos, stock, códigos)
                        </a>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary fw-bold px-4" id="btnGuardarEdicionCatalogo">
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
