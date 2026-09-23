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
    $categorias = $firestore->obtenerColeccion("categorias");

    // Construir mapa de umbrales FIFO por categoría
    $mapaCategorias = [];
    foreach ($categorias as $c) {
        $cId = (int)($c["id"] ?? $c["_id"] ?? 0);
        $cNom = (string)($c["nombre"] ?? "");
        $dRojo = isset($c["dias_rojo"]) ? max(1, (int)$c["dias_rojo"]) : 45;
        $dAmarillo = isset($c["dias_amarillo"]) ? max($dRojo + 1, (int)$c["dias_amarillo"]) : 90;
        
        $catData = [
            "id" => $cId,
            "nombre" => $cNom,
            "dias_rojo" => $dRojo,
            "dias_amarillo" => $dAmarillo
        ];
        if ($cId > 0) $mapaCategorias[$cId] = $catData;
        if ($cNom !== "") $mapaCategorias[$cNom] = $catData;
    }

    $hoy = new DateTimeImmutable("today");
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

        // Obtener umbrales FIFO configurados para la categoría del producto
        $catConfig = ($categoriaId && isset($mapaCategorias[$categoriaId]))
            ? $mapaCategorias[$categoriaId]
            : ($categoriaNombre && isset($mapaCategorias[$categoriaNombre]) ? $mapaCategorias[$categoriaNombre] : null);

        $diasRojo = $catConfig ? $catConfig["dias_rojo"] : 45;
        $diasAmarillo = $catConfig ? $catConfig["dias_amarillo"] : 90;

        // Calcular estado de semáforo del producto
        $estadoSemaforo = "sin_fecha";
        $diasRestantes = null;
        if ($fechaVenc !== null && $fechaVenc !== "") {
            $dtVenc = DateTimeImmutable::createFromFormat("Y-m-d", substr($fechaVenc, 0, 10));
            if ($dtVenc) {
                $diferenciaSegundos = $dtVenc->getTimestamp() - $hoy->getTimestamp();
                $diasRestantes = (int) ceil($diferenciaSegundos / 86400);

                if ($diasRestantes <= $diasRojo) {
                    $estadoSemaforo = "rojo";
                } elseif ($diasRestantes <= $diasAmarillo) {
                    $estadoSemaforo = "amarillo";
                } else {
                    $estadoSemaforo = "verde";
                }
            }
        }

        // Normalizar valores para coincidencia flexible y robusta
        $cbNorm = trim(strval($codigoBarras));
        $codNorm = trim(strval($codigo));
        $idStr = strval($id);
        $cbFiltroNorm = trim(strval($codigoBarrasFiltro));

        // Filtro por código de barras exacto o ID
        if ($cbFiltroNorm !== "") {
            $coincideCb = ($cbNorm !== "" && (strcasecmp($cbNorm, $cbFiltroNorm) === 0 || ltrim($cbNorm, "0") === ltrim($cbFiltroNorm, "0")));
            $coincideCod = ($codNorm !== "" && (strcasecmp($codNorm, $cbFiltroNorm) === 0 || ltrim($codNorm, "0") === ltrim($cbFiltroNorm, "0")));
            $coincideId = ($idStr === $cbFiltroNorm);
            if (!$coincideCb && !$coincideCod && !$coincideId) {
                continue;
            }
        }

        // Filtro de búsqueda general
        if ($busqueda !== "") {
            $nomLower = mb_strtolower($nombre);
            $provLower = mb_strtolower($proveedor);
            $codLower = mb_strtolower($codNorm);
            $cbLower = mb_strtolower($cbNorm);
            $catLower = mb_strtolower($categoriaNombre);
            $descLower = mb_strtolower($descripcion);
            if (!str_contains($nomLower, $busqueda) && 
                !str_contains($provLower, $busqueda) && 
                !str_contains($codLower, $busqueda) && 
                !str_contains($cbLower, $busqueda) && 
                !str_contains($catLower, $busqueda) &&
                !str_contains($descLower, $busqueda) &&
                $idStr !== $busqueda) {
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
        if ($semaforo !== "" && $estadoSemaforo !== $semaforo) {
            continue;
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
            "permite_venta_unidad" => isset($p["permite_venta_unidad"]) ? (bool)$p["permite_venta_unidad"] : ($pres === "unidad"),
            "unidades_por_bulto" => $unidadesBulto,
            "fecha_vencimiento" => $fechaVenc,
            "dias_restantes_vencimiento" => $diasRestantes,
            "semaforo_estado" => $estadoSemaforo,
            "dias_rojo" => $diasRojo,
            "dias_amarillo" => $diasAmarillo,
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
