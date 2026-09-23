<?php
require_once __DIR__ . "/seguridad.php";
requerirUsuarioJson(["admin", "vendedor", "cliente"]);
require_once __DIR__ . "/FirestoreConexion.php";
header("Content-Type: application/json; charset=UTF-8");

$esAdmin = isset($_SESSION["usuario_rol"]) && $_SESSION["usuario_rol"] === "admin";
$semaforo = trim($_GET["semaforo"] ?? "");
$busqueda = mb_strtolower(trim($_GET["busqueda"] ?? ""));
$presentacion = trim($_GET["presentacion"] ?? "");
$categoriaFiltro = trim($_GET["categoria"] ?? "");
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
        $precioVenta = (float) ($p["precio_venta"] ?? $p["precio"] ?? 0);
        $precioCosto = (float) ($p["precio_costo"] ?? 0);
        $stock = (int) ($p["stock"] ?? 0);
        $pres = (string) ($p["presentacion"] ?? "unidad");
        $unidadesBulto = (int) ($p["unidades_por_bulto"] ?? 1);
        $fechaVenc = !empty($p["fecha_vencimiento"]) ? (string)$p["fecha_vencimiento"] : null;
        $proveedor = (string) ($p["proveedor"] ?? "");
        $categoriaId = isset($p["categoria_id"]) ? (int)$p["categoria_id"] : null;
        $categoriaNombre = (string) ($p["categoria_nombre"] ?? $p["categoria"] ?? "");
        $imagenUrl = (string) ($p["imagen_url"] ?? "");

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
            $catLower = mb_strtolower($categoriaNombre);
            if (!str_contains($nomLower, $busqueda) && !str_contains($provLower, $busqueda) && !str_contains($codLower, $busqueda) && !str_contains($cbLower, $busqueda) && !str_contains($catLower, $busqueda)) {
                continue;
            }
        }

        // Filtro de presentación
        if ($presentacion !== "" && $pres !== $presentacion) {
            continue;
        }

        // Filtro de categoría
        if ($categoriaFiltro !== "" && $categoriaNombre !== $categoriaFiltro && (string)$categoriaId !== $categoriaFiltro) {
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

        $prodItem = [
            "id" => $id,
            "codigo" => $codigo !== "" ? $codigo : null,
            "codigo_barras" => $codigoBarras !== "" ? $codigoBarras : null,
            "nombre" => $nombre,
            "descripcion" => $descripcion !== "" ? $descripcion : null,
            "precio" => $precioVenta,
            "precio_venta" => $precioVenta,
            "stock" => $stock,
            "presentacion" => $pres,
            "unidades_por_bulto" => $unidadesBulto,
            "fecha_vencimiento" => $fechaVenc,
            "proveedor" => $proveedor !== "" ? $proveedor : null,
            "categoria_id" => $categoriaId,
            "categoria" => $categoriaNombre !== "" ? $categoriaNombre : null,
            "categoria_nombre" => $categoriaNombre !== "" ? $categoriaNombre : null,
            "imagen_url" => $imagenUrl !== "" ? $imagenUrl : null
        ];

        if ($esAdmin) {
            $prodItem["precio_costo"] = $precioCosto;
        }

        $productosFiltrados[] = $prodItem;
    }

    // Ordenar por nombre
    usort($productosFiltrados, function ($a, $b) {
        return strcasecmp($a["nombre"] ?? "", $b["nombre"] ?? "");
    });

    echo json_encode($productosFiltrados, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error al obtener productos: " . $e->getMessage());
    responderJson(["error" => "No se pudieron obtener los productos: " . $e->getMessage()], 500);
}
?>
