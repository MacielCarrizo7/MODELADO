<?php
/**
 * Archivo centralizado de conexión a MySQL vía PDO
 * Configurado para el entorno estándar de XAMPP (localhost, root, sin contraseña).
 */
class Conexion {
    private static ?PDO $instancia = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Obtiene la instancia única de conexión PDO (Patrón Singleton)
     * @return PDO
     */
    public static function obtenerInstancia(): PDO {
        if (self::$instancia === null) {
            $host = "localhost";
            $basedatos = "control_stock";
            $usuario = "root";
            $clave = "";
            $charset = "utf8mb4";

            $dsn = "mysql:host={$host};dbname={$basedatos};charset={$charset}";
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instancia = new PDO($dsn, $usuario, $clave, $opciones);
            } catch (PDOException $e) {
                // Registrar log interno y responder de forma amigable
                error_log("Error de conexión PDO: " . $e->getMessage());
                http_response_code(500);
                if (php_sapi_name() === "cli") {
                    die("Error de conexión a la base de datos: " . $e->getMessage() . PHP_EOL);
                }
                die("Error de conexión a la base de datos MySQL (XAMPP). Verifique que el servicio MySQL esté iniciado en el panel de XAMPP.");
            }
        }
        return self::$instancia;
    }

    /**
     * Alias explicito para obtener la conexión PDO
     * @return PDO
     */
    public static function obtenerPDO(): PDO {
        return self::obtenerInstancia();
    }
}
?>
