<?php
ob_start();
error_reporting(0);
// php/api_evaluacion.php
session_start();
$rolesPermitidos = ['Administrador', 'Usuario'];
if (!isset($_SESSION['id']) || !in_array($_SESSION['rol'], $rolesPermitidos)) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}
require_once "../config/conexion.php";
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── DETECTAR COLUMNAS DINÁMICAMENTE ──────────────────────
function detectarFK($conexion, $tabla, $buscar) {
    $res = $conexion->query("SHOW COLUMNS FROM `$tabla`");
    while ($col = $res->fetch_assoc()) {
        if (stripos($col['Field'], $buscar) !== false) return $col['Field'];
    }
    return null;
}

// ── COLABORADORES ─────────────────────────────────────────
if ($action === 'colaboradores') {

    // Detectar columna FK de área en colaboradores
    $fkArea  = detectarFK($conexion, 'colaboradores', 'area') ?? 'FK_Id_Area';
    // Detectar columna de cargo
    $colCargo = detectarFK($conexion, 'colaboradores', 'cargo')
             ?? detectarFK($conexion, 'colaboradores', 'puesto')
             ?? null;
    $selectCargo = $colCargo ? "c.`$colCargo` AS Cargo" : "'' AS Cargo";

    $sql = "
        SELECT c.Id_Colaborador, c.Nombre, $selectCargo,
               a.Id_Area, a.Nombre AS area_nombre
        FROM colaboradores c
        LEFT JOIN areas a ON a.Id_Area = c.`$fkArea`
        ORDER BY a.Nombre ASC, c.Nombre ASC
    ";
    $res = $conexion->query($sql);
    if (!$res) {
        echo json_encode(['success'=>false,'error'=>$conexion->error,'sql'=>$sql]);
        exit();
    }
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// ── CRITERIOS (tabla puntos) ──────────────────────────────
if ($action === 'criterios') {
    $res = $conexion->query("SELECT Id_Criterios, Nombre_Criterio, Evaluando FROM puntos ORDER BY Nombre_Criterio ASC");
    if (!$res) {
        echo json_encode(['success'=>false,'error'=>$conexion->error]);
        exit();
    }
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// ── KPS POR ÁREA ──────────────────────────────────────────
if ($action === 'kps_area') {
    $id_area = (int)($_GET['id_area'] ?? 0);
    if ($id_area <= 0) { echo json_encode(['success'=>true,'data'=>null]); exit(); }

    // Detectar FK de área en kps
    $fkKps = detectarFK($conexion, 'kps', 'area') ?? 'Id_Area';
    $stmt  = $conexion->prepare("SELECT k.Id_KPs, k.Metas, k.Pedidos FROM kps k WHERE k.`$fkKps` = ? LIMIT 1");
    $stmt->bind_param("i", $id_area);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $row]);
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

    $obs_observacion = trim($_POST['observacion']  ?? '');
    $obs_puntos      = trim($_POST['puntos']       ?? '');
    $obs_pendientes  = trim($_POST['pendientes']   ?? '');
    $obs_comentarios = trim($_POST['comentarios']  ?? '');

    $sliders_json = $_POST['sliders'] ?? '{}';
    $sliders      = json_decode($sliders_json, true) ?: [];

    if ($id_colaborador <= 0) {
        echo json_encode(['success'=>false,'error'=>'Selecciona un colaborador.']); exit();
    }
    if (empty($criterios_txt)) {
        echo json_encode(['success'=>false,'error'=>'Selecciona al menos un criterio.']); exit();
    }

    // Calcular % y Nivel
    $prom_sliders = !empty($sliders) ? array_sum($sliders) / count($sliders) : (float)$evaluacion;
    $pct = ($prom_sliders / 10) * 100;

    if ($pct >= 90)      $id_estadistica = 1;
    elseif ($pct >= 75)  $id_estadistica = 2;
    elseif ($pct >= 60)  $id_estadistica = 3;
    else                 $id_estadistica = 4;

    // Tomar primer Id_Insignia de la lista
    $id_insignia = null;
    if (!empty($insignias_ids)) {
        $insArr      = array_filter(array_map('intval', explode(',', $insignias_ids)));
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

    // INSERT evaluaciones — nueva estructura con Id_Result e Id_Insignia
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

    // INSERT reportes automático
    $id_dalvi    = (int)$_SESSION['id'];
    $descripcion = "Evaluación #$id_evaluacion - " . date('d/m/Y H:i');
    $stmt_rep = $conexion->prepare("
        INSERT INTO reportes (Descripcion, Id_Estadistica, Id_Dalvi, Id_Colaborador, Id_Evaluacion)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_rep->bind_param("siiii", $descripcion, $id_estadistica, $id_dalvi, $id_colaborador, $id_evaluacion);
    $stmt_rep->execute();

    echo json_encode([
        'success'            => true,
        'id_evaluacion'      => $id_evaluacion,
        'nivel'              => $nivel_float,
        'porcentaje'         => round($pct, 1),
        'estadistica'        => $id_estadistica,
        'insignias_nombres'  => $insignias_nombres,
    ]);
    exit();
}

// ── DEBUG: verificar que el API responde ──────────────────
if ($action === 'ping') {
    echo json_encode(['success'=>true,'session'=>$_SESSION,'msg'=>'API funcionando']);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida', 'action_recibida' => $action]);