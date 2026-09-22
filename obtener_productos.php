<?php
require_once __DIR__ . "/seguridad.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
require_once __DIR__ . "/FirestoreConexion.php";
header("Content-Type: application/json; charset=UTF-8");

$semaforo = trim($_GET["semaforo"] ?? "");
$busqueda = mb_strtolower(trim($_GET["busqueda"] ?? ""));
$presentacion = trim($_GET["presentacion"] ?? "");
$codigoBarrasFiltro = trim($_GET["codigo_barras"] ?? "");

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $todos = $firestore->obtenerColeccion("productos");

    $hoy = new DateTimeImmutable("today");
    $limite45 = $hoy->modify("+45 days");
    $limite90 = $hoy->modify("+90 days");

    $productosFiltrados = [];

    foreach ($todos as $p) {
        $id = (int) ($p["id"] ?? $p["_id"] ?? 0);
        $codigo = (string) ($p["codigo"] ?? "");
        $codigoBarras = (string) ($p["codigo_barras"] ?? "");
        $nombre = (string) ($p["nombre"] ?? "");
        $descripcion = (string) ($p["descripcion"] ?? "");
        $precio = (float) ($p["precio"] ?? 0);
        $stock = (int) ($p["stock"] ?? 0);
        $pres = (string) ($p["presentacion"] ?? "unidad");
        $unidadesBulto = (int) ($p["unidades_por_bulto"] ?? 1);
        $fechaVenc = !empty($p["fecha_vencimiento"]) ? (string)$p["fecha_vencimiento"] : null;
        $proveedor = (string) ($p["proveedor"] ?? "");
        $categoria = (string) ($p["categoria"] ?? "");

        // Filtro por código de barras exacto
        if ($codigoBarrasFiltro !== "" && $codigoBarras !== $codigoBarrasFiltro && $codigo !== $codigoBarrasFiltro) {
            continue;
        }

        // Filtro de búsqueda general
        if ($busqueda !== "") {
            $nomLower = mb_strtolower($nombre);
            $provLower = mb_strtolower($proveedor);
            $codLower = mb_strtolower($codigo);
            $cbLower = mb_strtolower($codigoBarras);
            if (!str_contains($nomLower, $busqueda) && !str_contains($provLower, $busqueda) && !str_contains($codLower, $busqueda) && !str_contains($cbLower, $busqueda)) {
                continue;
            }
        }

        // Filtro de presentación
        if ($presentacion !== "" && $pres !== $presentacion) {
            continue;
        }

        // Filtro de semáforo
        if ($semaforo !== "") {
            if ($semaforo === "sin_fecha") {
                if ($fechaVenc !== null && $fechaVenc !== "") {
                    continue;
                }
            } else {
                if ($fechaVenc === null || $fechaVenc === "") {
                    continue;
                }
                $dtVenc = DateTimeImmutable::createFromFormat("Y-m-d", substr($fechaVenc, 0, 10));
                if (!$dtVenc) {
                    continue;
                }

                if ($semaforo === "rojo") {
                    if ($dtVenc > $limite45) {
                        continue;
                    }
                } elseif ($semaforo === "amarillo") {
                    if ($dtVenc <= $limite45 || $dtVenc > $limite90) {
                        continue;
                    }
                } elseif ($semaforo === "verde") {
                    if ($dtVenc <= $limite90) {
                        continue;
                    }
                }
            }
        }

        $productosFiltrados[] = [
            "id" => $id,
            "codigo" => $codigo !== "" ? $codigo : null,
            "codigo_barras" => $codigoBarras !== "" ? $codigoBarras : null,
            "nombre" => $nombre,
            "descripcion" => $descripcion !== "" ? $descripcion : null,
            "precio" => $precio,
            "stock" => $stock,
            "presentacion" => $pres,
            "unidades_por_bulto" => $unidadesBulto,
            "fecha_vencimiento" => $fechaVenc,
            "proveedor" => $proveedor !== "" ? $proveedor : null,
            "categoria" => $categoria !== "" ? $categoria : null
        ];
    }

    // Ordenamiento FIFO / Vencimiento y Nombre
    usort($productosFiltrados, function ($a, $b) {
        $vencA = $a["fecha_vencimiento"];
        $vencB = $b["fecha_vencimiento"];

        if ($vencA === null && $vencB !== null) return 1;
        if ($vencA !== null && $vencB === null) return -1;
        if ($vencA !== null && $vencB !== null) {
            $cmp = strcmp($vencA, $vencB);
            if ($cmp !== 0) return $cmp;
        }
        return strcasecmp($a["nombre"], $b["nombre"]);
    });

    echo json_encode($productosFiltrados, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_productos (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
