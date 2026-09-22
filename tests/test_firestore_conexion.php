<?php
/**
 * Test de Conexión y Operaciones Básicas en Firebase Firestore
 */

require_once __DIR__ . "/../FirestoreConexion.php";

echo "=== TEST CONEXION FIREBASE FIRESTORE ===" . PHP_EOL;

try {
    echo "1. Resolviendo credenciales..." . PHP_EOL;
    $ruta = FirestoreConexion::obtenerRutaCredenciales();
    echo "   Ruta encontrada: {$ruta}" . PHP_EOL;

    echo "2. Obteniendo instancia de FirestoreRestCliente..." . PHP_EOL;
    $firestore = FirestoreConexion::obtenerFirestore();
    $token = $firestore->obtenerToken();
    echo "   Token OAuth2 generado correctamente (" . substr($token, 0, 15) . "...)" . PHP_EOL;

    echo "3. Probando lectura del documento contadores/usuarios..." . PHP_EOL;
    $contador = $firestore->obtenerDocumento("contadores", "usuarios");
    if ($contador === null) {
        echo "   Documento no existe. Inicializando contador en 0..." . PHP_EOL;
        $firestore->guardarDocumento("contadores", "usuarios", [
            "ultimo_id" => 0,
            "creado_el" => date("Y-m-d H:i:s")
        ]);
        echo "   Contador inicializado." . PHP_EOL;
    } else {
        echo "   Contador existente. ultimo_id: " . ($contador["ultimo_id"] ?? "0") . PHP_EOL;
    }

    echo "4. Probando conteo y consulta en coleccion usuarios..." . PHP_EOL;
    $totalUsuarios = $firestore->contarDocumentos("usuarios");
    echo "   Total de documentos en 'usuarios': {$totalUsuarios}" . PHP_EOL;

    $usuarios = $firestore->consultar("usuarios", [], "fecha_registro", "DESC", 5);
    echo "   Consulta de usuarios retornó " . count($usuarios) . " registros." . PHP_EOL;
    foreach ($usuarios as $u) {
        echo "   - ID: " . ($u["id"] ?? $u["_id"]) . " | DNI: " . ($u["dni"] ?? "") . " | Nombre: " . ($u["nombre"] ?? "") . " " . ($u["apellido"] ?? "") . " | Rol: " . ($u["rol"] ?? "") . PHP_EOL;
    }

    echo "=== TEST FINALIZADO CON EXITO ===" . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR EN TEST: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
