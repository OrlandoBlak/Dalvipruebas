<?php
// php/api_colaboradores.php
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

// Detectar columnas dinámicamente
function detectarCol($conexion, $tabla, $buscar) {
    $res = $conexion->query("SHOW COLUMNS FROM `$tabla`");
    while ($col = $res->fetch_assoc()) {
        if (stripos($col['Field'], $buscar) !== false) return $col['Field'];
    }
    return null;
}

$fkArea  = detectarCol($conexion, 'colaboradores', 'area')  ?? 'FK_Id_Area';
$colCargo= detectarCol($conexion, 'colaboradores', 'cargo')
        ?? detectarCol($conexion, 'colaboradores', 'puesto') ?? null;

// ── OBTENER UN COLABORADOR ────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit(); }

    $cargo = $colCargo ? "c.`$colCargo` AS Cargo" : "'' AS Cargo";
    $stmt  = $conexion->prepare("
        SELECT c.Id_Colaborador, c.Nombre, $cargo, c.`$fkArea` AS Id_Area
        FROM colaboradores c WHERE c.Id_Colaborador = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) echo json_encode(['success'=>true,'data'=>$row]);
    else      echo json_encode(['success'=>false,'error'=>'No encontrado']);
    exit();
}

// ── EDITAR ────────────────────────────────────────────
if ($action === 'editar') {
    $id      = (int)($_POST['id']      ?? 0);
    $nombre  = trim($_POST['nombre']   ?? '');
    $cargo   = trim($_POST['cargo']    ?? '');
    $id_area = (int)($_POST['id_area'] ?? 0);

    if ($id <= 0)        { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit(); }
    if (empty($nombre))  { echo json_encode(['success'=>false,'error'=>'El nombre es obligatorio']); exit(); }
    if ($id_area <= 0)   { echo json_encode(['success'=>false,'error'=>'Selecciona un área']); exit(); }

    if ($colCargo) {
        $stmt = $conexion->prepare("UPDATE colaboradores SET Nombre=?, `$colCargo`=?, `$fkArea`=? WHERE Id_Colaborador=?");
        $stmt->bind_param("ssii", $nombre, $cargo, $id_area, $id);
    } else {
        $stmt = $conexion->prepare("UPDATE colaboradores SET Nombre=?, `$fkArea`=? WHERE Id_Colaborador=?");
        $stmt->bind_param("sii", $nombre, $id_area, $id);
    }

    if ($stmt->execute()) echo json_encode(['success'=>true]);
    else echo json_encode(['success'=>false,'error'=>$conexion->error]);
    exit();
}

// ── ELIMINAR ──────────────────────────────────────────
if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit(); }

    $stmt = $conexion->prepare("DELETE FROM colaboradores WHERE Id_Colaborador=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) echo json_encode(['success'=>true]);
    else echo json_encode(['success'=>false,'error'=>$conexion->error]);
    exit();
}

// ── CREAR ─────────────────────────────────────────────
if ($action === 'crear') {
    $nombre  = trim($_POST['nombre']   ?? '');
    $id_area = (int)($_POST['id_area'] ?? 0);
    if (empty($nombre))  { echo json_encode(['success'=>false,'error'=>'Nombre obligatorio']); exit(); }
    if ($id_area <= 0)   { echo json_encode(['success'=>false,'error'=>'Selecciona un área']); exit(); }
    $stmt = $conexion->prepare("INSERT INTO colaboradores (Nombre, `$fkArea`) VALUES (?, ?)");
    $stmt->bind_param("si", $nombre, $id_area);
    if ($stmt->execute()) echo json_encode(['success'=>true,'id'=>$conexion->insert_id]);
    else echo json_encode(['success'=>false,'error'=>$conexion->error]);
    exit();
}

// ── ÁREAS ─────────────────────────────────────────────
if ($action === 'areas') {
    $res  = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success'=>true,'data'=>$rows]);
    exit();
}

http_response_code(400);
echo json_encode(['error'=>'Acción no válida']);