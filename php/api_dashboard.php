<?php
ob_start();
error_reporting(0);
// php/api_dashboard.php
// Devuelve JSON con datos pesados (deptos + top colab) DESPUÉS del paint inicial

session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}
require_once "../config/conexion.php";
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// ── Departamentos con conteo y promedio ───────────────────
if ($action === 'deptos') {
    $res = $conexion->query("
        SELECT
            a.Id_Area,
            COUNT(DISTINCT c.Id_Colaborador) AS total_colab,
            ROUND(AVG(e.Evaluacion), 1)       AS promedio
        FROM areas a
        LEFT JOIN colaboradores c ON c.FK_Id_Area = a.Id_Area
        LEFT JOIN evaluaciones  e ON e.FK_Id_Colaborador = c.Id_Colaborador
        GROUP BY a.Id_Area
        ORDER BY a.Id_Area ASC
    ");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

// ── Top 5 colaboradores ───────────────────────────────────
if ($action === 'top') {
    $res = $conexion->query("
        SELECT
            col.Id_Colaborador,
            col.Nombre,
            a.Nombre        AS area_nombre,
            ROUND(AVG(e.Evaluacion), 1) AS promedio
        FROM colaboradores col
        LEFT JOIN areas       a ON a.Id_Area            = col.FK_Id_Area
        LEFT JOIN evaluaciones e ON e.FK_Id_Colaborador = col.Id_Colaborador
        GROUP BY col.Id_Colaborador, col.Nombre, a.Nombre
        ORDER BY promedio DESC
        LIMIT 5
    ");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);