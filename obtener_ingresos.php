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

    $mapaUsuarios = [];
    foreach ($todosLosUsuarios as $u) {
        $uId = (int) ($u["id"] ?? $u["_id"] ?? 0);
        if ($uId > 0) {
            $mapaUsuarios[$uId] = trim(($u["nombre"] ?? "") . " " . ($u["apellido"] ?? ""));
        }
    }

    $hoy = new DateTimeImmutable("today");
    $limite45 = $hoy->modify("+45 days");
    $limite90 = $hoy->modify("+90 days");

    $ingresosFiltrados = [];

    foreach ($todosLosIngresos as $i) {
        $id = (int) ($i["id"] ?? $i["_id"] ?? 0);
        $prodId = (int) ($i["producto_id"] ?? 0);
        $uId = (int) ($i["usuario_id"] ?? 0);
        $prov = (string) ($i["proveedor"] ?? "");
        $fecha = (string) ($i["fecha"] ?? "");
        $fechaVenc = !empty($i["fecha_vencimiento"]) ? (string)$i["fecha_vencimiento"] : null;

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

        // Filtro semáforo
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
            "fecha_vencimiento" => $fechaVenc,
            "usuario_id" => $uId,
            "motivo" => (string) ($i["motivo"] ?? ""),
            "fecha" => $fecha,
            "usuario" => $mapaUsuarios[$uId] ?? ""
        ];
    }

    usort($ingresosFiltrados, function ($a, $b) {
        $cmp = strcmp($b["fecha"], $a["fecha"]);
        if ($cmp !== 0) return $cmp;
        return $b["id"] <=> $a["id"];
    });

    echo json_encode($ingresosFiltrados, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en obtener_ingresos (Firestore): " . $e->getMessage());
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>
