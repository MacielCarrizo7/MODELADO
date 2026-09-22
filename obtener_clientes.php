<?php
require_once "seguridad.php";
require_once "FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $docs = $firestore->consultar("usuarios", [
        ["rol", "==", "cliente"],
        ["activo", "==", 1]
    ]);

    $clientes = [];
    foreach ($docs as $d) {
        $clientes[] = [
            "id" => (int) ($d["id"] ?? $d["_id"]),
            "nombre" => (string) ($d["nombre"] ?? ""),
            "apellido" => (string) ($d["apellido"] ?? ""),
            "dni" => (string) ($d["dni"] ?? "")
        ];
    }

    usort($clientes, function ($a, $b) {
        $cmpApe = strcasecmp($a["apellido"], $b["apellido"]);
        if ($cmpApe !== 0) return $cmpApe;
        return strcasecmp($a["nombre"], $b["nombre"]);
    });

    echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_clientes: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
