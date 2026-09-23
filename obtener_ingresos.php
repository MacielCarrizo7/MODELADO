<?php
require_once __DIR__ . "/seguridad.php";
require_once __DIR__ . "/FirestoreConexion.php";
requerirUsuarioJson(["admin", "vendedor"]);
header("Content-Type: application/json; charset=UTF-8");

$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");
$productoIdTexto = trim($_GET["producto_id"] ?? "");
$proveedor = mb_strtolower(trim($_GET["proveedor"] ?? ""));
$usuarioIdTexto = trim($_GET["usuario_id"] ?? "");
$semaforo = trim($_GET["semaforo"] ?? "");
$numeroFacturaFiltro = mb_strtolower(trim($_GET["numero_factura"] ?? $_GET["factura"] ?? ""));

if (($desde !== "" && !fechaIsoValida($desde)) || ($hasta !== "" && !fechaIsoValida($hasta))) {
    responderJson(["error" => "Ingresá fechas válidas."], 400);
}
if ($desde !== "" && $hasta !== "" && $desde > $hasta) {
    responderJson(["error" => "La fecha desde no puede ser posterior a la fecha hasta."], 400);
}

$prodIdFiltro = ($productoIdTexto !== "" && ctype_digit($productoIdTexto)) ? (int)$productoIdTexto : null;
$usuIdFiltro = ($usuarioIdTexto !== "" && ctype_digit($usuarioIdTexto)) ? (int)$usuarioIdTexto : null;

try {
    $firestore = FirestoreConexion::obtenerFirestore();
    $todosLosIngresos = $firestore->obtenerColeccion("ingresos_stock");
    $todosLosUsuarios = $firestore->obtenerColeccion("usuarios");
    $todosLosProductos = $firestore->obtenerColeccion("productos");
    $todasLasCategorias = $firestore->obtenerColeccion("categorias");

    $mapaUsuarios = [];
    foreach ($todosLosUsuarios as $u) {
        $uId = (int) ($u["id"] ?? $u["_id"] ?? 0);
        if ($uId > 0) {
            $mapaUsuarios[$uId] = trim(($u["nombre"] ?? "") . " " . ($u["apellido"] ?? ""));
        }
    }

    $mapaCategorias = [];
    foreach ($todasLasCategorias as $c) {
        $cId = (int)($c["id"] ?? $c["_id"] ?? 0);
        $cNom = (string)($c["nombre"] ?? "");
        $dRojo = isset($c["dias_rojo"]) ? max(1, (int)$c["dias_rojo"]) : 45;
        $dAmarillo = isset($c["dias_amarillo"]) ? max($dRojo + 1, (int)$c["dias_amarillo"]) : 90;
        $catData = ["dias_rojo" => $dRojo, "dias_amarillo" => $dAmarillo];
        if ($cId > 0) $mapaCategorias[$cId] = $catData;
        if ($cNom !== "") $mapaCategorias[$cNom] = $catData;
    }

    $mapaProdCategoria = [];
    foreach ($todosLosProductos as $p) {
        $pId = (int)($p["id"] ?? $p["_id"] ?? 0);
        $catId = isset($p["categoria_id"]) ? (int)$p["categoria_id"] : null;
        $catNom = (string)($p["categoria_nombre"] ?? $p["categoria"] ?? "");
        if ($pId > 0) {
            $mapaProdCategoria[$pId] = ["categoria_id" => $catId, "categoria_nombre" => $catNom];
        }
    }

    $hoy = new DateTimeImmutable("today");
    $ingresosFiltrados = [];

    foreach ($todosLosIngresos as $i) {
        $id = (int) ($i["id"] ?? $i["_id"] ?? 0);
        $prodId = (int) ($i["producto_id"] ?? 0);
        $uId = (int) ($i["usuario_id"] ?? 0);
        $prov = (string) ($i["proveedor"] ?? "");
        $fecha = (string) ($i["fecha"] ?? "");
        $fechaVenc = !empty($i["fecha_vencimiento"]) ? (string)$i["fecha_vencimiento"] : null;
        $numFacturaDoc = (string) ($i["numero_factura"] ?? "");
        $sinFacturaDoc = !empty($i["sin_factura"]);

        // Filtro desde
        if ($desde !== "" && $fecha !== "" && substr($fecha, 0, 10) < $desde) {
            continue;
        }

        // Filtro hasta
        if ($hasta !== "" && $fecha !== "" && substr($fecha, 0, 10) > $hasta) {
            continue;
        }

        // Filtro producto
        if ($prodIdFiltro !== null && $prodId !== $prodIdFiltro) {
            continue;
        }

        // Filtro usuario
        if ($usuIdFiltro !== null && $uId !== $usuIdFiltro) {
            continue;
        }

        // Filtro proveedor
        if ($proveedor !== "" && !str_contains(mb_strtolower($prov), $proveedor)) {
            continue;
        }

        // Filtro N° Factura
        if ($numeroFacturaFiltro !== "") {
            $numFacturaLower = mb_strtolower($numFacturaDoc);
            $matchFact = str_contains($numFacturaLower, $numeroFacturaFiltro);
            if ($sinFacturaDoc && str_contains("sin factura", $numeroFacturaFiltro)) {
                $matchFact = true;
            }
            if (!$matchFact) {
                continue;
            }
        }

        // Determinar días del semáforo según categoría
        $prodInfo = $mapaProdCategoria[$prodId] ?? null;
        $catId = $prodInfo["categoria_id"] ?? null;
        $catNom = $prodInfo["categoria_nombre"] ?? "";
        $catConfig = ($catId && isset($mapaCategorias[$catId]))
            ? $mapaCategorias[$catId]
            : ($catNom && isset($mapaCategorias[$catNom]) ? $mapaCategorias[$catNom] : null);

        $diasRojo = $catConfig ? $catConfig["dias_rojo"] : 45;
        $diasAmarillo = $catConfig ? $catConfig["dias_amarillo"] : 90;

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

        // Filtro semáforo
        if ($semaforo !== "" && $estadoSemaforo !== $semaforo) {
            continue;
        }

        $ingresosFiltrados[] = [
            "id" => $id,
            "producto_id" => $prodId,
            "producto_nombre" => (string) ($i["producto_nombre"] ?? ""),
            "cantidad" => (int) ($i["cantidad"] ?? 0),
            "presentacion" => (string) ($i["presentacion"] ?? "unidad"),
            "unidades_por_bulto" => (int) ($i["unidades_por_bulto"] ?? 1),
            "total_unidades" => (int) ($i["total_unidades"] ?? 0),
            "precio_unitario" => (float) ($i["precio_unitario"] ?? 0),
            "proveedor" => $prov !== "" ? $prov : null,
            "numero_factura" => !empty($i["numero_factura"]) ? (string)$i["numero_factura"] : (!empty($i["sin_factura"]) ? "Sin Factura" : "—"),
            "sin_factura" => !empty($i["sin_factura"]),
            "fecha_vencimiento" => $fechaVenc,
            "dias_restantes_vencimiento" => $diasRestantes,
            "semaforo_estado" => $estadoSemaforo,
            "dias_rojo" => $diasRojo,
            "dias_amarillo" => $diasAmarillo,
            "usuario_id" => $uId,
            "motivo" => (string) ($i["motivo"] ?? ""),
            "fecha" => $fecha,
            "usuario" => $mapaUsuarios[$uId] ?? ""
        ];
    }

    echo json_encode($ingresosFiltrados, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error al obtener ingresos: " . $e->getMessage());
    responderJson(["error" => "No se pudieron obtener los ingresos: " . $e->getMessage()], 500);
}
?>
