<?php

require_once __DIR__ . "/vendor/autoload.php";

use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

const TOTP_ENV_KEY = "CONTROL_STOCK_TOTP_KEY";
const TOTP_DISCREPANCIA = 1;
const MFA_MAX_INTENTOS = 5;
const MFA_BLOQUEO_SEGUNDOS = 60;
const MFA_SESION_PENDIENTE_SEGUNDOS = 600;

function servicioTotp(): TwoFactorAuth {
    static $servicio = null;
    if ($servicio === null) {
        $qr = new BaconQrCodeProvider(format: "svg");
        $servicio = new TwoFactorAuth($qr, "Control Stock");
    }
    return $servicio;
}

function claveCifradoTotp(): string {
    $valor = getenv(TOTP_ENV_KEY) ?: ($_ENV[TOTP_ENV_KEY] ?? null);
    if (!is_string($valor) || $valor === "") {
        $archivoClave = __DIR__ . "/.totp_key";
        if (file_exists($archivoClave)) {
            $valor = trim((string) file_get_contents($archivoClave));
        } else {
            $nuevaClaveBase64 = base64_encode(random_bytes(32));
            @file_put_contents($archivoClave, $nuevaClaveBase64);
            $valor = $nuevaClaveBase64;
        }
    }
    $clave = is_string($valor) ? base64_decode($valor, true) : false;
    if ($clave === false || strlen($clave) !== 32) {
        throw new RuntimeException(
            "El segundo factor no está configurado. Definí " . TOTP_ENV_KEY . " con 32 bytes codificados en Base64."
        );
    }
    return $clave;
}

function cifrarSecretoTotp(string $secreto): string {
    $iv = random_bytes(12);
    $tag = "";
    $cifrado = openssl_encrypt(
        $secreto,
        "aes-256-gcm",
        claveCifradoTotp(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        "control-stock:totp:v1",
        16
    );
    if ($cifrado === false) {
        throw new RuntimeException("No se pudo proteger el secreto del segundo factor.");
    }
    return base64_encode("\x01" . $iv . $tag . $cifrado);
}

function descifrarSecretoTotp(string $envoltorio): string {
    $datos = base64_decode($envoltorio, true);
    if ($datos === false || strlen($datos) < 30 || ord($datos[0]) !== 1) {
        throw new RuntimeException("El secreto del segundo factor no tiene un formato válido.");
    }
    $iv = substr($datos, 1, 12);
    $tag = substr($datos, 13, 16);
    $cifrado = substr($datos, 29);
    $secreto = openssl_decrypt(
        $cifrado,
        "aes-256-gcm",
        claveCifradoTotp(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        "control-stock:totp:v1"
    );
    if ($secreto === false || $secreto === "") {
        throw new RuntimeException("No se pudo recuperar el secreto del segundo factor.");
    }
    return $secreto;
}

function iniciarSesionPendiente(array $usuario, bool $requiereEnrolamiento): void {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION["auth_pending_user_id"] = (int) $usuario["id"];
    $_SESSION["mfa_pending_started_at"] = time();
    $_SESSION["mfa_pending_enrollment"] = $requiereEnrolamiento;
    $_SESSION["mfa_attempts"] = 0;
    $_SESSION["mfa_lock_until"] = 0;
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function finalizarSesionMfa(array $usuario): void {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION["usuario_id"] = (int) $usuario["id"];
    $_SESSION["usuario_dni"] = $usuario["dni"];
    $_SESSION["usuario_nombre"] = $usuario["nombre"];
    $_SESSION["usuario_apellido"] = $usuario["apellido"];
    $_SESSION["usuario_rol"] = $usuario["rol"];
    $_SESSION["mfa_verified"] = true;
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function sesionMfaPendienteValida(): bool {
    $inicio = (int) ($_SESSION["mfa_pending_started_at"] ?? 0);
    return isset($_SESSION["auth_pending_user_id"])
        && $inicio > 0
        && time() - $inicio <= MFA_SESION_PENDIENTE_SEGUNDOS;
}

function segundosBloqueoMfa(): int {
    return max(0, (int) ($_SESSION["mfa_lock_until"] ?? 0) - time());
}

function registrarFalloMfa(): int {
    $intentos = (int) ($_SESSION["mfa_attempts"] ?? 0) + 1;
    $_SESSION["mfa_attempts"] = $intentos;
    if ($intentos >= MFA_MAX_INTENTOS) {
        $_SESSION["mfa_lock_until"] = time() + MFA_BLOQUEO_SEGUNDOS;
        $_SESSION["mfa_attempts"] = 0;
    }
    return $intentos;
}

