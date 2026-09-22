<?php
declare(strict_types=1);

/**
 * Script de Importación / Restauración de Base de Datos - Control Stock
 * Compatible con CLI (Terminal) y Web (Navegador).
 */

$isCli = (php_sapi_name() === 'cli');

$host = "127.0.0.1";
$usuario = "root";
$clave = "";
$basedatos = "control_stock";
$puerto = 3306;

// Archivos de origen preferidos
$archivoSql = __DIR__ . DIRECTORY_SEPARATOR . "database_backup.sql";
if (!file_exists($archivoSql)) {
    $archivoSql = __DIR__ . DIRECTORY_SEPARATOR . "database.sql";
}

if ($isCli) {
    global $argv;
    if (isset($argv[1])) $host = $argv[1];
    if (isset($argv[2])) $usuario = $argv[2];
    if (isset($argv[3])) $clave = $argv[3];
    if (isset($argv[4])) $basedatos = $argv[4];
    if (isset($argv[5])) $puerto = (int)$argv[5];
    if (isset($argv[6])) $archivoSql = $argv[6];
} else {
    if (!empty($_POST['host'])) $host = (string)$_POST['host'];
    if (!empty($_POST['usuario'])) $usuario = (string)$_POST['usuario'];
    if (isset($_POST['clave'])) $clave = (string)$_POST['clave'];
    if (!empty($_POST['basedatos'])) $basedatos = (string)$_POST['basedatos'];
    if (!empty($_POST['puerto'])) $puerto = (int)$_POST['puerto'];
    if (!empty($_POST['archivo_sql']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $_POST['archivo_sql'])) {
        $archivoSql = __DIR__ . DIRECTORY_SEPARATOR . $_POST['archivo_sql'];
    }
}

$error = null;
$mensaje = null;
$consultasEjecutadas = 0;

if ($isCli || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['ejecutar'])) {
    try {
        if (!file_exists($archivoSql)) {
            throw new RuntimeException("No se encontró el archivo SQL para importar en: $archivoSql");
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @new mysqli($host, $usuario, $clave, "", $puerto);

        if ($mysqli->connect_error) {
            throw new RuntimeException("Error de conexión a MySQL ($host:$puerto): " . $mysqli->connect_error . 
                "\nVerificá que MySQL esté iniciado en el panel de XAMPP.");
        }

        $mysqli->set_charset("utf8mb4");

        // Crear base de datos si no existe
        $sqlCrearBd = "CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($basedatos) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
        if (!$mysqli->query($sqlCrearBd)) {
            throw new RuntimeException("No se pudo crear la base de datos '$basedatos': " . $mysqli->error);
        }

        $mysqli->select_db($basedatos);
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0;");

        // Leer y parsear el archivo SQL por sentencias
        $contenido = file_get_contents($archivoSql);
        if ($contenido === false) {
            throw new RuntimeException("Error al leer el archivo $archivoSql");
        }

        // Remover comentarios de una sola línea
        $lineas = explode("\n", $contenido);
        $queryActual = "";

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === "" || str_starts_with($lineaTrim, "--") || str_starts_with($lineaTrim, "//") || str_starts_with($lineaTrim, "/*")) {
                continue;
            }

            $queryActual .= $linea . "\n";

            if (str_ends_with($lineaTrim, ";")) {
                if (trim($queryActual) !== "") {
                    if (!$mysqli->query($queryActual)) {
                        // Ignorar advertencias menores como tablas ya existentes, lanzar en errores críticos
                        $errCode = $mysqli->errno;
                        if ($errCode !== 1050 && $errCode !== 1060) { // 1050: Table already exists, 1060: Duplicate column name
                            throw new RuntimeException("Error al ejecutar sentencia SQL: " . $mysqli->error . "\nSentencia: " . substr($queryActual, 0, 150) . "...");
                        }
                    }
                    $consultasEjecutadas++;
                }
                $queryActual = "";
            }
        }

        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1;");

        $mensaje = "¡Base de datos importada exitosamente! Se ejecutaron $consultasEjecutadas instrucciones SQL desde " . basename($archivoSql);

        if ($isCli) {
            echo "[OK] " . $mensaje . PHP_EOL;
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
    <title>Importar Base de Datos - Control Stock</title>
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #10b981;
            --primary-hover: #059669;
            --info: #3b82f6;
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
            color: #34d399;
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
            border: 1px solid var(--primary);
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
        input, select {
            width: 100%;
            padding: 0.75rem;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: #fff;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
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
        .btn-info {
            background: var(--info);
        }
        .btn-info:hover {
            background: #2563eb;
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
        <h1>📥 Importar / Restaurar Base de Datos</h1>
        <p>Crea la base de datos <strong>control_stock</strong> e importa todas las tablas, usuarios, productos y ventas en esta PC.</p>

        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ❌ <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="migracion_importar_db.php">
            <div class="form-group">
                <label for="archivo_sql">Archivo SQL a Importar</label>
                <select id="archivo_sql" name="archivo_sql">
                    <?php if (file_exists(__DIR__ . '/database_backup.sql')): ?>
                        <option value="database_backup.sql" selected>database_backup.sql (Respaldo con tus datos actuales)</option>
                    <?php endif; ?>
                    <option value="database.sql" <?= !file_exists(__DIR__ . '/database_backup.sql') ? 'selected' : '' ?>>database.sql (Estructura base limpia)</option>
                </select>
            </div>

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
                <button type="submit" class="btn">⚡ Ejecutar Importación / Restauración</button>
                <a href="login.php" class="btn btn-info">🚀 Ir al Login de la App</a>
            </div>
        </form>
    </div>
</body>
</html>
<?php endif; ?>
