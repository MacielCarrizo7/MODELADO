<?php
require_once "seguridad.php";
requerirPaginaAutenticada(["admin"]);
require_once "FirestoreConexion.php";

$firestore = FirestoreConexion::obtenerFirestore();
$error = "";
$rolesValidos = ["admin", "vendedor", "cliente"];

if (!isset($_SESSION["csrf_crear_usuario"])) {
    $_SESSION["csrf_crear_usuario"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $dni = trim($_POST["dni"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $password = $_POST["password"] ?? "";
    $rol = $_POST["rol"] ?? "";

    if (!hash_equals($_SESSION["csrf_crear_usuario"], $token)) {
        http_response_code(400);
        $error = "La sesión del formulario venció. Actualizá la página e intentá nuevamente.";
    } elseif ($dni === "" || $nombre === "" || $apellido === "" || $password === "") {
        $error = "Completá todos los campos obligatorios.";
    } elseif (mb_strlen($dni) > 20 || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        $error = "Uno de los datos supera la longitud permitida.";
    } elseif (!in_array($rol, $rolesValidos, true)) {
        $error = "Seleccioná un rol válido.";
    } else {
        try {
            $check = $firestore->consultar("usuarios", [["dni", "==", $dni]]);

            if (!empty($check)) {
                $error = "Ya existe una cuenta registrada con el DNI ingresado.";
            } else {
                $nuevoId = FirestoreConexion::obtenerSiguienteIdUsuario();
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $nuevoUsuario = [
                    "id" => $nuevoId,
                    "dni" => $dni,
                    "nombre" => $nombre,
                    "apellido" => $apellido,
                    "password" => $hash,
                    "rol" => $rol,
                    "activo" => 1,
                    "fecha_registro" => date("Y-m-d H:i:s"),
                    "totp_secret_encrypted" => null,
                    "totp_enabled" => 0,
                    "totp_confirmed_at" => null,
                    "totp_last_timeslice" => null,
                ];

                $firestore->guardarDocumento("usuarios", (string)$nuevoId, $nuevoUsuario, true);

                $_SESSION["csrf_crear_usuario"] = bin2hex(random_bytes(32));
                header("Location: registro.php?creado=1");
                exit;
            }
        } catch (Throwable $e) {
            error_log("Error al crear usuario en Firestore: " . $e->getMessage());
            $error = "Ocurrió un error al registrar el usuario en la base de datos.";
        }
    }
}

try {
    $usuarios = $firestore->consultar("usuarios");
    usort($usuarios, function ($a, $b) {
        $fechaA = $a["fecha_registro"] ?? "";
        $fechaB = $b["fecha_registro"] ?? "";
        if ($fechaA === $fechaB) {
            return ($b["id"] ?? 0) <=> ($a["id"] ?? 0);
        }
        return strcmp($fechaB, $fechaA);
    });
} catch (Throwable $e) {
    error_log("Error al listar usuarios desde Firestore: " . $e->getMessage());
    $usuarios = [];
}

$clientes = array_filter($usuarios, fn($u) => ($u["rol"] ?? "") === "cliente");
$equipo = array_filter($usuarios, fn($u) => ($u["rol"] ?? "") === "admin" || ($u["rol"] ?? "") === "vendedor");

function e(string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios y clientes | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar app-navbar sticky-top py-3">
        <div class="container d-flex align-items-center justify-content-between gap-3">
            <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="admin.php">
                <span class="marca-icono" aria-hidden="true">CS</span>
                <span class="fw-bold">Control Stock</span>
            </a>
            <div class="d-flex gap-2">
                <a href="admin.php" class="btn btn-outline-secondary btn-sm">Volver al panel</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Cerrar sesión</a>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">
        <div class="mb-4">
            <p class="etiqueta text-primary mb-1">Administración de Cuentas (Solo Administrador)</p>
            <h1 class="h2 fw-bold mb-2">Gestión de Usuarios y Clientes</h1>
            <p class="texto-secundario mb-0">Alta de cuentas y segmentación de cartera de clientes y personal operativo.</p>
        </div>

        <?php if (isset($_GET["creado"])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="status">
                El usuario fue registrado correctamente con su contraseña encriptada.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="row g-4 align-items-start">
            <!-- Formulario de Alta de Usuario -->
            <div class="col-12 col-lg-4">
                <section class="seccion-card" aria-labelledby="titulo-crear-usuario">
                    <h2 id="titulo-crear-usuario" class="h5 fw-bold mb-1">Registrar cuenta</h2>
                    <p class="texto-secundario small mb-4">Completá los 4 datos requeridos para crear un nuevo usuario o cliente.</p>
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_crear_usuario"]) ?>">
                        <div class="mb-3">
                            <label for="dni" class="form-label">DNI *</label>
                            <input type="text" id="dni" name="dni" class="form-control" inputmode="numeric" maxlength="20" placeholder="Ej: 42123456" required>
                        </div>
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" maxlength="100" placeholder="Ej: Juan" required>
                        </div>
                        <div class="mb-3">
                            <label for="apellido" class="form-label">Apellido *</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" maxlength="100" placeholder="Ej: Pérez" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña inicial *</label>
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" placeholder="••••••••" required>
                        </div>
                        <div class="mb-4">
                            <label for="rol" class="form-label">Tipo de cuenta / Rol *</label>
                            <select id="rol" name="rol" class="form-select" required>
                                <option value="cliente" selected>Cliente (Consumidor final)</option>
                                <option value="vendedor">Vendedor (Personal de ventas)</option>
                                <option value="admin">Administrador (Control total)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">+ Guardar usuario</button>
                    </form>
                </section>
            </div>

            <!-- Listado Segmentado por Roles con Buscador -->
            <div class="col-12 col-lg-8">
                <section class="seccion-card" aria-labelledby="titulo-segmentacion-usuarios">
                    
                    <!-- Barra de Búsqueda Instantánea -->
                    <div class="mb-3">
                        <input type="text" id="buscadorUsuarios" class="form-control" placeholder="🔍 Buscar por nombre, apellido, DNI o rol...">
                    </div>

                    <!-- Pestañas de Segmentación -->
                    <ul class="nav nav-tabs-app mb-4" id="usuariosSegmentacionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-clientes-btn" data-bs-toggle="tab" data-bs-target="#panel-clientes" type="button" role="tab" aria-controls="panel-clientes" aria-selected="true">
                                <span>🛒 Clientes</span>
                                <span class="badge rounded-pill text-bg-primary ms-1"><?= count($clientes) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-equipo-btn" data-bs-toggle="tab" data-bs-target="#panel-equipo" type="button" role="tab" aria-controls="panel-equipo" aria-selected="false">
                                <span>👔 Equipo (Vendedores y Admins)</span>
                                <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($equipo) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-todos-btn" data-bs-toggle="tab" data-bs-target="#panel-todos" type="button" role="tab" aria-controls="panel-todos" aria-selected="false">
                                <span>👥 Todos</span>
                                <span class="badge rounded-pill text-bg-light border ms-1"><?= count($usuarios) ?></span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="usuariosSegmentacionContent">
                        
                        <!-- Vista Exclusiva: Clientes -->
                        <div class="tab-pane fade show active" id="panel-clientes" role="tabpanel" aria-labelledby="tab-clientes-btn">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h6 fw-bold text-uppercase text-muted mb-0">Cartera de Clientes</h2>
                                <span class="text-secondary small"><?= count($clientes) ?> clientes registrados</span>
                            </div>
                            <?php if (empty($clientes)): ?>
                                <div class="empty-state">No hay clientes registrados en el sistema.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0 tabla-filtrable">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>DNI</th>
                                                <th>Fecha de Registro</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($clientes as $cliente): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($cliente["nombre"], 0, 1))) ?></span>
                                                        <div>
                                                            <div class="fw-semibold nombre-usuario"><?= e(trim($cliente["nombre"] . " " . ($cliente["apellido"] ?? ""))) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="dni-usuario"><?= e($cliente["dni"]) ?></td>
                                                <td><small class="text-muted"><?= e(date("d/m/Y", strtotime($cliente["fecha_registro"]))) ?></small></td>
                                                <td>
                                                    <?php if ((int)($cliente["activo"] ?? 1) === 1): ?>
                                                        <span class="badge text-bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-secondary">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Vista Exclusiva: Vendedores y Administradores -->
                        <div class="tab-pane fade" id="panel-equipo" role="tabpanel" aria-labelledby="tab-equipo-btn">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h6 fw-bold text-uppercase text-muted mb-0">Personal de Ventas y Administración</h2>
                                <span class="text-secondary small"><?= count($equipo) ?> miembros del equipo</span>
                            </div>
                            <?php if (empty($equipo)): ?>
                                <div class="empty-state">No hay miembros de equipo registrados.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0 tabla-filtrable">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>DNI</th>
                                                <th>Rol Asignado</th>
                                                <th>Fecha de Registro</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($equipo as $miembro): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="avatar bg-primary-subtle text-primary" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($miembro["nombre"], 0, 1))) ?></span>
                                                        <div>
                                                            <div class="fw-semibold nombre-usuario"><?= e(trim($miembro["nombre"] . " " . ($miembro["apellido"] ?? ""))) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="dni-usuario"><?= e($miembro["dni"]) ?></td>
                                                <td>
                                                    <?php if ($miembro["rol"] === "admin"): ?>
                                                        <span class="badge text-bg-danger rol-usuario">Administrador</span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-primary rol-usuario">Vendedor</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small class="text-muted"><?= e(date("d/m/Y", strtotime($miembro["fecha_registro"]))) ?></small></td>
                                                <td>
                                                    <?php if ((int)($miembro["activo"] ?? 1) === 1): ?>
                                                        <span class="badge text-bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-secondary">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Vista Consolidada: Todos -->
                        <div class="tab-pane fade" id="panel-todos" role="tabpanel" aria-labelledby="tab-todos-btn">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 tabla-filtrable">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>DNI</th>
                                            <th>Rol</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($usuario["nombre"], 0, 1))) ?></span>
                                                    <div>
                                                        <div class="fw-semibold nombre-usuario"><?= e(trim($usuario["nombre"] . " " . ($usuario["apellido"] ?? ""))) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="dni-usuario"><?= e($usuario["dni"]) ?></td>
                                            <td>
                                                <?php if ($usuario["rol"] === "admin"): ?>
                                                    <span class="badge text-bg-danger rol-usuario">Admin</span>
                                                <?php elseif ($usuario["rol"] === "vendedor"): ?>
                                                    <span class="badge text-bg-primary rol-usuario">Vendedor</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary rol-usuario">Cliente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?= e(date("d/m/Y", strtotime($usuario["fecha_registro"]))) ?></small></td>
                                            <td>
                                                <?php if ((int)($usuario["activo"] ?? 1) === 1): ?>
                                                    <span class="badge text-bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </section>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const buscador = document.getElementById("buscadorUsuarios");
        if (buscador) {
            buscador.addEventListener("input", (evento) => {
                const query = evento.target.value.toLowerCase().trim();
                const tablas = document.querySelectorAll(".tabla-filtrable tbody");
                tablas.forEach((tbody) => {
                    const filas = tbody.querySelectorAll("tr");
                    filas.forEach((fila) => {
                        const texto = fila.textContent.toLowerCase();
                        fila.style.display = texto.includes(query) ? "" : "none";
                    });
                });
            });
        }
    </script>
</body>
</html>
