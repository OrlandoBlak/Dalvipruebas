<?php
ob_start();
error_reporting(0);
// php/api_criterios.php
// GET  ?action=list          → lista todos los criterios
// POST action=crear          → inserta nuevo criterio
// POST action=editar         → actualiza criterio
// POST action=eliminar       → elimina criterio

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

// ── LISTAR ────────────────────────────────────────────────
if ($action === 'list') {
    $res  = $conexion->query("SELECT * FROM puntos ORDER BY Id_Criterios ASC");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// ── CREAR ─────────────────────────────────────────────────
if ($action === 'crear') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $evaluando = trim($_POST['evaluando'] ?? '');

    if (empty($nombre)) {
        echo json_encode(['success' => false, 'error' => 'El nombre del criterio es obligatorio.']); exit();
    }
    if (!is_numeric($evaluando) || (float)$evaluando < 0) {
        echo json_encode(['success' => false, 'error' => 'El puntaje debe ser un número válido mayor o igual a 0.']); exit();
    }

    $evaluando = (float)$evaluando;
    $stmt = $conexion->prepare("INSERT INTO puntos (Nombre_Criterio, Evaluando) VALUES (?, ?)");
    $stmt->bind_param("sd", $nombre, $evaluando);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'data'    => [
                'Id_Criterios'    => $conexion->insert_id,
                'Nombre_Criterio' => $nombre,
                'Evaluando'       => $evaluando,
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $conexion->error]);
    }
    exit();
}

// ── EDITAR ────────────────────────────────────────────────
if ($action === 'editar') {
    $id        = (int)($_POST['id'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $evaluando = trim($_POST['evaluando'] ?? '');

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit();
    }
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio.']); exit();
    }
    if (!is_numeric($evaluando) || (float)$evaluando < 0) {
        echo json_encode(['success' => false, 'error' => 'Puntaje inválido.']); exit();
    }

    $evaluando = (float)$evaluando;
    $stmt = $conexion->prepare("UPDATE puntos SET Nombre_Criterio = ?, Evaluando = ? WHERE Id_Criterios = ?");
    $stmt->bind_param("sdi", $nombre, $evaluando, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar: ' . $conexion->error]);
    }
    exit();
}

// ── ELIMINAR ──────────────────────────────────────────────
if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit();
    }

    $stmt = $conexion->prepare("DELETE FROM puntos WHERE Id_Criterios = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar: ' . $conexion->error]);
    }
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);