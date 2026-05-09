<?php
ob_start();
error_reporting(0);
// php/colaboradores.php
// Maneja: GET ?action=areas  → devuelve áreas en JSON
//         POST               → guarda colaborador nuevo

session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}
require_once "../config/conexion.php";

ob_end_clean();
header('Content-Type: application/json');

// ── GET: obtener lista de áreas ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'areas') {
    $res = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
    $areas = [];
    while ($row = $res->fetch_assoc()) {
        $areas[] = $row;
    }
    echo json_encode(['success' => true, 'areas' => $areas]);
    exit();
}

// ── POST: guardar colaborador ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre  = trim($_POST['nombre'] ?? '');
    $id_area = (int)($_POST['id_area'] ?? 0);

    // Validaciones
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio.']);
        exit();
    }
    if ($id_area <= 0) {
        echo json_encode(['success' => false, 'error' => 'Selecciona un área válida.']);
        exit();
    }

    // Verificar que el área existe
    $checkArea = $conexion->prepare("SELECT Id_Area FROM areas WHERE Id_Area = ?");
    $checkArea->bind_param("i", $id_area);
    $checkArea->execute();
    if ($checkArea->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'El área seleccionada no existe.']);
        exit();
    }

    // Insertar
    $stmt = $conexion->prepare("INSERT INTO colaboradores (Nombre, FK_Id_Area) VALUES (?, ?)");
    $stmt->bind_param("si", $nombre, $id_area);

    if ($stmt->execute()) {
        $nuevoId = $conexion->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Colaborador agregado correctamente.',
            'id'      => $nuevoId,
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $conexion->error]);
    }

    $stmt->close();
    exit();
}

// Método no permitido
http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);