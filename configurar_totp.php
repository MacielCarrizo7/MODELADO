<?php
require "seguridad.php";
require "FirestoreConexion.php";
require "totp_servicio.php";
iniciarSesionAplicacion();
header("Cache-Control: no-store");
header("Pragma: no-cache");

if (!sesionMfaPendienteValida()) {
    session_unset();
    header("Location: login.php?sesion_vencida=1");
    exit;
}

$firestore = FirestoreConexion::obtenerFirestore();
$usuarioId = (int) $_SESSION["auth_pending_user_id"];
$usuario = $firestore->obtenerDocumento("usuarios", (string)$usuarioId);

if (!$usuario) {
    session_unset();
    header("Location: login.php");
    exit;
}
if ((bool) ($usuario["totp_enabled"] ?? 0)) {
    $_SESSION["mfa_pending_enrollment"] = false;
    header("Location: verificar_totp.php");
    exit;
}

$error = "";
$secreto = "";
$qr = "";
try {
    if (empty($usuario["totp_secret_encrypted"])) {
        $secreto = servicioTotp()->createSecret();
        $protegido = cifrarSecretoTotp($secreto);
        $firestore->actualizarCampos("usuarios", (string)$usuarioId, [
            "totp_secret_encrypted" => $protegido
        ]);
    } else {
        $secreto = descifrarSecretoTotp($usuario["totp_secret_encrypted"]);
    }
    $etiqueta = trim(($usuario["nombre"] ?? "") . " " . ($usuario["apellido"] ?? "")) . " · DNI " . ($usuario["dni"] ?? "");
    $qr = servicioTotp()->getQRCodeImageAsDataUri($etiqueta, $secreto, 240);
} catch (Throwable $e) {
    error_log("Error al preparar TOTP en Firestore: " . $e->getMessage());
    $error = "No se pudo preparar el segundo factor. Contactá al administrador.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    $token = $_POST["csrf_token"] ?? "";
    $codigo = trim($_POST["codigo"] ?? "");
    if (!hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        $error = "La sesión del formulario venció. Actualizá la página.";
    } elseif (($espera = segundosBloqueoMfa()) > 0) {
        http_response_code(429);
        $error = "Demasiados intentos. Esperá {$espera} segundos.";
    } elseif (!preg_match('/^\d{6}$/', $codigo)) {
        registrarFalloMfa();
        $error = "Ingresá el código de 6 dígitos de tu aplicación.";
    } else {
        $timeslice = 0;
        $valido = servicioTotp()->verifyCode($secreto, $codigo, TOTP_DISCREPANCIA, null, $timeslice);
        if (!$valido) {
            registrarFalloMfa();
            $error = "El código de verificación no es válido o ha vencido.";
        } else {
            try {
                $actual = $firestore->obtenerDocumento("usuarios", (string)$usuarioId);
                $ultimo = isset($actual["totp_last_timeslice"]) ? (int) $actual["totp_last_timeslice"] : 0;
                if (!$actual || (bool) ($actual["totp_enabled"] ?? 0) || $timeslice <= $ultimo) {
                    throw new RuntimeException("Código TOTP repetido o estado inválido.");
                }

                $firestore->actualizarCampos("usuarios", (string)$usuarioId, [
                    "totp_enabled" => 1,
                    "totp_confirmed_at" => date("Y-m-d H:i:s"),
                    "totp_last_timeslice" => (int) $timeslice
                ]);

                $actual["totp_enabled"] = 1;
                finalizarSesionMfa($actual);
                header("Location: stock.php?mfa_configurado=1");
                exit;
            } catch (Throwable $e) {
                error_log("Error al confirmar TOTP en Firestore: " . $e->getMessage());
                registrarFalloMfa();
                $error = "El código ya fue usado o no pudo confirmarse. Esperá uno nuevo.";
            }
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
    <title>Configurar verificación | Control Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/estilos.css" rel="stylesheet">
</head>
<body class="login-body">
<main class="container py-4">
    <section class="login-card mx-auto" style="max-width: 540px">
        <p class="etiqueta text-primary mb-2">Protegé tu cuenta</p>
        <h1 class="h2 fw-bold">Configurar verificación en dos pasos</h1>
        <p class="texto-secundario">Escaneá este QR con Google Authenticator, Microsoft Authenticator, Authy u otra aplicación TOTP. Luego ingresá el código generado.</p>
        <?php if ($error !== ""): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($qr !== ""): ?>
            <div class="text-center bg-white border rounded-4 p-3 mb-4">
                <img src="<?= e($qr) ?>" width="240" height="240" alt="Código QR para configurar el segundo factor">
            </div>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <label for="codigo" class="form-label">Código de 6 dígitos</label>
                <input id="codigo" name="codigo" class="form-control form-control-lg text-center mb-3" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
                <button class="btn btn-primary btn-lg w-100" type="submit">Activar y continuar</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-link w-100 mt-3" href="logout.php">Volver al inicio</a>
    </section>
</main>
</body>
</html>
