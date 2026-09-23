<?php
/**
 * Conexión centralizada a Firebase Firestore vía REST API oficial de Google
 * Compatible al 100% con XAMPP / PHP 8.2 sin requerir extensiones compiladas adicionales.
 * Implementa el Patrón Singleton y soporte para variables de entorno.
 */

require_once __DIR__ . "/vendor/autoload.php";

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client as HttpClient;

class FirestoreRestCliente {
    private string $projectId;
    private string $keyFilePath;
    private ?ServiceAccountCredentials $credenciales = null;
    private HttpClient $http;
    private string $baseUrl;
    private array $tokenCache = [];

    public function __construct(string $projectId, string $keyFilePath) {
        $this->projectId = $projectId;
        $this->keyFilePath = $keyFilePath;
        $this->http = new HttpClient([
            "timeout" => 15.0,
            "http_errors" => false,
        ]);
        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents";

        $scopes = ["https://www.googleapis.com/auth/datastore"];
        $this->credenciales = new ServiceAccountCredentials($scopes, $this->keyFilePath);
    }

    /**
     * Obtiene un token Bearer OAuth2 válido para las peticiones a Firestore.
     */
    public function obtenerToken(): string {
        $ahora = time();
        if (!empty($this->tokenCache) && isset($this->tokenCache["access_token"], $this->tokenCache["expires_at"]) && $this->tokenCache["expires_at"] > ($ahora + 60)) {
            return $this->tokenCache["access_token"];
        }

        $tokenData = $this->credenciales->fetchAuthToken();
        if (empty($tokenData["access_token"])) {
            throw new RuntimeException("No se pudo obtener el token de autenticación de Google Cloud.");
        }

        $expiraEn = (int) ($tokenData["expires_in"] ?? 3600);
        $this->tokenCache = [
            "access_token" => $tokenData["access_token"],
            "expires_at" => $ahora + $expiraEn,
        ];

        return $this->tokenCache["access_token"];
    }

    /**
     * Convierte valores nativos de PHP al formato de tipos de Firestore REST API.
     */
    public static function phpAFirestoreValor(mixed $valor): array {
        if ($valor === null) {
            return ["nullValue" => null];
        }
        if (is_bool($valor)) {
            return ["booleanValue" => $valor];
        }
        if (is_int($valor)) {
            return ["integerValue" => (string) $valor];
        }
        if (is_float($valor)) {
            return ["doubleValue" => $valor];
        }
        if (is_string($valor)) {
            return ["stringValue" => $valor];
        }
        if (is_array($valor)) {
            $esLista = array_is_list($valor);
            if ($esLista) {
                $valores = [];
                foreach ($valor as $item) {
                    $valores[] = self::phpAFirestoreValor($item);
                }
                return ["arrayValue" => ["values" => $valores]];
            } else {
                $campos = [];
                foreach ($valor as $k => $v) {
                    $campos[$k] = self::phpAFirestoreValor($v);
                }
                return ["mapValue" => ["fields" => $campos]];
            }
        }
        return ["stringValue" => (string) $valor];
    }

    /**
     * Convierte campos de Firestore REST API a tipos nativos de PHP.
     */
    public static function firestoreAPhpValor(array $firestoreValor): mixed {
        if (array_key_exists("nullValue", $firestoreValor)) {
            return null;
        }
        if (array_key_exists("booleanValue", $firestoreValor)) {
            return (bool) $firestoreValor["booleanValue"];
        }
        if (array_key_exists("integerValue", $firestoreValor)) {
            return (int) $firestoreValor["integerValue"];
        }
        if (array_key_exists("doubleValue", $firestoreValor)) {
            return (float) $firestoreValor["doubleValue"];
        }
        if (array_key_exists("stringValue", $firestoreValor)) {
            return (string) $firestoreValor["stringValue"];
        }
        if (array_key_exists("timestampValue", $firestoreValor)) {
            return (string) $firestoreValor["timestampValue"];
        }
        if (array_key_exists("arrayValue", $firestoreValor)) {
            $res = [];
            $items = $firestoreValor["arrayValue"]["values"] ?? [];
            foreach ($items as $item) {
                $res[] = self::firestoreAPhpValor($item);
            }
            return $res;
        }
        if (array_key_exists("mapValue", $firestoreValor)) {
            $res = [];
            $campos = $firestoreValor["mapValue"]["fields"] ?? [];
            foreach ($campos as $k => $v) {
                $res[$k] = self::firestoreAPhpValor($v);
            }
            return $res;
        }
        return null;
    }

