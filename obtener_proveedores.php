<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $proveedoresRegistrados = $firestore->obtenerColeccion("proveedores");
    $productos = $firestore->obtenerColeccion("productos");
    $ingresos = $firestore->obtenerColeccion("ingresos_stock");

    $proveedoresMapa = [];

    // 1. Cargar proveedores registrados
    foreach ($proveedoresRegistrados as $pr) {
        $id = (int) ($pr["id"] ?? $pr["_id"] ?? 0);
        $nom = trim((string)($pr["nombre"] ?? ""));
        if ($nom === "") continue;

        $proveedoresMapa[$nom] = [
            "id" => $id,
            "proveedor" => $nom,
            "nombre" => $nom,
            "email" => (string) ($pr["email"] ?? ""),
            "direccion" => (string) ($pr["direccion"] ?? ""),
            "telefono" => (string) ($pr["telefono"] ?? ""),
            "cuit_cuil" => (string) ($pr["cuit_cuil"] ?? ""),
            "total_productos" => 0,
            "total_ingresos" => 0,
            "total_unidades" => 0,
            "ultimo_ingreso" => null,
            "productos_set" => []
        ];
    }

    // 2. Asociar productos
    foreach ($productos as $p) {
        $prov = trim((string)($p["proveedor"] ?? ""));
        if ($prov === "") continue;

        if (!isset($proveedoresMapa[$prov])) {
            $proveedoresMapa[$prov] = [
                "id" => 0,
                "proveedor" => $prov,
                "nombre" => $prov,
                "email" => "",
                "direccion" => "",
                "telefono" => "",
                "cuit_cuil" => "",
                "total_productos" => 0,
                "total_ingresos" => 0,
                "total_unidades" => 0,
                "ultimo_ingreso" => null,
                "productos_set" => []
            ];
        }

        $proveedoresMapa[$prov]["total_productos"]++;
        $nomProd = trim((string)($p["nombre"] ?? ""));
        if ($nomProd !== "") {
            $proveedoresMapa[$prov]["productos_set"][$nomProd] = true;
        }
    }

    // 3. Asociar ingresos
    foreach ($ingresos as $ing) {
        $prov = trim((string)($ing["proveedor"] ?? ""));
        if ($prov === "") continue;

        if (!isset($proveedoresMapa[$prov])) {
            $proveedoresMapa[$prov] = [
                "id" => 0,
                "proveedor" => $prov,
                "nombre" => $prov,
                "email" => "",
                "direccion" => "",
                "telefono" => "",
                "cuit_cuil" => "",
                "total_productos" => 0,
                "total_ingresos" => 0,
                "total_unidades" => 0,
                "ultimo_ingreso" => null,
                "productos_set" => []
            ];
        }

        $proveedoresMapa[$prov]["total_ingresos"]++;
        $proveedoresMapa[$prov]["total_unidades"] += (int)($ing["total_unidades"] ?? 0);

        $fechaIng = (string)($ing["fecha"] ?? "");
        if ($fechaIng !== "") {
            if ($proveedoresMapa[$prov]["ultimo_ingreso"] === null || $fechaIng > $proveedoresMapa[$prov]["ultimo_ingreso"]) {
                $proveedoresMapa[$prov]["ultimo_ingreso"] = $fechaIng;
            }
        }
    }

    $resultado = [];
    foreach ($proveedoresMapa as $p) {
        $lista = array_keys($p["productos_set"]);
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        $resultado[] = [
            "id" => $p["id"],
            "proveedor" => $p["proveedor"],
            "nombre" => $p["nombre"],
            "email" => $p["email"],
            "direccion" => $p["direccion"],
            "telefono" => $p["telefono"],
            "cuit_cuil" => $p["cuit_cuil"],
            "total_productos" => $p["total_productos"],
            "total_ingresos" => $p["total_ingresos"],
            "total_unidades" => $p["total_unidades"],
            "ultimo_ingreso" => $p["ultimo_ingreso"],
            "productos_lista" => !empty($lista) ? implode(", ", $lista) : "—"
        ];
    }

    usort($resultado, function ($a, $b) {
        return strcasecmp($a["nombre"], $b["nombre"]);
    });

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_proveedores (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
