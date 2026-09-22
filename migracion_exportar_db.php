<?php
declare(strict_types=1);

/**
 * Script de Exportación de Base de Datos - Control Stock
 * Compatible con CLI (Terminal) y Web (Navegador).
 */

$isCli = (php_sapi_name() === 'cli');

// Intentar cargar configuración predeterminada desde conexion.php si existe
$host = "127.0.0.1";
$usuario = "root";
$clave = "";
$basedatos = "control_stock";
$puerto = 3306;

// Permitir sobreescribir vía parámetros CLI o GET/POST
if ($isCli) {
    global $argv;
    if (isset($argv[1])) $host = $argv[1];
    if (isset($argv[2])) $usuario = $argv[2];
    if (isset($argv[3])) $clave = $argv[3];
    if (isset($argv[4])) $basedatos = $argv[4];
    if (isset($argv[5])) $puerto = (int)$argv[5];
} else {
    if (!empty($_POST['host'])) $host = (string)$_POST['host'];
    if (!empty($_POST['usuario'])) $usuario = (string)$_POST['usuario'];
    if (isset($_POST['clave'])) $clave = (string)$_POST['clave'];
    if (!empty($_POST['basedatos'])) $basedatos = (string)$_POST['basedatos'];
    if (!empty($_POST['puerto'])) $puerto = (int)$_POST['puerto'];
}

$archivoSalida = __DIR__ . DIRECTORY_SEPARATOR . "database_backup.sql";
$error = null;
$mensaje = null;
$totalTablas = 0;
$totalRegistros = 0;