    /**
     * Convierte un documento completo de Firestore REST a un array asociativo PHP.
     */
    public static function formatearDocumento(array $doc): array {
        $resultado = [];
        if (isset($doc["name"])) {
            $partes = explode("/", $doc["name"]);
            $resultado["_id"] = end($partes);
            $resultado["_name"] = $doc["name"];
        }
        if (isset($doc["fields"]) && is_array($doc["fields"])) {
            foreach ($doc["fields"] as $campo => $firestoreValor) {
                $resultado[$campo] = self::firestoreAPhpValor($firestoreValor);
            }
        }
        return $resultado;
    }

    /**
     * Obtiene un documento por ID de colección y documento.
     */
    public function obtenerDocumento(string $coleccion, string $docId): ?array {
        $token = $this->obtenerToken();
        $url = "{$this->baseUrl}/{$coleccion}/{$docId}";

        $respuesta = $this->http->get($url, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Accept" => "application/json",
            ]
        ]);

        $codigo = $respuesta->getStatusCode();
        if ($codigo === 404) {
            return null;
        }
        if ($codigo >= 400) {
            throw new RuntimeException("Error al obtener documento de Firestore ({$codigo}): " . (string)$respuesta->getBody());
        }

        $cuerpo = json_decode((string)$respuesta->getBody(), true);
        if (!$cuerpo || !isset($cuerpo["fields"])) {
            return null;
        }

        return self::formatearDocumento($cuerpo);
    }

    /**
     * Guarda o crea un documento (con opción de merge).
     */
    public function guardarDocumento(string $coleccion, string $docId, array $datos, bool $merge = true): bool {
        $token = $this->obtenerToken();
        $url = "{$this->baseUrl}/{$coleccion}/{$docId}";

        $fields = [];
        $fieldPaths = [];
        foreach ($datos as $k => $v) {
            $fields[$k] = self::phpAFirestoreValor($v);
            $fieldPaths[] = "updateMask.fieldPaths=" . urlencode($k);
        }

        if ($merge && !empty($fieldPaths)) {
            $url .= "?" . implode("&", $fieldPaths);
        }

        $respuesta = $this->http->patch($url, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            "json" => [
                "fields" => $fields,
            ]
        ]);

        $codigo = $respuesta->getStatusCode();
        if ($codigo >= 400) {
            throw new RuntimeException("Error al guardar documento en Firestore ({$codigo}): " . (string)$respuesta->getBody());
        }

        return true;
    }

    /**
     * Actualiza campos específicos de un documento existente.
     */
    public function actualizarCampos(string $coleccion, string $docId, array $campos): bool {
        return $this->guardarDocumento($coleccion, $docId, $campos, true);
    }

    /**
     * Elimina un documento de una colección.
     */
    public function eliminarDocumento(string $coleccion, string $docId): bool {
        $token = $this->obtenerToken();
        $url = "{$this->baseUrl}/{$coleccion}/{$docId}";

        $respuesta = $this->http->delete($url, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Accept" => "application/json",
            ]
        ]);

        $codigo = $respuesta->getStatusCode();
        return ($codigo === 200 || $codigo === 204 || $codigo === 404);
    }

    /**
     * Realiza consultas estructuradas en Firestore.
     * $filtros es un array de tripletas: [['campo', 'operador', $valor], ...]
     * Operadores admitidos: '=', '==', 'EQUAL', '>', '>=', '<', '<=', 'IN', 'ARRAY_CONTAINS'
     */
    public function consultar(string $coleccion, array $filtros = [], ?string $ordenCampo = null, string $ordenDir = "ASC", ?int $limite = null): array {
        $token = $this->obtenerToken();
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:runQuery";

        $structuredQuery = [
            "from" => [
                ["collectionId" => $coleccion]
            ]
        ];

        // Construcción de filtros
        if (!empty($filtros)) {
            $fieldFilters = [];
            foreach ($filtros as $f) {
                if (!is_array($f) || count($f) < 3) continue;
                [$campo, $op, $val] = $f;
                $opUpper = strtoupper(trim($op));

                $firestoreOp = match ($opUpper) {
                    "=", "==" => "EQUAL",
                    ">" => "GREATER_THAN",
                    ">=" => "GREATER_THAN_OR_EQUAL",
                    "<" => "LESS_THAN",
                    "<=" => "LESS_THAN_OR_EQUAL",
                    "IN" => "IN",
                    "ARRAY_CONTAINS" => "ARRAY_CONTAINS",
                    default => "EQUAL"
                };

                $fieldFilters[] = [
                    "fieldFilter" => [
                        "field" => ["fieldPath" => $campo],
                        "op" => $firestoreOp,
                        "value" => self::phpAFirestoreValor($val),
                    ]
                ];
            }

            if (count($fieldFilters) === 1) {
                $structuredQuery["where"] = $fieldFilters[0];
            } elseif (count($fieldFilters) > 1) {
                $structuredQuery["where"] = [
                    "compositeFilter" => [
                        "op" => "AND",
                        "filters" => $fieldFilters
                    ]
                ];
            }
        }

        // Ordenamiento
        if ($ordenCampo !== null && $ordenCampo !== "") {
            $structuredQuery["orderBy"] = [
                [
                    "field" => ["fieldPath" => $ordenCampo],
                    "direction" => (strtoupper($ordenDir) === "DESC") ? "DESCENDING" : "ASCENDING"
                ]
            ];
        }

        // Límite
        if ($limite !== null && $limite > 0) {
            $structuredQuery["limit"] = $limite;
        }

        $respuesta = $this->http->post($url, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            "json" => [
                "structuredQuery" => $structuredQuery
            ]
        ]);

        $codigo = $respuesta->getStatusCode();
        if ($codigo >= 400) {
            throw new RuntimeException("Error en consulta structuredQuery ({$codigo}): " . (string)$respuesta->getBody());
        }

        $items = json_decode((string)$respuesta->getBody(), true);
        if (!is_array($items)) {
            return [];
        }

        $documentos = [];
        foreach ($items as $item) {
            if (isset($item["document"])) {
                $documentos[] = self::formatearDocumento($item["document"]);
            }
        }

        return $documentos;
    }

    /**
     * Obtiene todos los documentos de una colección.
     */
    public function obtenerColeccion(string $coleccion, int $maxDocs = 500): array {
        $token = $this->obtenerToken();
        $url = "{$this->baseUrl}/{$coleccion}?pageSize=" . min($maxDocs, 300);

        $documentos = [];
        $nextPageToken = null;

        do {
            $pUrl = $url . ($nextPageToken ? "&pageToken=" . urlencode($nextPageToken) : "");
            $respuesta = $this->http->get($pUrl, [
                "headers" => [
                    "Authorization" => "Bearer {$token}",
                    "Accept" => "application/json",
                ]
            ]);

            if ($respuesta->getStatusCode() !== 200) {
                break;
            }

            $datos = json_decode((string)$respuesta->getBody(), true);
            if (isset($datos["documents"]) && is_array($datos["documents"])) {
                foreach ($datos["documents"] as $doc) {
                    $documentos[] = self::formatearDocumento($doc);
                    if (count($documentos) >= $maxDocs) {
                        break 2;
                    }
                }
            }
            $nextPageToken = $datos["nextPageToken"] ?? null;
        } while ($nextPageToken !== null);

        return $documentos;
    }

    /**
     * Alias de obtenerColeccion para compatibilidad
     */
    public function obtenerTodos(string $coleccion, int $maxDocs = 500): array {
        return $this->obtenerColeccion($coleccion, $maxDocs);
    }

    /**
     * Cuenta documentos de una colección (vía listar / query).
     */
    public function contarDocumentos(string $coleccion): int {
        $token = $this->obtenerToken();
        $url = "{$this->baseUrl}/{$coleccion}?pageSize=300&mask.fieldPaths=id";

        $conteo = 0;
        $nextPageToken = null;

        do {
            $pUrl = $url . ($nextPageToken ? "&pageToken=" . urlencode($nextPageToken) : "");
            $respuesta = $this->http->get($pUrl, [
                "headers" => [
                    "Authorization" => "Bearer {$token}",
                    "Accept" => "application/json",
                ]
            ]);

            if ($respuesta->getStatusCode() !== 200) {
                break;
            }

            $datos = json_decode((string)$respuesta->getBody(), true);
            if (isset($datos["documents"]) && is_array($datos["documents"])) {
                $conteo += count($datos["documents"]);
            }
            $nextPageToken = $datos["nextPageToken"] ?? null;
        } while ($nextPageToken !== null);

        return $conteo;
    }

    /**
     * Genera un ID autoincremental atómico utilizando Firestore write commit / transactions.
     */
    public function obtenerSiguienteId(string $coleccion = "contadores", string $documento = "usuarios", string $campo = "ultimo_id"): int {
        $token = $this->obtenerToken();

        // 1. Iniciar transacción en Firestore
        $urlTx = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:beginTransaction";
        $resTx = $this->http->post($urlTx, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            "json" => (object)[]
        ]);

        if ($resTx->getStatusCode() !== 200) {
            throw new RuntimeException("No se pudo iniciar la transacción en Firestore: " . (string)$resTx->getBody());
        }

        $txData = json_decode((string)$resTx->getBody(), true);
        $transactionId = $txData["transaction"];

        // 2. Leer el documento actual dentro de la transacción
        $urlGet = "{$this->baseUrl}/{$coleccion}/{$documento}?transaction=" . urlencode($transactionId);
        $resGet = $this->http->get($urlGet, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Accept" => "application/json",
            ]
        ]);

        $ultimoId = 0;
        if ($resGet->getStatusCode() === 200) {
            $docData = json_decode((string)$resGet->getBody(), true);
            if (isset($docData["fields"][$campo]["integerValue"])) {
                $ultimoId = (int) $docData["fields"][$campo]["integerValue"];
            }
        }

        $siguienteId = $ultimoId + 1;
        $docName = "projects/{$this->projectId}/databases/(default)/documents/{$coleccion}/{$documento}";

        // 3. Confirmar la escritura en la transacción (Commit)
        $urlCommit = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:commit";
        $resCommit = $this->http->post($urlCommit, [
            "headers" => [
                "Authorization" => "Bearer {$token}",
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            "json" => [
                "transaction" => $transactionId,
                "writes" => [
                    [
                        "update" => [
                            "name" => $docName,
                            "fields" => [
                                $campo => ["integerValue" => (string)$siguienteId],
                                "actualizado_el" => ["stringValue" => date("Y-m-d H:i:s")]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        if ($resCommit->getStatusCode() !== 200) {
            throw new RuntimeException("Error al confirmar transacción de contador en Firestore: " . (string)$resCommit->getBody());
        }

        return $siguienteId;
    }
}

class FirestoreConexion {
    private static ?FirestoreRestCliente $instancia = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Resuelve la ruta al archivo JSON de credenciales de la cuenta de servicio de Firebase.
     */
    public static function obtenerRutaCredenciales(): string {
        // 1. Variable de entorno con ruta a archivo
        $rutaEnv = getenv("FIREBASE_CREDENTIALS");
        if ($rutaEnv !== false && trim($rutaEnv) !== "" && file_exists(trim($rutaEnv))) {
            return trim($rutaEnv);
        }

        // 2. Variable de entorno con contenido JSON directo (Ideal para Render/Heroku)
        $jsonDirecto = getenv("FIREBASE_CREDENTIALS_JSON");
        if ($jsonDirecto !== false && trim($jsonDirecto) !== "") {
            $tmpPath = sys_get_temp_dir() . "/firebase-credentials.json";
            if (!file_exists($tmpPath) || md5_file($tmpPath) !== md5($jsonDirecto)) {
                file_put_contents($tmpPath, trim($jsonDirecto));
            }
            return $tmpPath;
        }

        // 3. Rutas estándar en servidores cloud (Render Secret Files, Docker, Linux) y locales
        $candidatos = [
            "/etc/secrets/firebase-credentials.json",
            "/etc/secrets/serviceAccountKey.json",
            __DIR__ . "/credenciales/modelado-e4de7-firebase-adminsdk-fbsvc-b0220e2c36.json",
            __DIR__ . "/firebase-credentials.json",
            __DIR__ . "/serviceAccountKey.json",
            "C:\\xampp\\credenciales\\modelado-e4de7-firebase-adminsdk-fbsvc-b0220e2c36.json",
            "C:\\xampp\\htdocs\\credenciales\\modelado-e4de7-firebase-adminsdk-fbsvc-b0220e2c36.json",
        ];

        if (is_dir(__DIR__ . "/credenciales")) {
            $archivosLocales = glob(__DIR__ . "/credenciales/*firebase-adminsdk*.json");
            if (!empty($archivosLocales)) {
                $candidatos = array_merge($archivosLocales, $candidatos);
            }
        }

        if (is_dir("C:\\xampp\\credenciales")) {
            $archivos = glob("C:\\xampp\\credenciales\\*firebase-adminsdk*.json");
            if (!empty($archivos)) {
                $candidatos = array_merge($archivos, $candidatos);
            }
        }

        foreach ($candidatos as $ruta) {
            if (file_exists($ruta)) {
                return $ruta;
            }
        }

        throw new RuntimeException("No se encontró el archivo JSON de credenciales de Firebase. Configure la variable FIREBASE_CREDENTIALS o FIREBASE_CREDENTIALS_JSON en Render.");
    }

    /**
     * Obtiene el Project ID configurado.
     */
    public static function obtenerProjectId(): string {
        $projectEnv = getenv("FIREBASE_PROJECT_ID");
        if ($projectEnv !== false && trim($projectEnv) !== "") {
            return trim($projectEnv);
        }
        return "modelado-e4de7";
    }

    /**
     * Obtiene la instancia del cliente de Firestore (Singleton).
     */
    public static function obtenerFirestore(): FirestoreRestCliente {
        if (self::$instancia === null) {
            $rutaKey = self::obtenerRutaCredenciales();
            $projectId = self::obtenerProjectId();

            try {
                self::$instancia = new FirestoreRestCliente($projectId, $rutaKey);
            } catch (Throwable $e) {
                error_log("Error al conectar con Firestore: " . $e->getMessage());
                http_response_code(500);
                if (php_sapi_name() === "cli") {
                    die("Error de conexión a Firebase Firestore: " . $e->getMessage() . PHP_EOL);
                }
                die("Error de conexión a Firebase Firestore. Verifique las credenciales y la conexión a Internet.");
            }
        }

        return self::$instancia;
    }

    /**
     * Alias de obtenerFirestore
     */
    public static function obtenerInstancia(): FirestoreRestCliente {
        return self::obtenerFirestore();
    }

    /**
     * Genera el siguiente ID numérico autoincremental de forma atómica y transaccional
     * utilizando el documento 'contadores/usuarios' en Firestore.
     */
    public static function obtenerSiguienteIdUsuario(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "usuarios", "ultimo_id");
    }

    public static function obtenerSiguienteIdProducto(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "productos", "ultimo_id");
    }

    public static function obtenerSiguienteIdVenta(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "ventas", "ultimo_id");
    }

    public static function obtenerSiguienteIdSolicitud(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "solicitudes", "ultimo_id");
    }

    public static function obtenerSiguienteIdHistorial(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "historial", "ultimo_id");
    }

    public static function obtenerSiguienteIdProveedor(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "proveedores", "ultimo_id");
    }

    public static function obtenerSiguienteIdCategoria(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "categorias", "ultimo_id");
    }

    public static function obtenerSiguienteIdMovimiento(): int {
        return self::obtenerFirestore()->obtenerSiguienteId("contadores", "movimientos", "ultimo_id");
    }

    /**
     * Registra un evento en el historial de trazabilidad de un producto.
     */
    public static function registrarMovimientoProducto(
        int $productoId,
        string $tipo,
        string $descripcion,
        ?int $cantidadAnterior = null,
        ?int $cantidadNueva = null,
        ?int $diferencia = null,
        ?float $precioAnterior = null,
        ?float $precioNuevo = null,
        ?int $usuarioId = null,
        ?string $usuarioNombre = null
    ): int {
        try {
            $firestore = self::obtenerFirestore();
            $movId = self::obtenerSiguienteIdMovimiento();
            $fecha = date("Y-m-d H:i:s");

            if ($usuarioId === null && isset($_SESSION["usuario_id"])) {
                $usuarioId = (int) $_SESSION["usuario_id"];
            }
            if ($usuarioNombre === null && isset($_SESSION["usuario_nombre"])) {
                $usuarioNombre = trim(($_SESSION["usuario_nombre"] ?? "") . " " . ($_SESSION["usuario_apellido"] ?? ""));
            }

            $doc = [
                "id" => $movId,
                "producto_id" => $productoId,
                "tipo" => $tipo,
                "descripcion" => $descripcion,
                "cantidad_anterior" => $cantidadAnterior,
                "cantidad_nueva" => $cantidadNueva,
                "diferencia" => $diferencia,
                "precio_anterior" => $precioAnterior,
                "precio_nuevo" => $precioNuevo,
                "usuario_id" => $usuarioId,
                "usuario_nombre" => $usuarioNombre,
                "fecha" => $fecha
            ];

            $firestore->guardarDocumento("movimientos_producto", (string)$movId, $doc);
            return $movId;
        } catch (Throwable $e) {
            error_log("Error al registrar movimiento de producto: " . $e->getMessage());
            return 0;
        }
    }
}
?>
