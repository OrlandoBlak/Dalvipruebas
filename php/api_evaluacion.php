<?php
ob_start();
error_reporting(0);
session_start();

$roles = ['Administrador', 'Usuario'];
if (!isset($_SESSION['id']) || !in_array($_SESSION['rol'], $roles)) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}
require_once "../config/conexion.php";
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Detectar FK área
function detectarFK($conexion, $tabla, $buscar) {
    $res = $conexion->query("SHOW COLUMNS FROM `$tabla`");
    while ($col = $res->fetch_assoc()) {
        if (stripos($col['Field'], $buscar) !== false) return $col['Field'];
    }
    return null;
}

// ── COLABORADORES ─────────────────────────────────────────
if ($action === 'colaboradores') {
    $fkArea = detectarFK($conexion, 'colaboradores', 'area') ?? 'FK_Id_Area';
    $cargoCol = detectarFK($conexion, 'colaboradores', 'cargo')
             ?? detectarFK($conexion, 'colaboradores', 'puest') ?? null;
    $selCargo = $cargoCol ? "c.`$cargoCol` AS Cargo" : "'' AS Cargo";

    $res = $conexion->query("
        SELECT c.Id_Colaborador, c.Nombre, $selCargo,
               a.Id_Area, a.Nombre AS area_nombre
        FROM colaboradores c
        LEFT JOIN areas a ON a.Id_Area = c.`$fkArea`
        ORDER BY a.Nombre ASC, c.Nombre ASC
    ");
    if (!$res) { echo json_encode(['success'=>false,'error'=>$conexion->error]); exit(); }
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit();
}

// ── CRITERIOS ─────────────────────────────────────────────
if ($action === 'criterios') {
    $res = $conexion->query("SELECT Id_Criterios, Nombre_Criterio, Evaluando FROM puntos ORDER BY Nombre_Criterio ASC");
    if (!$res) { echo json_encode(['success'=>false,'error'=>$conexion->error]); exit(); }
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit();
}

// ── GUARDAR EVALUACIÓN ────────────────────────────────────
if ($action === 'guardar') {
    $id_colaborador    = (int)($_POST['id_colaborador']    ?? 0);
    $criterios_txt     = trim($_POST['criterios']          ?? '');
    $evaluacion        = (float)($_POST['evaluacion']      ?? 0);
    $id_result         = (int)($_POST['id_result']         ?? 0) ?: null;
    $insignias_ids     = trim($_POST['insignias_ids']      ?? '');
    $insignias_nombres = trim($_POST['insignias_nombres']  ?? '');
    $sliders_json      = $_POST['sliders'] ?? '{}';
    $sliders           = json_decode($sliders_json, true) ?: [];
    $razones_txt       = trim($_POST['razones'] ?? '');

    $obs_observacion = trim($_POST['observacion']  ?? '');
    $obs_puntos      = trim($_POST['puntos']       ?? '');
    $obs_pendientes  = trim($_POST['pendientes']   ?? '');
    $obs_comentarios = trim($_POST['comentarios']  ?? '');

    if ($id_colaborador <= 0) {
        echo json_encode(['success'=>false,'error'=>'Selecciona un colaborador.']); exit();
    }
    if (empty($criterios_txt)) {
        echo json_encode(['success'=>false,'error'=>'Selecciona al menos un criterio.']); exit();
    }

    // Calcular promedio y nivel
    $prom_sliders = !empty($sliders) ? array_sum($sliders) / count($sliders) : (float)$evaluacion;
    $pct = ($prom_sliders / 10) * 100;

    if ($pct >= 90)     $id_estadistica = 1;
    elseif ($pct >= 75) $id_estadistica = 2;
    elseif ($pct >= 60) $id_estadistica = 3;
    else                $id_estadistica = 4;

    // Id_Insignia
    $id_insignia = null;
    if (!empty($insignias_ids)) {
        $insArr = array_filter(array_map('intval', explode(',', $insignias_ids)));
        $id_insignia = !empty($insArr) ? $insArr[0] : null;
    }

    // INSERT observaciones
    $stmt_obs = $conexion->prepare(
        "INSERT INTO observaciones (Observacion, Puntos, Pendientes, Comentarios) VALUES (?, ?, ?, ?)"
    );
    $stmt_obs->bind_param("ssss", $obs_observacion, $obs_puntos, $obs_pendientes, $obs_comentarios);
    if (!$stmt_obs->execute()) {
        echo json_encode(['success'=>false,'error'=>'Error observaciones: '.$conexion->error]); exit();
    }
    $id_observacion = $conexion->insert_id;

    // INSERT evaluaciones
    $nivel_float = round($prom_sliders, 1);
    $stmt_eval = $conexion->prepare("
        INSERT INTO evaluaciones
            (Id_Colaborador, Criterios, Evaluacion, Nivel, Id_Observacion, Id_Result, Id_Insignia)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_eval->bind_param("isddiii",
        $id_colaborador, $criterios_txt, $evaluacion,
        $nivel_float, $id_observacion, $id_result, $id_insignia
    );
    if (!$stmt_eval->execute()) {
        echo json_encode(['success'=>false,'error'=>'Error evaluacion: '.$conexion->error]); exit();
    }
    $id_evaluacion = $conexion->insert_id;

    // ── GUARDAR CRITERIOS INDIVIDUALES ────────────────────
    if ($id_evaluacion && !empty($sliders)) {
        foreach ($sliders as $nombre_criterio => $valor_norm) {
            // Obtener valor máximo del criterio desde tabla puntos
            $stmtC = $conexion->prepare(
                "SELECT Evaluando FROM puntos WHERE Nombre_Criterio = ? LIMIT 1"
            );
            $stmtC->bind_param("s", $nombre_criterio);
            $stmtC->execute();
            $crit = $stmtC->get_result()->fetch_assoc();
            $valor_maximo = $crit ? (float)$crit['Evaluando'] : 10;

            // valor_norm ya viene normalizado a /10 desde el JS
            $valor_real = round(($valor_norm / 10) * $valor_maximo, 2);
            $pct_crit   = $valor_maximo > 0 ? round(($valor_real / $valor_maximo) * 100, 1) : 0;

            // Guardar como JSON legible
            $datos = json_encode([
                'criterio' => $nombre_criterio,
                'actual'   => $valor_real,
                'maximo'   => $valor_maximo,
                'pct'      => $pct_crit
            ], JSON_UNESCAPED_UNICODE);

            $stmtCR = $conexion->prepare(
                "INSERT INTO criterios_resultados (Id_Evaluacion, Datos_Guardado) VALUES (?, ?)"
            );
            $stmtCR->bind_param("is", $id_evaluacion, $datos);
            $stmtCR->execute();
        }
    }

    // INSERT reportes
    $id_dalvi    = (int)$_SESSION['id'];
    $descripcion = "Evaluación #$id_evaluacion - " . date('d/m/Y H:i');
    $stmt_rep = $conexion->prepare("
        INSERT INTO reportes (Descripcion, Id_Estadistica, Id_Dalvi, Id_Colaborador, Id_Evaluacion)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_rep->bind_param("siiii", $descripcion, $id_estadistica, $id_dalvi, $id_colaborador, $id_evaluacion);
    $stmt_rep->execute();

    // Guardar razones en tabla razones
    if ($id_evaluacion && !empty($razones_txt)) {
        $stmtRaz = $conexion->prepare(
            "INSERT INTO razones (Objetivo) VALUES (?)"
        );
        if ($stmtRaz) {
            $stmtRaz->bind_param("s", $razones_txt);
            $stmtRaz->execute();
            $id_razon = $conexion->insert_id;
            // Enlazar a evaluacion si la tabla tiene Id_Razon en evaluaciones
            if ($id_razon) {
                $conexion->query("UPDATE evaluaciones SET Id_Razon = $id_razon WHERE Id_Evaluacion = $id_evaluacion");
            }
        }
    }

    echo json_encode([
        'success'           => true,
        'id_evaluacion'     => $id_evaluacion,
        'nivel'             => $nivel_float,
        'porcentaje'        => round($pct, 1),
        'estadistica'       => $id_estadistica,
        'insignias_nombres' => $insignias_nombres,
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida', 'action' => $action]);