if ($isCli || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['ejecutar'])) {
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @new mysqli($host, $usuario, $clave, "", $puerto);

        if ($mysqli->connect_error) {
            throw new RuntimeException("Error al conectar con MySQL ($host:$puerto): " . $mysqli->connect_error);
        }

        $mysqli->set_charset("utf8mb4");

        // Verificar si la base de datos existe
        $resCheck = $mysqli->query("SHOW DATABASES LIKE '" . $mysqli->real_escape_string($basedatos) . "'");
        if (!$resCheck || $resCheck->num_rows === 0) {
            throw new RuntimeException("La base de datos '$basedatos' no existe en el servidor MySQL.");
        }

        $mysqli->select_db($basedatos);

        $sqlDump = "-- ========================================================\n";
        $sqlDump .= "-- RESPALDO COMPLETO DE BASE DE DATOS: " . $basedatos . "\n";
        $sqlDump .= "-- FECHA DE EXPORTACIÓN: " . date("Y-m-d H:i:s") . "\n";
        $sqlDump .= "-- VERSIÓN MYSQL: " . $mysqli->server_info . "\n";
        $sqlDump .= "-- ========================================================\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sqlDump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sqlDump .= "SET NAMES utf8mb4;\n\n";
        $sqlDump .= "CREATE DATABASE IF NOT EXISTS `" . $basedatos . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
        $sqlDump .= "USE `" . $basedatos . "`;\n\n";

        // Obtener todas las tablas
        $resTablas = $mysqli->query("SHOW TABLES");
        if (!$resTablas) {
            throw new RuntimeException("Error al listar tablas: " . $mysqli->error);
        }

        $tablas = [];
        while ($row = $resTablas->fetch_row()) {
            $tablas[] = $row[0];
        }

        // Orden de tablas sugerido para respetar dependencias si foreign keys estuvieran activas
        $ordenDeseado = ['usuarios', 'productos', 'ventas', 'venta_historial', 'ingresos_stock', 'solicitudes_vendedor'];
        usort($tablas, function ($a, $b) use ($ordenDeseado) {
            $posA = array_search($a, $ordenDeseado, true);
            $posB = array_search($b, $ordenDeseado, true);
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;
            return $posA <=> $posB;
        });

        foreach ($tablas as $tabla) {
            $totalTablas++;
            $sqlDump .= "-- --------------------------------------------------------\n";
            $sqlDump .= "-- Estructura de tabla para: `$tabla`\n";
            $sqlDump .= "-- --------------------------------------------------------\n";
            $sqlDump .= "DROP TABLE IF EXISTS `$tabla`;\n";

            $resCreate = $mysqli->query("SHOW CREATE TABLE `$tabla`");
            if ($resCreate && ($rowCreate = $resCreate->fetch_row())) {
                $createSql = $rowCreate[1];
                // Asegurar sintaxis limpia
                $sqlDump .= $createSql . ";\n\n";
            }

            // Datos de la tabla
            $resDatos = $mysqli->query("SELECT * FROM `$tabla`");
            if ($resDatos && $resDatos->num_rows > 0) {
                $numRows = $resDatos->num_rows;
                $totalRegistros += $numRows;
                $sqlDump .= "-- Volcado de datos para la tabla `$tabla` ($numRows registros)\n";

                $columnas = [];
                $fields = $resDatos->fetch_fields();
                foreach ($fields as $field) {
                    $columnas[] = "`" . $field->name . "`";
                }
                $colsSql = implode(", ", $columnas);

                $filasValores = [];
                while ($fila = $resDatos->fetch_assoc()) {
                    $valores = [];
                    foreach ($fila as $valor) {
                        if ($valor === null) {
                            $valores[] = "NULL";
                        } elseif (is_numeric($valor) && !preg_match('/^0[0-9]/', (string)$valor)) {
                            $valores[] = $valor;
                        } else {
                            $valores[] = "'" . $mysqli->real_escape_string((string)$valor) . "'";
                        }
                    }
                    $filasValores[] = "(" . implode(", ", $valores) . ")";

                    if (count($filasValores) >= 100) {
                        $sqlDump .= "INSERT INTO `$tabla` ($colsSql) VALUES\n" . implode(",\n", $filasValores) . ";\n";
                        $filasValores = [];
                    }
                }

                if (count($filasValores) > 0) {
                    $sqlDump .= "INSERT INTO `$tabla` ($colsSql) VALUES\n" . implode(",\n", $filasValores) . ";\n";
                }
                $sqlDump .= "\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // Guardar archivo SQL
        if (file_put_contents($archivoSalida, $sqlDump) === false) {
            throw new RuntimeException("No se pudo escribir el archivo de respaldo en: $archivoSalida");
        }

        $mensaje = "¡Base de datos exportada con éxito! ($totalTablas tablas, $totalRegistros registros exportados en database_backup.sql)";
        
        if ($isCli) {
            echo "[OK] " . $mensaje . PHP_EOL;
            echo "Archivo generado: " . $archivoSalida . " (" . round(filesize($archivoSalida) / 1024, 2) . " KB)" . PHP_EOL;
            exit(0);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($isCli) {
            fwrite(STDERR, "[ERROR] " . $error . PHP_EOL);
            exit(1);
        }
    }
}

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Base de Datos - Control Stock</title>
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --border: #334155;
        }
        body {
            margin: 0;
            padding: 2rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .container {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            max-width: 580px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        h1 {
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #60a5fa;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-top: 0;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .alert-success {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--success);
            color: #34d399;
        }
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #f87171;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input {
            width: 100%;
            padding: 0.75rem;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: #fff;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 0.85rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
            transition: background 0.2s ease;
        }
        .btn:hover {
            background: var(--primary-hover);
        }
        .btn-success {
            background: var(--success);
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-secondary {
            background: #475569;
            margin-top: 0.5rem;
        }
        .btn-secondary:hover {
            background: #334155;
        }
        .actions {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Exportar Base de Datos</h1>
        <p>Genera un archivo SQL completo con todas las tablas y datos actuales de la aplicación para transportarla a otra PC.</p>

        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($mensaje) ?><br>
                <small>Ubicación: <?= htmlspecialchars($archivoSalida) ?></small>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ❌ <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="migracion_exportar_db.php">
            <div class="grid-2">
                <div class="form-group">
                    <label for="host">Servidor Host</label>
                    <input type="text" id="host" name="host" value="<?= htmlspecialchars($host) ?>" required>
                </div>
                <div class="form-group">
                    <label for="puerto">Puerto MySQL</label>
                    <input type="number" id="puerto" name="puerto" value="<?= htmlspecialchars((string)$puerto) ?>" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="usuario">Usuario MySQL</label>
                    <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($usuario) ?>" required>
                </div>
                <div class="form-group">
                    <label for="clave">Contraseña MySQL</label>
                    <input type="password" id="clave" name="clave" value="<?= htmlspecialchars($clave) ?>" placeholder="(vacía por defecto en XAMPP)">
                </div>
            </div>
            <div class="form-group">
                <label for="basedatos">Nombre Base de Datos</label>
                <input type="text" id="basedatos" name="basedatos" value="<?= htmlspecialchars($basedatos) ?>" required>
            </div>

            <div class="actions">
                <button type="submit" class="btn">🚀 Generar Respaldo (database_backup.sql)</button>
                <?php if (file_exists($archivoSalida)): ?>
                    <a href="database_backup.sql" download class="btn btn-success">⬇️ Descargar database_backup.sql (<?= round(filesize($archivoSalida)/1024, 1) ?> KB)</a>
                <?php endif; ?>
                <a href="login.php" class="btn btn-secondary">⬅️ Volver a la Aplicación</a>
            </div>
        </form>
    </div>
</body>
</html>
<?php endif; ?>
