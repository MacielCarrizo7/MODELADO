<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once __DIR__ . "/FirestoreConexion.php";

$firestore = FirestoreConexion::obtenerFirestore();
$categorias = $firestore->obtenerTodos("categorias");
usort($categorias, fn($a, $b) => strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? ""));

// Obtener conteo de productos por categoría
$productos = $firestore->obtenerColeccion("productos");
$conteoPorCat = [];
foreach ($productos as $p) {
    $cId = $p["categoria_id"] ?? null;
    $cNom = $p["categoria_nombre"] ?? $p["categoria"] ?? "";
    if ($cId) {
        $conteoPorCat[$cId] = ($conteoPorCat[$cId] ?? 0) + 1;
    } elseif ($cNom) {
        $conteoPorCat[$cNom] = ($conteoPorCat[$cNom] ?? 0) + 1;
    }
}

$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body data-rol="admin" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Categorías</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="catalogo.php" class="btn btn-outline-primary btn-sm">👁️ Ver Catálogo</a>
                <a href="admin.php" class="btn btn-outline-secondary btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="row g-4">
            <!-- Formulario Crear/Editar Categoría -->
            <div class="col-12 col-md-5">
                <div class="seccion-card">
                    <h2 class="h5 fw-bold text-primary mb-3" id="formTitulo">✨ Nueva Categoría</h2>
                    <p class="text-muted small mb-3">Crea categorías para organizar el catálogo de productos y facilitar la búsqueda.</p>

                    <div id="alertaError" class="alert alert-danger d-none mb-3"></div>
                    <div id="alertaExito" class="alert alert-success d-none mb-3"></div>

                    <form id="formCategoria">
                        <input type="hidden" id="catId" name="id" value="">

                        <div class="mb-3">
                            <label for="catIcono" class="form-label">Ícono / Emoji</label>
                            <select class="form-select" id="catIcono" name="icono">
                                <option value="🏷️">🏷️ Etiqueta General</option>
                                <option value="🥤">🥤 Gaseosas y Bebidas</option>
                                <option value="🍫">🍫 Chocolates y Dulces</option>
                                <option value="🍪">🍪 Galletitas y Alfajores</option>
                                <option value="🍿">🍿 Snacks y Salados</option>
                                <option value="🥖">🥖 Panificados</option>
                                <option value="🥛">🥛 Lácteos y Yogures</option>
                                <option value="🍦">🍦 Helados y Congelados</option>
                                <option value="🧼">🧼 Limpieza e Higiene</option>
                                <option value="📦">📦 Varios / Otros</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="catNombre" class="form-label">Nombre de la Categoría *</label>
                            <input type="text" class="form-control" id="catNombre" name="nombre" placeholder="Ej: Gaseosas" required>
                        </div>

                        <div class="mb-4">
                            <label for="catDescripcion" class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" id="catDescripcion" name="descripcion" rows="2" placeholder="Breve detalle de los productos que abarca..."></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary d-none" id="btnCancelarEdicion">Cancelar</button>
                            <button type="submit" class="btn btn-primary flex-grow-1 fw-bold" id="btnGuardarCat">
                                + Guardar Categoría
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Listado de Categorías -->
            <div class="col-12 col-md-7">
                <div class="seccion-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold text-dark mb-0">Categorías Registradas</h2>
                        <span class="badge text-bg-primary"><?= count($categorias) ?> categorías</span>
                    </div>

                    <div class="table-responsive border rounded-3 bg-white">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Productos</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categorias)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No hay categorías registradas aún.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categorias as $cat): ?>
                                        <?php 
                                            $cantProds = ($conteoPorCat[$cat['id']] ?? 0) + ($conteoPorCat[$cat['nombre']] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fs-5 me-1"><?= htmlspecialchars($cat['icono'] ?? '🏷️', ENT_QUOTES, 'UTF-8') ?></span>
                                                <strong class="text-dark"><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </td>
                                            <td class="small text-muted">
                                                <?= htmlspecialchars($cat['descripcion'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-light border"><?= $cantProds ?> prod.</span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary btn-editar" 
                                                        data-id="<?= $cat['id'] ?>" 
                                                        data-nombre="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>" 
                                                        data-icono="<?= htmlspecialchars($cat['icono'] ?? '🏷️', ENT_QUOTES, 'UTF-8') ?>" 
                                                        data-descripcion="<?= htmlspecialchars($cat['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                                        title="Editar">
                                                        ✏️
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-eliminar" 
                                                        data-id="<?= $cat['id'] ?>" 
                                                        data-nombre="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>" 
                                                        data-cant="<?= $cantProds ?>"
                                                        title="Eliminar">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const form = document.getElementById("formCategoria");
        const catId = document.getElementById("catId");
        const catNombre = document.getElementById("catNombre");
        const catIcono = document.getElementById("catIcono");
        const catDescripcion = document.getElementById("catDescripcion");
        const formTitulo = document.getElementById("formTitulo");
        const btnGuardarCat = document.getElementById("btnGuardarCat");
        const btnCancelar = document.getElementById("btnCancelarEdicion");
        const alertErr = document.getElementById("alertaError");
        const alertOk = document.getElementById("alertaExito");

        // Editar categoría
        document.querySelectorAll(".btn-editar").forEach(btn => {
            btn.addEventListener("click", () => {
                catId.value = btn.dataset.id;
                catNombre.value = btn.dataset.nombre;
                catIcono.value = btn.dataset.icono || "🏷️";
                catDescripcion.value = btn.dataset.descripcion || "";
                formTitulo.textContent = `✏️ Editar Categoría #${btn.dataset.id}`;
                btnGuardarCat.textContent = "💾 Guardar Cambios";
                btnCancelar.classList.remove("d-none");
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        });

        // Cancelar edición
        btnCancelar.addEventListener("click", () => {
            form.reset();
            catId.value = "";
            formTitulo.textContent = "✨ Nueva Categoría";
            btnGuardarCat.textContent = "+ Guardar Categoría";
            btnCancelar.classList.add("d-none");
        });

        // Eliminar categoría
        document.querySelectorAll(".btn-eliminar").forEach(btn => {
            btn.addEventListener("click", async () => {
                const id = btn.dataset.id;
                const nombre = btn.dataset.nombre;
                const cant = parseInt(btn.dataset.cant) || 0;

                if (cant > 0) {
                    alert(`No podés eliminar la categoría "${nombre}" porque tiene ${cant} producto(s) asignado(s). Reasignalor primero.`);
                    return;
                }

                if (!confirm(`¿Estás seguro de eliminar la categoría "${nombre}"?`)) return;

                try {
                    const formData = new FormData();
                    formData.append("id", id);
                    const resp = await fetch("eliminar_categoria.php", {
                        method: "POST",
                        headers: { "X-CSRF-Token": document.body.dataset.csrf },
                        body: formData
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) throw new Error(data.error || "Error al eliminar categoría.");
                    window.location.reload();
                } catch (err) {
                    alert(err.message);
                }
            });
        });

        // Guardar categoría
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            alertErr.classList.add("d-none");
            alertOk.classList.add("d-none");

            btnGuardarCat.disabled = true;
            try {
                const formData = new FormData(form);
                const resp = await fetch("guardar_categoria.php", {
                    method: "POST",
                    headers: { "X-CSRF-Token": document.body.dataset.csrf },
                    body: formData
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(data.error || "Error al guardar la categoría.");

                alertOk.textContent = data.mensaje || "Categoría guardada con éxito.";
                alertOk.classList.remove("d-none");

                setTimeout(() => {
                    window.location.reload();
                }, 800);
            } catch (err) {
                alertErr.textContent = err.message;
                alertErr.classList.remove("d-none");
                btnGuardarCat.disabled = false;
            }
        });
    </script>
</body>
</html>
