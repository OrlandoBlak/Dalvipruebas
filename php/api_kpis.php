<?php
ob_start();
error_reporting(0);
session_start();

/* ─────────────────────────────────────────────
   PERMISOS
───────────────────────────────────────────── */
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


/* ─────────────────────────────────────────────
   LISTAR KPIs
───────────────────────────────────────────── */
if ($action === 'list') {

    $sql = "
        SELECT 
            k.Id_KPs,
            k.Nombre,
            k.Tipo,
            k.Id_Area,
            k.Metas,
            a.Nombre AS area_nombre
        FROM kps k
        INNER JOIN areas a ON a.Id_Area = k.Id_Area
        ORDER BY a.Nombre ASC, k.Nombre ASC
    ";

    $res = $conexion->query($sql);

    if (!$res) {
        echo json_encode(['success' => false, 'error' => $conexion->error]);
        exit();
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}


/* ─────────────────────────────────────────────
   ÁREAS
───────────────────────────────────────────── */
if ($action === 'areas') {

    $res = $conexion->query("
        SELECT Id_Area, Nombre
        FROM areas
        ORDER BY Nombre ASC
    ");

    if (!$res) {
        echo json_encode(['success' => false, 'error' => $conexion->error]);
        exit();
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}


/* ─────────────────────────────────────────────
   KPIs POR ÁREA
───────────────────────────────────────────── */
if ($action === 'kps_area') {

    $id_area = (int)($_GET['id_area'] ?? 0);

    if ($id_area <= 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    $stmt = $conexion->prepare("
        SELECT 
            k.Id_KPs,
            k.Nombre,
            k.Tipo,
            k.Metas,
            COALESCE(r.Dato_Ingresado, 0) AS Dato_Ingresado,
            r.Id_Result
        FROM kps k
        LEFT JOIN (
            SELECT Id_KPs, MAX(Id_Result) AS maxR
            FROM resultados
            GROUP BY Id_KPs
        ) rm ON rm.Id_KPs = k.Id_KPs
        LEFT JOIN resultados r ON r.Id_Result = rm.maxR
        WHERE k.Id_Area = ?
        ORDER BY k.Id_KPs ASC
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conexion->error]);
        exit();
    }

    $stmt->bind_param("i", $id_area);
    $stmt->execute();

    $res = $stmt->get_result();
    $rows = [];

    while ($r = $res->fetch_assoc()) $rows[] = $r;

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}


/* ─────────────────────────────────────────────
   CREAR KPI
───────────────────────────────────────────── */
if ($action === 'crear') {

    $nombre  = trim($_POST['nombre'] ?? '');
    $tipo    = trim($_POST['tipo'] ?? '');
    $id_area = (int)($_POST['id_area'] ?? 0);
    $metas   = (float)($_POST['metas'] ?? 0);

    if (empty($nombre)) {
        echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
        exit();
    }

    if ($id_area <= 0) {
        echo json_encode(['success' => false, 'error' => 'Selecciona un área']);
        exit();
    }

    if ($metas <= 0) {
        echo json_encode(['success' => false, 'error' => 'La meta debe ser mayor a 0']);
        exit();
    }

    $stmt = $conexion->prepare("
        INSERT INTO kps (Nombre, Tipo, Id_Area, Metas)
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conexion->error]);
        exit();
    }

    $stmt->bind_param("ssid", $nombre, $tipo, $id_area, $metas);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'id' => $conexion->insert_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $conexion->error
        ]);
    }

    exit();
}


/* ─────────────────────────────────────────────
   EDITAR KPI
───────────────────────────────────────────── */
if ($action === 'editar') {

    $id      = (int)($_POST['id'] ?? 0);
    $nombre  = trim($_POST['nombre'] ?? '');
    $tipo    = trim($_POST['tipo'] ?? '');
    $id_area = (int)($_POST['id_area'] ?? 0);
    $metas   = (float)($_POST['metas'] ?? 0);

    $stmt = $conexion->prepare("
        UPDATE kps
        SET Nombre=?, Tipo=?, Id_Area=?, Metas=?
        WHERE Id_KPs=?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conexion->error]);
        exit();
    }

    $stmt->bind_param("ssidi", $nombre, $tipo, $id_area, $metas, $id);

    echo json_encode(
        $stmt->execute()
            ? ['success' => true]
            : ['success' => false, 'error' => $conexion->error]
    );

    exit();
}


/* ─────────────────────────────────────────────
   GUARDAR RESULTADO
───────────────────────────────────────────── */
if ($action === 'guardar_resultado') {

    $id_kps    = (int)($_POST['id_kps'] ?? 0);
    $dato      = (float)($_POST['dato'] ?? 0);
    $id_result = (int)($_POST['id_result'] ?? 0);

    if ($id_kps <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID KPI inválido']);
        exit();
    }

    $metaStmt = $conexion->prepare("
        SELECT Metas
        FROM kps
        WHERE Id_KPs=?
    ");

    $metaStmt->bind_param("i", $id_kps);
    $metaStmt->execute();

    $kp = $metaStmt->get_result()->fetch_assoc();

    if (!$kp) {
        echo json_encode(['success' => false, 'error' => 'KPI no encontrado']);
        exit();
    }

    $meta = (float)$kp['Metas'];
    $dato = min($dato, $meta);

    if ($id_result > 0) {

        $stmt = $conexion->prepare("
            UPDATE resultados
            SET Dato_Ingresado=?
            WHERE Id_Result=? AND Id_KPs=?
        ");

        $stmt->bind_param("dii", $dato, $id_result, $id_kps);
        $stmt->execute();

        $newId = $id_result;

    } else {

        $stmt = $conexion->prepare("
            INSERT INTO resultados (Id_KPs, Dato_Ingresado)
            VALUES (?, ?)
        ");

        $stmt->bind_param("id", $id_kps, $dato);
        $stmt->execute();

        $newId = $conexion->insert_id;
    }

    $pct = $meta > 0 ? round(($dato / $meta) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'id_result' => $newId,
        'dato' => $dato,
        'porcentaje' => $pct
    ]);

    exit();
}


/* ─────────────────────────────────────────────
   ELIMINAR KPI
───────────────────────────────────────────── */
if ($action === 'eliminar') {

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $stmt = $conexion->prepare("
        DELETE FROM kps
        WHERE Id_KPs=?
    ");

    $stmt->bind_param("i", $id);

    echo json_encode(
        $stmt->execute()
            ? ['success' => true]
            : ['success' => false, 'error' => $conexion->error]
    );

    exit();
}


/* ─────────────────────────────────────────────
   ACCIÓN INVÁLIDA
───────────────────────────────────────────── */
http_response_code(400);

echo json_encode([
    'success' => false,
    'error' => 'Acción no válida',
    'action' => $action
]);

?>