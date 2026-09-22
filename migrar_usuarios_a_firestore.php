<?php
/**
 * Script de Migración Inicial:
 * 1. Lee los usuarios de MySQL (si MySQL está disponible) y los inserta en Firestore
 * 2. Si no hay usuarios o MySQL no está activo, siembra el usuario Admin inicial en Firestore
 * 3. Actualiza contadores/usuarios con el mayor ID encontrado
 */

require_once __DIR__ . "/FirestoreConexion.php";

echo "=== INICIANDO MIGRACION / SEMILLA DE USUARIOS A FIRESTORE ===" . PHP_EOL;

$firestore = FirestoreConexion::obtenerFirestore();
$usuariosMysql = [];
$pdo = null;

// Intentar leer MySQL directamente
try {
    $dsn = "mysql:host=localhost;dbname=control_stock;charset=utf8mb4";
    $pdo = new PDO($dsn, "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ]);
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id ASC");
    $usuariosMysql = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Usuarios encontrados en MySQL: " . count($usuariosMysql) . PHP_EOL;
} catch (Throwable $e) {
    echo "Aviso: MySQL no está accesible ({$e->getMessage()}). Sembrando Firestore directamente." . PHP_EOL;
}

$maxId = 0;
$migrados = 0;

try {
    if (!empty($usuariosMysql)) {
        foreach ($usuariosMysql as $u) {
            $idInt = (int) $u["id"];
            if ($idInt > $maxId) {
                $maxId = $idInt;
            }

            $docData = [
                "id" => $idInt,
                "dni" => (string) $u["dni"],
                "nombre" => (string) $u["nombre"],
                "apellido" => (string) ($u["apellido"] ?? ""),
                "password" => (string) $u["password"],
                "rol" => (string) ($u["rol"] ?? "cliente"),
                "activo" => isset($u["activo"]) ? (int) $u["activo"] : 1,
                "fecha_registro" => (string) ($u["fecha_registro"] ?? date("Y-m-d H:i:s")),
                "totp_secret_encrypted" => $u["totp_secret_encrypted"] ?? null,
                "totp_enabled" => isset($u["totp_enabled"]) ? (int) $u["totp_enabled"] : 0,
                "totp_confirmed_at" => $u["totp_confirmed_at"] ?? null,
                "totp_last_timeslice" => isset($u["totp_last_timeslice"]) ? (int) $u["totp_last_timeslice"] : null,
            ];

            $firestore->guardarDocumento("usuarios", (string)$idInt, $docData, true);
            $migrados++;
            echo " - Migrado usuario ID {$idInt}: {$docData['nombre']} {$docData['apellido']} (DNI: {$docData['dni']}, Rol: {$docData['rol']})" . PHP_EOL;
        }
    } else {
        // Verificar si ya existe el usuario ID 1 en Firestore
        $adminDoc = $firestore->obtenerDocumento("usuarios", "1");
        if (!$adminDoc) {
            echo "Creando usuario Administrador inicial en Firestore..." . PHP_EOL;
            $maxId = 1;
            $docData = [
                "id" => 1,
                "dni" => "12345678",
                "nombre" => "Admin",
                "apellido" => "General",
                "password" => password_hash("admin123", PASSWORD_DEFAULT),
                "rol" => "admin",
                "activo" => 1,
                "fecha_registro" => date("Y-m-d H:i:s"),
                "totp_secret_encrypted" => null,
                "totp_enabled" => 0,
                "totp_confirmed_at" => null,
                "totp_last_timeslice" => null,
            ];
            $firestore->guardarDocumento("usuarios", "1", $docData, true);
            $migrados++;
            echo " - Creado usuario inicial ID 1: Admin General (DNI: 12345678, Pass: admin123)" . PHP_EOL;
        } else {
            echo "El usuario ID 1 ya existe en Firestore." . PHP_EOL;
            $maxId = max(1, (int)($adminDoc["id"] ?? 1));
        }
    }

    // 2. Sincronizar contador
    $contadorDoc = $firestore->obtenerDocumento("contadores", "usuarios");
    $ultimoIdContador = isset($contadorDoc["ultimo_id"]) ? (int)$contadorDoc["ultimo_id"] : 0;
    $nuevoUltimoId = max($ultimoIdContador, $maxId);

    $firestore->guardarDocumento("contadores", "usuarios", [
        "ultimo_id" => $nuevoUltimoId,
        "actualizado_el" => date("Y-m-d H:i:s")
    ], true);
    echo "Contador 'contadores/usuarios' sincronizado con ultimo_id = {$nuevoUltimoId}." . PHP_EOL;

    // 3. Eliminar restricciones FK hacia usuarios en MySQL si está conectado
    if ($pdo !== null) {
        echo "Ajustando claves foráneas en MySQL..." . PHP_EOL;
        $queryFK = "SELECT TABLE_NAME, CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = 'control_stock'
                      AND REFERENCED_TABLE_NAME = 'usuarios'";
        $stmtFK = $pdo->query($queryFK);
        $fks = $stmtFK->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fks as $fkInfo) {
            $t = $fkInfo["TABLE_NAME"];
            $c = $fkInfo["CONSTRAINT_NAME"];
            try {
                $pdo->exec("ALTER TABLE `{$t}` DROP FOREIGN KEY `{$c}`");
                echo " - Eliminada FK {$c} de la tabla {$t}" . PHP_EOL;
            } catch (Throwable $e) {
                echo " - Nota FK {$c}: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    echo "=== MIGRACION / SEMILLA FINALIZADA EXITOSAMENTE ===" . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR EN MIGRACION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
