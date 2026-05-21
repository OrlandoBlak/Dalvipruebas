<?php
ob_start();
error_reporting(0);
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
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
$fkCol = 'FK_Id_Area';
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($c = $cols->fetch_assoc()) {
    if (stripos($c['Field'], 'area') !== false) { $fkCol = $c['Field']; break; }
}

// ── LISTAR ────────────────────────────────────────────────
if ($action === 'list') {
    $res = $conexion->query("
        SELECT e.Id_Evaluacion, e.Criterios,
               ROUND(e.Evaluacion,1) AS promedio,
               e.Nivel, e.Id_Observacion, e.Id_Insignia,
               c.Id_Colaborador, c.Nombre AS colab_nombre,
               a.Nombre AS area_nombre,
               ins.Descripcion AS insignia_nombre,
               o.Observacion, o.Puntos, o.Pendientes, o.Comentarios
        FROM evaluaciones e
        INNER JOIN colaboradores c  ON c.Id_Colaborador  = e.Id_Colaborador
        LEFT  JOIN areas a          ON a.Id_Area          = c.`$fkCol`
        LEFT  JOIN insignias ins    ON ins.Id_Insignia    = e.Id_Insignia
        LEFT  JOIN observaciones o  ON o.Id_Observacion  = e.Id_Observacion
        ORDER BY e.Id_Evaluacion DESC
    ");
    if (!$res) { echo json_encode(['success'=>false,'error'=>$conexion->error]); exit(); }
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit();
}

// ── OBTENER UNA ───────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit(); }

    $stmt = $conexion->prepare("
        SELECT e.*, ROUND(e.Evaluacion,1) AS promedio,
               c.Nombre AS colab_nombre, c.Id_Colaborador,
               a.Nombre AS area_nombre,
               ins.Descripcion AS insignia_nombre,
               o.Observacion, o.Puntos, o.Pendientes, o.Comentarios
        FROM evaluaciones e
        INNER JOIN colaboradores c ON c.Id_Colaborador  = e.Id_Colaborador
        LEFT  JOIN areas a         ON a.Id_Area          = c.`$fkCol`
        LEFT  JOIN insignias ins   ON ins.Id_Insignia    = e.Id_Insignia
        LEFT  JOIN observaciones o ON o.Id_Observacion  = e.Id_Observacion
        WHERE e.Id_Evaluacion = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo $row
        ? json_encode(['success'=>true,'data'=>$row])
        : json_encode(['success'=>false,'error'=>'No encontrada']);
    exit();
}

// ── ELIMINAR ─────────────────────────────────────────────
if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit(); }

    // Obtener Id_Observacion antes de borrar
    $obs = $conexion->query("SELECT Id_Observacion FROM evaluaciones WHERE Id_Evaluacion = $id")->fetch_assoc();

    // Eliminar registros relacionados
    $conexion->query("DELETE FROM criterios_resultados WHERE Id_Evaluacion = $id");
    $conexion->query("DELETE FROM reportes WHERE Id_Evaluacion = $id");

    $stmt = $conexion->prepare("DELETE FROM evaluaciones WHERE Id_Evaluacion = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($obs && $obs['Id_Observacion']) {
        $idObs = (int)$obs['Id_Observacion'];
        $conexion->query("DELETE FROM observaciones WHERE Id_Observacion = $idObs");
    }

    echo json_encode(['success'=>true]);
    exit();
}

// ── ACTUALIZAR ────────────────────────────────────────────
if ($action === 'actualizar') {
    $id          = (int)($_POST['id']           ?? 0);
    $evaluacion  = (float)($_POST['evaluacion'] ?? 0);
    $criterios   = trim($_POST['criterios']     ?? '');
    $observacion = trim($_POST['observacion']   ?? '');
    $puntos      = trim($_POST['puntos']        ?? '');
    $pendientes  = trim($_POST['pendientes']    ?? '');
    $comentarios = trim($_POST['comentarios']   ?? '');
    $id_obs      = (int)($_POST['id_obs']       ?? 0);

    if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit(); }

    $stmt = $conexion->prepare("UPDATE evaluaciones SET Evaluacion=?, Criterios=? WHERE Id_Evaluacion=?");
    $stmt->bind_param("dsi", $evaluacion, $criterios, $id);
    $stmt->execute();

    if ($id_obs > 0) {
        $stmt2 = $conexion->prepare("UPDATE observaciones SET Observacion=?, Puntos=?, Pendientes=?, Comentarios=? WHERE Id_Observacion=?");
        $stmt2->bind_param("ssssi", $observacion, $puntos, $pendientes, $comentarios, $id_obs);
        $stmt2->execute();
    }

    // Actualizar criterios individuales si vienen
    $criteriosDataJson = trim($_POST['criterios_data'] ?? '');
    if (!empty($criteriosDataJson)) {
        $criteriosData = json_decode($criteriosDataJson, true);
        if (is_array($criteriosData)) {
            // Borrar anteriores
            $conexion->query("DELETE FROM criterios_resultados WHERE Id_Evaluacion = $id");
            // Insertar nuevos
            foreach ($criteriosData as $cd) {
                $datos = json_encode([
                    'criterio' => $cd['criterio'],
                    'actual'   => $cd['actual'],
                    'maximo'   => $cd['maximo'],
                    'pct'      => $cd['pct']
                ], JSON_UNESCAPED_UNICODE);
                $stmtCR = $conexion->prepare(
                    "INSERT INTO criterios_resultados (Id_Evaluacion, Datos_Guardado) VALUES (?, ?)"
                );
                $stmtCR->bind_param("is", $id, $datos);
                $stmtCR->execute();
            }
        }
    }

    echo json_encode(['success'=>true]);
    exit();
}

http_response_code(400);
echo json_encode(['error'=>'Acción no válida','action'=>$action]);