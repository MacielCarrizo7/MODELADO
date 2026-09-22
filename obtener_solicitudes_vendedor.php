<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
header("Content-Type: application/json; charset=UTF-8");

$rol = $_SESSION["usuario_rol"];
$usuarioId = (int) $_SESSION["usuario_id"];

try {
    $firestore = FirestoreConexion::obtenerFirestore();

    if ($rol === "cliente") {
        $solicitudes = $firestore->consultar("solicitudes_vendedor", [
            ["cliente_id", "==", $usuarioId]
        ]);

        $resultado = [];
        foreach ($solicitudes as $s) {
            $resultado[] = [
                "id" => (int) ($s["id"] ?? $s["_id"] ?? 0),
                "mensaje" => (string) ($s["mensaje"] ?? ""),
                "estado" => (string) ($s["estado"] ?? "PENDIENTE"),
                "fecha" => (string) ($s["fecha"] ?? ""),
                "fecha_atencion" => !empty($s["fecha_atencion"]) ? (string)$s["fecha_atencion"] : null
            ];
        }

        usort($resultado, function ($a, $b) {
            $cmp = strcmp($b["fecha"], $a["fecha"]);
            if ($cmp !== 0) return $cmp;
            return $b["id"] <=> $a["id"];
        });

        echo json_encode(array_slice($resultado, 0, 5), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $todasLasSol = $firestore->obtenerColeccion("solicitudes_vendedor");
    $todosLosUsuarios = $firestore->obtenerColeccion("usuarios");

    $mapaUsuarios = [];
    foreach ($todosLosUsuarios as $u) {
        $uId = (int) ($u["id"] ?? $u["_id"] ?? 0);
        if ($uId > 0) {
            $mapaUsuarios[$uId] = [
                "dni" => (string) ($u["dni"] ?? ""),
                "nombre" => trim(($u["nombre"] ?? "") . " " . ($u["apellido"] ?? ""))
            ];
        }
    }

    $resultado = [];
    foreach ($todasLasSol as $s) {
        $cId = (int) ($s["cliente_id"] ?? 0);
        $aId = (int) ($s["atendido_por"] ?? 0);

        $resultado[] = [
            "id" => (int) ($s["id"] ?? $s["_id"] ?? 0),
            "cliente_id" => $cId,
            "mensaje" => (string) ($s["mensaje"] ?? ""),
            "estado" => (string) ($s["estado"] ?? "PENDIENTE"),
            "fecha" => (string) ($s["fecha"] ?? ""),
            "fecha_atencion" => !empty($s["fecha_atencion"]) ? (string)$s["fecha_atencion"] : null,
            "atendido_por" => $aId > 0 ? $aId : null,
            "cliente_dni" => $mapaUsuarios[$cId]["dni"] ?? "",
            "cliente_nombre" => $mapaUsuarios[$cId]["nombre"] ?? "",
            "atendido_por_nombre" => $mapaUsuarios[$aId]["nombre"] ?? ""
        ];
    }

    // Ordenar PENDIENTE primero, luego por fecha DESC
    usort($resultado, function ($a, $b) {
        $prioridadA = ($a["estado"] === "PENDIENTE") ? 0 : 1;
        $prioridadB = ($b["estado"] === "PENDIENTE") ? 0 : 1;
        if ($prioridadA !== $prioridadB) {
            return $prioridadA <=> $prioridadB;
        }
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode(array_slice($resultado, 0, 50), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_solicitudes_vendedor (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
