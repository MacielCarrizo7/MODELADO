<?php
/**
 * Test de Integración Completo: Firestore + Auth + TOTP + Relaciones
 */

require_once __DIR__ . "/../FirestoreConexion.php";
require_once __DIR__ . "/../totp_servicio.php";

echo "==================================================" . PHP_EOL;
echo " INICIANDO TEST DE INTEGRACION COMPLETO FIRESTORE" . PHP_EOL;
echo "==================================================" . PHP_EOL;

$firestore = FirestoreConexion::obtenerFirestore();

function asserTrue($condicion, $mensaje) {
    if (!$condicion) {
        echo " [FAIL] {$mensaje}" . PHP_EOL;
        throw new RuntimeException("Assertion failed: {$mensaje}");
    }
    echo " [PASS] {$mensaje}" . PHP_EOL;
}

try {
    // 1. Verificar lectura de admin
    $adminDoc = $firestore->obtenerDocumento("usuarios", "1");
    asserTrue($adminDoc !== null, "Usuario Admin ID 1 existe en Firestore");
    asserTrue($adminDoc["dni"] === "12345678", "Admin DNI es 12345678");
    asserTrue($adminDoc["rol"] === "admin", "Admin rol es admin");
    asserTrue(password_verify("admin123", $adminDoc["password"]), "Password verify de admin funciona con bcrypt");

    // 2. Probar creación atómica de un nuevo cliente con contador
    $nuevoId = FirestoreConexion::obtenerSiguienteIdUsuario();
    echo "   Nuevo ID generado para cliente de prueba: {$nuevoId}" . PHP_EOL;
    asserTrue($nuevoId > 1, "ID generado es mayor a 1");

    $testDni = "TEST" . time();
    $testPass = "clavePrueba123";
    $testUsuario = [
        "id" => $nuevoId,
        "dni" => $testDni,
        "nombre" => "Carlos",
        "apellido" => "Prueba",
        "password" => password_hash($testPass, PASSWORD_DEFAULT),
        "rol" => "cliente",
        "activo" => 1,
        "fecha_registro" => date("Y-m-d H:i:s"),
        "totp_secret_encrypted" => null,
        "totp_enabled" => 0,
        "totp_confirmed_at" => null,
        "totp_last_timeslice" => null
    ];
    $firestore->guardarDocumento("usuarios", (string)$nuevoId, $testUsuario, true);

    // 3. Probar lectura del nuevo usuario
    $leido = $firestore->obtenerDocumento("usuarios", (string)$nuevoId);
    asserTrue($leido !== null, "Nuevo usuario se recupera por ID de documento");
    asserTrue($leido["nombre"] === "Carlos", "Nombre coincide");
    asserTrue(password_verify($testPass, $leido["password"]), "Password del nuevo usuario verifica correctamente");

    // 4. Probar búsqueda por consulta estructurada (login simulation)
    $candidatos = $firestore->consultar("usuarios", [
        ["dni", "==", $testDni],
        ["nombre", "==", "Carlos"]
    ]);
    asserTrue(count($candidatos) === 1, "Consulta estructurada por DNI y Nombre encuentra exactamente 1 usuario");
    asserTrue($candidatos[0]["id"] === $nuevoId, "ID del candidato coincide");

    // 5. Probar flujo TOTP (creación de secreto, cifrado, verificación)
    $secreto = servicioTotp()->createSecret();
    $cifrado = cifrarSecretoTotp($secreto);
    $firestore->actualizarCampos("usuarios", (string)$nuevoId, [
        "totp_secret_encrypted" => $cifrado
    ]);

    $docTotp = $firestore->obtenerDocumento("usuarios", (string)$nuevoId);
    $descifrado = descifrarSecretoTotp($docTotp["totp_secret_encrypted"]);
    asserTrue($descifrado === $secreto, "Secreto TOTP cifrado y descifrado coincide");

    $codigo = servicioTotp()->getCode($secreto);
    $timeslice = 0;
    $valido = servicioTotp()->verifyCode($descifrado, $codigo, 1, null, $timeslice);
    asserTrue($valido === true, "Código TOTP generado es validado exitosamente");

    // Confirmar activación TOTP
    $firestore->actualizarCampos("usuarios", (string)$nuevoId, [
        "totp_enabled" => 1,
        "totp_confirmed_at" => date("Y-m-d H:i:s"),
        "totp_last_timeslice" => $timeslice
    ]);
    $docTotpActivo = $firestore->obtenerDocumento("usuarios", (string)$nuevoId);
    asserTrue((int)$docTotpActivo["totp_enabled"] === 1, "TOTP habilitado en Firestore");

    // Probar restablecimiento TOTP
    $firestore->actualizarCampos("usuarios", (string)$nuevoId, [
        "totp_secret_encrypted" => null,
        "totp_enabled" => 0,
        "totp_confirmed_at" => null,
        "totp_last_timeslice" => null
    ]);
    $docTotpReset = $firestore->obtenerDocumento("usuarios", (string)$nuevoId);
    asserTrue((int)$docTotpReset["totp_enabled"] === 0, "TOTP deshabilitado tras restablecimiento");
    asserTrue($docTotpReset["totp_secret_encrypted"] === null, "Secreto TOTP eliminado");

    // 6. Probar conteo total de usuarios (dashboard admin)
    $total = $firestore->contarDocumentos("usuarios");
    asserTrue($total >= 2, "Conteo de usuarios en Firestore es >= 2 (Admin + Test)");

    // 7. Probar consulta de clientes y vendedores
    $clientes = $firestore->consultar("usuarios", [
        ["rol", "==", "cliente"],
        ["activo", "==", 1]
    ]);
    asserTrue(count($clientes) >= 1, "Consulta de clientes activos retorna al menos 1");

    $admins = $firestore->consultar("usuarios", [
        ["rol", "==", "admin"],
        ["activo", "==", 1]
    ]);
    asserTrue(count($admins) >= 1, "Consulta de admins activos retorna al menos 1");

    // 8. Limpiar usuario de prueba
    $firestore->eliminarDocumento("usuarios", (string)$nuevoId);
    $eliminado = $firestore->obtenerDocumento("usuarios", (string)$nuevoId);
    asserTrue($eliminado === null, "Usuario de prueba eliminado correctamente de Firestore");

    echo "==================================================" . PHP_EOL;
    echo " TODOS LOS TESTS PASARON EXITOSAMENTE (100%)" . PHP_EOL;
    echo "==================================================" . PHP_EOL;

} catch (Throwable $e) {
    echo "ERROR FATAL: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
