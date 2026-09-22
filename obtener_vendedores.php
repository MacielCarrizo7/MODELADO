<?php
require_once "seguridad.php";
require_once "FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $docsAdmin = $firestore->consultar("usuarios", [
        ["rol", "==", "admin"],
        ["activo", "==", 1]
    ]);
    $docsVend = $firestore->consultar("usuarios", [
        ["rol", "==", "vendedor"],
        ["activo", "==", 1]
    ]);

    $vendedores = [];
    $todos = array_merge($docsAdmin, $docsVend);
    foreach ($todos as $d) {
        $vendedores[] = [
            "id" => (int) ($d["id"] ?? $d["_id"]),
            "nombre" => (string) ($d["nombre"] ?? ""),
            "apellido" => (string) ($d["apellido"] ?? ""),
            "dni" => (string) ($d["dni"] ?? ""),
            "rol" => (string) ($d["rol"] ?? "")
        ];
    }

    usort($vendedores, function ($a, $b) {
        $cmpRol = strcasecmp($a["rol"], $b["rol"]);
        if ($cmpRol !== 0) return $cmpRol;
        $cmpApe = strcasecmp($a["apellido"], $b["apellido"]);
        if ($cmpApe !== 0) return $cmpApe;
        return strcasecmp($a["nombre"], $b["nombre"]);
    });

    echo json_encode($vendedores, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_vendedores: " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
