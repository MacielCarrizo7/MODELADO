<?php
require_once "seguridad.php";
require_once "conexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
header("Content-Type: application/json; charset=UTF-8");

$pdo = Conexion::obtenerInstancia();
$rol = $_SESSION["usuario_rol"];
$usuarioId = (int) $_SESSION["usuario_id"];

try {
    if ($rol === "cliente") {
        $stmt = $pdo->prepare(
            "SELECT id, mensaje, estado, fecha, fecha_atencion
             FROM solicitudes_vendedor
             WHERE cliente_id = ?
             ORDER BY fecha DESC LIMIT 5"
        );
        $stmt->execute([$usuarioId]);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once "FirestoreConexion.php";
    $sql = "SELECT s.id, s.cliente_id, s.mensaje, s.estado, s.fecha, s.fecha_atencion, s.atendido_por
            FROM solicitudes_vendedor s
            ORDER BY CASE WHEN s.estado = 'PENDIENTE' THEN 0 ELSE 1 END, s.fecha DESC
            LIMIT 50";

    $stmt = $pdo->query($sql);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userIds = [];
    foreach ($solicitudes as $s) {
        if (!empty($s["cliente_id"])) $userIds[(int)$s["cliente_id"]] = true;
        if (!empty($s["atendido_por"])) $userIds[(int)$s["atendido_por"]] = true;
    }

    $mapaUsuarios = [];
    if (!empty($userIds)) {
        $firestore = FirestoreConexion::obtenerFirestore();
        foreach (array_keys($userIds) as $uid) {
            $uDoc = $firestore->obtenerDocumento("usuarios", (string)$uid);
            if ($uDoc) {
                $mapaUsuarios[$uid] = [
                    "dni" => (string) ($uDoc["dni"] ?? ""),
                    "nombre" => trim(($uDoc["nombre"] ?? "") . " " . ($uDoc["apellido"] ?? ""))
                ];
            } else {
                $mapaUsuarios[$uid] = [
                    "dni" => "",
                    "nombre" => ""
                ];
            }
        }
    }

    foreach ($solicitudes as &$s) {
        $cId = (int) ($s["cliente_id"] ?? 0);
        $aId = (int) ($s["atendido_por"] ?? 0);

        $s["cliente_dni"] = $mapaUsuarios[$cId]["dni"] ?? "";
        $s["cliente_nombre"] = $mapaUsuarios[$cId]["nombre"] ?? "";
        $s["atendido_por_nombre"] = $mapaUsuarios[$aId]["nombre"] ?? "";
    }
    unset($s);

    echo json_encode($solicitudes, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_solicitudes_vendedor: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
