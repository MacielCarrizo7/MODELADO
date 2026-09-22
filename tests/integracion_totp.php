<?php

declare(strict_types=1);

require __DIR__ . "/../conexion.php";
require __DIR__ . "/../totp_servicio.php";

$baseUrl = rtrim($argv[1] ?? "http://127.0.0.1:8099", "/");
$conexion = Conexion::obtenerInstancia();
$marca = "TOTPTEST" . bin2hex(random_bytes(4));
$password = "PruebaT0tp!";
$usuarios = [];
$productoPruebaId = 0;
$ventaPruebaId = 0;

function comprobar(bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new RuntimeException("FALLÓ: " . $mensaje);
    }
    echo "OK: {$mensaje}\n";
}

function solicitud(string $baseUrl, string $ruta, string $cookie, array $post = [], array $headers = []): array {
    static $conexiones = [];
    static $sesiones = [];
    if (!isset($conexiones[$cookie])) {
        $conexiones[$cookie] = curl_init();
        curl_setopt($conexiones[$cookie], CURLOPT_COOKIEFILE, "");
    }
    $curl = $conexiones[$cookie];
    $opciones = [
        CURLOPT_URL => $baseUrl . "/" . ltrim($ruta, "/"),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIE => isset($sesiones[$cookie]) ? "PHPSESSID=" . $sesiones[$cookie] : "",
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($post !== []) {
        $opciones[CURLOPT_POST] = true;
        $opciones[CURLOPT_POSTFIELDS] = http_build_query($post);
    } else {
        $opciones[CURLOPT_HTTPGET] = true;
    }
    curl_setopt_array($curl, $opciones);
    $respuesta = curl_exec($curl);
    if ($respuesta === false) {
        throw new RuntimeException("Error HTTP: " . curl_error($curl));
    }
    $cabeceraTamano = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $cabeceras = substr($respuesta, 0, $cabeceraTamano);
    if (preg_match_all('/^Set-Cookie:\s*PHPSESSID=([^;]*)/mi', $cabeceras, $coincide) && $coincide[1] !== []) {
        $sesiones[$cookie] = (string) end($coincide[1]);
    }
    return [
        "status" => $status,
        "headers" => $cabeceras,
        "body" => substr($respuesta, $cabeceraTamano),
    ];
}

function ubicacion(array $respuesta): string {
    preg_match('/^Location:\s*(.+)$/mi', $respuesta["headers"], $coincide);
    return trim($coincide[1] ?? "");
}

function csrf(string $html): string {
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $coincide);
    if (empty($coincide[1])) {
        throw new RuntimeException("No se encontró el token CSRF.");
    }
    return $coincide[1];
}

function ultimoCsrf(string $html): string {
    preg_match_all('/name="csrf_token" value="([a-f0-9]+)"/', $html, $coincide);
    if (empty($coincide[1])) {
        throw new RuntimeException("No se encontró el token CSRF.");
    }
    return (string) end($coincide[1]);
}

function csrfPagina(string $html): string {
    preg_match('/data-csrf="([a-f0-9]+)"/', $html, $coincide);
    if (empty($coincide[1])) {
        throw new RuntimeException("No se encontró el token CSRF de la página.");
    }
    return $coincide[1];
}

function autenticarPrimerFactor(string $baseUrl, string $cookie, array $usuario, string $password): array {
    return solicitud($baseUrl, "login.php", $cookie, [
        "dni" => $usuario["dni"],
        "nombre" => $usuario["nombre"],
        "apellido" => $usuario["apellido"],
        "password" => $password,
    ]);
}

