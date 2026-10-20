<?php
require_once __DIR__ . "/seguridad.php";
requerirPaginaAutenticada(["admin", "vendedor"]);
require_once __DIR__ . "/FirestoreConexion.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$esEdicion = ($id > 0);
$proveedor = null;

$firestore = FirestoreConexion::obtenerFirestore();

if ($esEdicion) {
    $proveedor = $firestore->obtenerDocumento("proveedores", (string)$id);
    if (!$proveedor) {
        header("Location: admin.php?error=" . urlencode("El proveedor no existe."));
        exit;
    }
}

$rol = $_SESSION["usuario_rol"] ?? "admin";
$paginaRetorno = ($rol === "admin") ? "admin.php" : "vendedor.php";
$csrf = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esEdicion ? "Editar Proveedor" : "Nuevo Proveedor" ?> | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $paginaRetorno ?>">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Gestión de Proveedores</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="<?= $paginaRetorno ?>" class="btn btn-outline-secondary btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <a href="<?= $paginaRetorno ?>" class="text-decoration-none text-muted small">← Volver</a>
                        <h1 class="h3 fw-bold mt-1 mb-0"><?= $esEdicion ? "Editar Proveedor #{$id}" : "Nuevo Proveedor" ?></h1>
                        <p class="text-muted small mb-0">Completá los datos de contacto y facturación del proveedor.</p>
                    </div>
                </div>

                <div id="alertaError" class="alert alert-danger d-none mb-3"></div>
                <div id="alertaExito" class="alert alert-success d-none mb-3"></div>

                <div class="seccion-card">
                    <form id="formProveedorStandalone">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="mb-3">
                            <label for="provNombre" class="form-label">Nombre o Razón Social *</label>
                            <input type="text" class="form-control" id="provNombre" name="nombre" value="<?= htmlspecialchars($proveedor['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Distribuidora Bebidas SA" required>
                        </div>

                        <div class="mb-3">
                            <label for="provEmail" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="provEmail" name="email" value="<?= htmlspecialchars($proveedor['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="contacto@proveedor.com">
                        </div>

                        <div class="mb-3">
                            <label for="provTelefono" class="form-label">Teléfono de Contacto</label>
                            <input type="tel" class="form-control" id="provTelefono" name="telefono" value="<?= htmlspecialchars($proveedor['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: +54 9 11 1234-5678">
                        </div>

                        <div class="mb-3">
                            <label for="provDireccion" class="form-label">Dirección / Depósito</label>
                            <input type="text" class="form-control" id="provDireccion" name="direccion" value="<?= htmlspecialchars($proveedor['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Calle, Número, Ciudad">
                        </div>

                        <div class="mb-4">
                            <label for="provCuit" class="form-label">CUIT / CUIL</label>
                            <input type="text" class="form-control" id="provCuit" name="cuit_cuil" value="<?= htmlspecialchars($proveedor['cuit_cuil'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 30-12345678-9">
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= $paginaRetorno ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnGuardarProveedor">
                                <?= $esEdicion ? "Guardar Cambios" : "Registrar Proveedor" ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const esEdicion = <?= $esEdicion ? "true" : "false" ?>;
        const endpoint = esEdicion ? "modificar_proveedor.php" : "guardar_proveedor.php";
        const form = document.getElementById("formProveedorStandalone");
        const alertErr = document.getElementById("alertaError");
        const alertOk = document.getElementById("alertaExito");
        const btnSubmit = document.getElementById("btnGuardarProveedor");

        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            alertErr.classList.add("d-none");
            alertOk.classList.add("d-none");

            btnSubmit.disabled = true;
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando...`;

            try {
                const formData = new FormData(form);
                const resp = await fetch(endpoint, {
                    method: "POST",
                    headers: { "X-CSRF-Token": document.body.dataset.csrf },
                    body: formData
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    throw new Error(data.error || "Error al guardar el proveedor.");
                }

                alertOk.textContent = data.mensaje || "¡Proveedor guardado exitosamente!";
                alertOk.classList.remove("d-none");

                setTimeout(() => {
                    window.location.href = "<?= $paginaRetorno ?>";
                }, 1000);

            } catch (err) {
                alertErr.textContent = err.message;
                alertErr.classList.remove("d-none");
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>
