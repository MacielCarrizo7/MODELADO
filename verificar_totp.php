<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
require_once __DIR__ . "/totp_servicio.php";
iniciarSesionAplicacion();
header("Cache-Control: no-store");
header("Pragma: no-cache");

if (autenticacionCompleta()) {
    header("Location: stock.php");
    exit;
}
if (!sesionMfaPendienteValida()) {
    session_unset();
    header("Location: login.php?sesion_vencida=1");
    exit;
}

$firestore = FirestoreConexion::obtenerFirestore();
$usuarioId = (int) $_SESSION["auth_pending_user_id"];
$usuario = $firestore->obtenerDocumento("usuarios", (string)$usuarioId);

if (!$usuario || !(bool) ($usuario["totp_enabled"] ?? 0) || empty($usuario["totp_secret_encrypted"])) {
    $_SESSION["mfa_pending_enrollment"] = true;
    header("Location: configurar_totp.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $codigo = trim($_POST["codigo"] ?? "");
    if (!hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        $error = "La sesión del formulario venció. Actualizá la página.";
    } elseif (($espera = segundosBloqueoMfa()) > 0) {
        http_response_code(429);
        $error = "Demasiados intentos. Esperá {$espera} segundos.";
    } elseif (!preg_match('/^\d{6}$/', $codigo)) {
        registrarFalloMfa();
        $error = "Ingresá un código válido de 6 dígitos.";
    } else {
        try {
            $secreto = descifrarSecretoTotp($usuario["totp_secret_encrypted"]);
            $timeslice = 0;
            $valido = servicioTotp()->verifyCode($secreto, $codigo, TOTP_DISCREPANCIA, null, $timeslice);
            if (!$valido) {
                registrarFalloMfa();
                $error = "El código de verificación no es válido o ha vencido.";
            } else {
                $actual = $firestore->obtenerDocumento("usuarios", (string)$usuarioId);
                $ultimo = isset($actual["totp_last_timeslice"]) ? (int) $actual["totp_last_timeslice"] : 0;
                if (!$actual || !(bool) ($actual["totp_enabled"] ?? 0) || $timeslice <= $ultimo) {
                    throw new RuntimeException("Código TOTP repetido o estado inválido.");
                }

                $firestore->actualizarCampos("usuarios", (string)$usuarioId, [
                    "totp_last_timeslice" => (int) $timeslice
                ]);

                finalizarSesionMfa($actual);
                header("Location: stock.php");
                exit;
            }
        } catch (Throwable $e) {
            error_log("Error al verificar TOTP en Firestore: " . $e->getMessage());
            registrarFalloMfa();
            $error = "El código ya fue usado o no pudo verificarse. Esperá uno nuevo.";
        }
    }
}

function e(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, "UTF-8"); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar acceso | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body class="login-body">
<main class="container py-4">
    <section class="login-card mx-auto" style="max-width: 480px">
        <p class="etiqueta text-primary mb-2">Segundo paso</p>
        <h1 class="h2 fw-bold">Verificá tu identidad</h1>
        <p class="texto-secundario mb-4">Ingresá el código actual de 6 dígitos de tu aplicación de autenticación.</p>
        <?php if ($error !== ""): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
            <label for="codigo" class="form-label">Código de seguridad</label>
            <input id="codigo" name="codigo" class="form-control form-control-lg text-center mb-3" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button class="btn btn-primary btn-lg w-100" type="submit">Verificar y entrar</button>
        </form>
        <a class="btn btn-link w-100 mt-3" href="logout.php">Usar otra cuenta</a>
    </section>
</main>
</body>
</html>