try {
    foreach (["admin", "vendedor", "cliente"] as $indice => $rol) {
        $dni = $marca . ($indice + 1);
        $nombre = "Prueba";
        $apellido = ucfirst($rol);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare(
            "INSERT INTO usuarios (dni, nombre, apellido, password, rol) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssss", $dni, $nombre, $apellido, $hash, $rol);
        $stmt->execute();
        $usuarios[$rol] = [
            "id" => $conexion->insert_id,
            "dni" => $dni,
            "nombre" => $nombre,
            "apellido" => $apellido,
            "cookie" => tempnam(sys_get_temp_dir(), "totp_cookie_"),
        ];
    }

    $paneles = ["admin" => "admin.php", "vendedor" => "vendedor.php", "cliente" => "cliente.php"];
    $secretos = [];
    $codigosIniciales = [];

    foreach ($usuarios as $rol => $usuario) {
        $login = autenticarPrimerFactor($baseUrl, $usuario["cookie"], $usuario, $password);
        comprobar($login["status"] === 302 && ubicacion($login) === "configurar_totp.php", "{$rol}: primer factor conduce al enrolamiento");

        $bypass = solicitud($baseUrl, $paneles[$rol], $usuario["cookie"]);
        comprobar(
            $bypass["status"] === 302 && ubicacion($bypass) === "configurar_totp.php",
            "{$rol}: el panel bloquea una sesión sin MFA (HTTP {$bypass['status']}, destino " . ubicacion($bypass) . ")"
        );
        $apiBypass = solicitud($baseUrl, "obtener_productos.php", $usuario["cookie"]);
        comprobar($apiBypass["status"] === 401, "{$rol}: la API bloquea una sesión sin MFA");

        $configuracion = solicitud($baseUrl, "configurar_totp.php", $usuario["cookie"]);
        comprobar($configuracion["status"] === 200 && str_contains($configuracion["body"], "data:image/svg+xml;base64,"), "{$rol}: QR local disponible tras el primer factor");

        $stmt = $conexion->prepare("SELECT totp_secret_encrypted FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario["id"]);
        $stmt->execute();
        $envoltorio = $stmt->get_result()->fetch_assoc()["totp_secret_encrypted"];
        comprobar(is_string($envoltorio) && !str_contains($envoltorio, "otpauth://"), "{$rol}: el secreto persiste cifrado");
        $secretos[$rol] = descifrarSecretoTotp($envoltorio);
        $codigosIniciales[$rol] = servicioTotp()->getCode($secretos[$rol]);

        $incorrectoEnrolamiento = $codigosIniciales[$rol] === "000000" ? "999999" : "000000";
        $rechazado = solicitud($baseUrl, "configurar_totp.php", $usuario["cookie"], [
            "csrf_token" => csrf($configuracion["body"]),
            "codigo" => $incorrectoEnrolamiento,
        ]);
        comprobar($rechazado["status"] === 200 && str_contains($rechazado["body"], "no es válido"), "{$rol}: código incorrecto de enrolamiento rechazado");

        $activar = solicitud($baseUrl, "configurar_totp.php", $usuario["cookie"], [
            "csrf_token" => csrf($rechazado["body"]),
            "codigo" => $codigosIniciales[$rol],
        ]);
        comprobar($activar["status"] === 302 && str_starts_with(ubicacion($activar), "stock.php"), "{$rol}: TOTP válido completa la sesión");

        $stock = solicitud($baseUrl, "stock.php", $usuario["cookie"]);
        comprobar($stock["status"] === 302 && ubicacion($stock) === $paneles[$rol], "{$rol}: redirección al panel correcto");
        $propio = solicitud($baseUrl, $paneles[$rol], $usuario["cookie"]);
        comprobar($propio["status"] === 200, "{$rol}: acceso al panel propio");
        $panelAjeno = $rol === "admin" ? "vendedor.php" : "admin.php";
        $ajeno = solicitud($baseUrl, $panelAjeno, $usuario["cookie"]);
        comprobar($ajeno["status"] === 403, "{$rol}: un rol no puede abrir otro panel");
    }

    $admin = $usuarios["admin"];
    solicitud($baseUrl, "logout.php", $admin["cookie"]);
    $loginAdmin = autenticarPrimerFactor($baseUrl, $admin["cookie"], $admin, $password);
    comprobar(ubicacion($loginAdmin) === "verificar_totp.php", "cuenta configurada solicita verificación, no otro QR");
    $verificacion = solicitud($baseUrl, "verificar_totp.php", $admin["cookie"]);
    $repetido = solicitud($baseUrl, "verificar_totp.php", $admin["cookie"], [
        "csrf_token" => csrf($verificacion["body"]),
        "codigo" => $codigosIniciales["admin"],
    ]);
    comprobar($repetido["status"] === 200 && str_contains($repetido["body"], "ya fue usado"), "un código aceptado no puede reutilizarse");

    $codigoSiguiente = servicioTotp()->getCode($secretos["admin"], time() + 30);
    $entrarAdmin = solicitud($baseUrl, "verificar_totp.php", $admin["cookie"], [
        "csrf_token" => csrf($repetido["body"]),
        "codigo" => $codigoSiguiente,
    ]);
    comprobar($entrarAdmin["status"] === 302 && ubicacion($entrarAdmin) === "stock.php", "una ventana TOTP posterior permite continuar");

    $panelAdmin = solicitud($baseUrl, "admin.php", $admin["cookie"]);
    $csrfAdmin = csrfPagina($panelAdmin["body"]);
    $sinCsrf = solicitud($baseUrl, "guardar_producto.php", $admin["cookie"], [
        "nombre" => "Sin token", "precio" => "1", "stock" => "1",
    ]);
    comprobar($sinCsrf["status"] === 403, "las escrituras de API conservan protección CSRF");

    $nombreProducto = "Árbol de prueba {$marca}";
    $crearProducto = solicitud($baseUrl, "guardar_producto.php", $admin["cookie"], [
        "nombre" => $nombreProducto, "precio" => "125.50", "stock" => "10",
    ], ["X-CSRF-Token: {$csrfAdmin}"]);
    comprobar($crearProducto["status"] === 201, "productos acepta y conserva texto UTF-8");
    $stmt = $conexion->prepare("SELECT id, stock FROM productos WHERE nombre = ? LIMIT 1");
    $stmt->bind_param("s", $nombreProducto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $productoPruebaId = (int) $producto["id"];

    $clientes = solicitud($baseUrl, "obtener_clientes.php", $admin["cookie"]);
    comprobar($clientes["status"] === 200 && str_contains($clientes["body"], $usuarios["cliente"]["dni"]), "consulta de clientes sigue operativa");
    $venta = solicitud($baseUrl, "guardar_venta.php", $admin["cookie"], [
        "producto_id" => (string) $productoPruebaId,
        "cliente_id" => (string) $usuarios["cliente"]["id"],
        "cantidad" => "2",
    ], ["X-CSRF-Token: {$csrfAdmin}"]);
    $ventaJson = json_decode($venta["body"], true);
    $ventaPruebaId = (int) ($ventaJson["venta_id"] ?? 0);
    comprobar($venta["status"] === 201 && $ventaPruebaId > 0, "registro de ventas sigue operativo");

    $modificar = solicitud($baseUrl, "modificar_venta.php", $admin["cookie"], [
        "venta_id" => (string) $ventaPruebaId,
        "accion" => "modificar_cantidad",
        "cantidad" => "3",
        "motivo" => "Regresión TOTP",
    ], ["X-CSRF-Token: {$csrfAdmin}"]);
    comprobar($modificar["status"] === 200 && str_contains($modificar["body"], "MODIFICADA"), "modificación de cantidad sigue operativa");

    $hoy = date("Y-m-d");
    $filtro = solicitud(
        $baseUrl,
        "obtener_ventas.php?desde={$hoy}&hasta={$hoy}&producto_id={$productoPruebaId}&estado=MODIFICADA",
        $admin["cookie"]
    );
    comprobar($filtro["status"] === 200 && str_contains($filtro["body"], (string) $ventaPruebaId), "filtros de ventas siguen operativos");
    $historial = solicitud($baseUrl, "obtener_historial_venta.php?venta_id={$ventaPruebaId}", $admin["cookie"]);
    $historialJson = json_decode($historial["body"], true);
    comprobar($historial["status"] === 200 && count($historialJson) >= 2, "historial y auditoría registran la modificación");

    $cancelar = solicitud($baseUrl, "modificar_venta.php", $admin["cookie"], [
        "venta_id" => (string) $ventaPruebaId,
        "accion" => "cancelar",
        "motivo" => "Cancelación de regresión",
    ], ["X-CSRF-Token: {$csrfAdmin}"]);
    comprobar($cancelar["status"] === 200 && str_contains($cancelar["body"], "CANCELADA"), "cancelaciones siguen operativas");
    $stmt = $conexion->prepare("SELECT stock FROM productos WHERE id = ?");
    $stmt->bind_param("i", $productoPruebaId);
    $stmt->execute();
    $stockFinal = (int) $stmt->get_result()->fetch_assoc()["stock"];
    comprobar($stockFinal === 10, "la cancelación restituye correctamente el stock");
    $stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM venta_historial WHERE venta_id = ?");
    $stmt->bind_param("i", $ventaPruebaId);
    $stmt->execute();
    comprobar((int) $stmt->get_result()->fetch_assoc()["total"] >= 3, "auditoría conserva creación, modificación y cancelación");

    $registroCrear = solicitud($baseUrl, "registro.php", $admin["cookie"]);
    $dniUi = $marca . "UI";
    $crearUsuario = solicitud($baseUrl, "registro.php", $admin["cookie"], [
        "csrf_token" => csrf($registroCrear["body"]),
        "dni" => $dniUi,
        "nombre" => "Creación",
        "apellido" => "Panel",
        "password" => $password,
        "rol" => "cliente",
    ]);
    comprobar($crearUsuario["status"] === 302 && str_contains(ubicacion($crearUsuario), "creado=1"), "creación de usuarios por admin sigue operativa");
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE dni = ?");
    $stmt->bind_param("s", $dniUi);
    $stmt->execute();
    $usuarioUi = $stmt->get_result()->fetch_assoc();
    $usuarios["creado_ui"] = ["id" => (int) $usuarioUi["id"], "dni" => $dniUi, "cookie" => ""];

    $registro = solicitud($baseUrl, "registro.php", $admin["cookie"]);
    $reset = solicitud($baseUrl, "restablecer_totp.php", $admin["cookie"], [
        "csrf_token" => ultimoCsrf($registro["body"]),
        "usuario_id" => (string) $usuarios["vendedor"]["id"],
    ]);
    comprobar($reset["status"] === 302, "administrador puede restablecer 2FA (HTTP {$reset['status']})");
    $stmt = $conexion->prepare(
        "SELECT totp_enabled, totp_secret_encrypted, totp_last_timeslice FROM usuarios WHERE id = ?"
    );
    $stmt->bind_param("i", $usuarios["vendedor"]["id"]);
    $stmt->execute();
    $reiniciado = $stmt->get_result()->fetch_assoc();
    comprobar((int) $reiniciado["totp_enabled"] === 0 && $reiniciado["totp_secret_encrypted"] === null && $reiniciado["totp_last_timeslice"] === null, "el restablecimiento elimina estado y secreto cifrado");

    $cliente = $usuarios["cliente"];
    solicitud($baseUrl, "logout.php", $cliente["cookie"]);
    autenticarPrimerFactor($baseUrl, $cliente["cookie"], $cliente, $password);
    $verCliente = solicitud($baseUrl, "verificar_totp.php", $cliente["cookie"]);
    $tokenCliente = csrf($verCliente["body"]);
    $incorrecto = servicioTotp()->getCode($secretos["cliente"]) === "000000" ? "999999" : "000000";
    for ($i = 0; $i < 5; $i++) {
        solicitud($baseUrl, "verificar_totp.php", $cliente["cookie"], ["csrf_token" => $tokenCliente, "codigo" => $incorrecto]);
    }
    $limitado = solicitud($baseUrl, "verificar_totp.php", $cliente["cookie"], ["csrf_token" => $tokenCliente, "codigo" => $incorrecto]);
    comprobar($limitado["status"] === 429 && str_contains($limitado["body"], "Demasiados intentos"), "cinco fallos activan el límite temporal");

    solicitud($baseUrl, "logout.php", $admin["cookie"]);
    $sinSesion = solicitud($baseUrl, "admin.php", $admin["cookie"]);
    comprobar($sinSesion["status"] === 302 && ubicacion($sinSesion) === "login.php", "logout elimina la sesión completa");
} finally {
    if ($ventaPruebaId > 0) {
        $conexion->query("DELETE FROM venta_historial WHERE venta_id = {$ventaPruebaId}");
        $conexion->query("DELETE FROM ventas WHERE id = {$ventaPruebaId}");
    }
    if ($productoPruebaId > 0) {
        $conexion->query("DELETE FROM productos WHERE id = {$productoPruebaId}");
    }
    if ($usuarios !== []) {
        $ids = array_map(static fn(array $usuario): int => (int) $usuario["id"], $usuarios);
        $lista = implode(",", $ids);
        $conexion->query("DELETE FROM usuarios WHERE id IN ({$lista}) AND dni LIKE 'TOTPTEST%'");
        foreach ($usuarios as $usuario) {
            if (!empty($usuario["cookie"]) && is_string($usuario["cookie"]) && file_exists($usuario["cookie"])) {
                unlink($usuario["cookie"]);
            }
        }
    }
}

echo "Integración TOTP completada correctamente.\n";
