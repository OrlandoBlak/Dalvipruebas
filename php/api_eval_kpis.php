<?php
/**
 * api_eval_kpis.php
 * API para evaluación de KPIs
 * Tablas:
 * - kps
 * - resultados
 * - areas
 * - insignias
 */

ob_start();
error_reporting(0);
session_start();

/* ───────────────────────────────
   PERMISOS
─────────────────────────────── */
$rolesPermitidos = ['Administrador', 'Usuario'];

if (!isset($_SESSION['id']) || !in_array($_SESSION['rol'], $rolesPermitidos)) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once "../config/conexion.php";

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');


/* ───────────────────────────────
   KPIs POR ÁREA
─────────────────────────────── */
if ($action === 'kps_area') {

    $id_area = (int)($_GET['id_area'] ?? 0);

    if ($id_area <= 0) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit();
    }

    $stmt = $conexion->prepare("
        SELECT
            k.Id_KPs,
            k.Nombre,
            k.Tipo,
            k.Metas,
            COALESCE(r.Dato_Ingresado, 0) AS Dato_Ingreso,
            r.Id_Result
        FROM kps k
        LEFT JOIN (
            SELECT Id_KPs, MAX(Id_Result) AS ultimo
            FROM resultados
            GROUP BY Id_KPs
        ) ult ON ult.Id_KPs = k.Id_KPs
        LEFT JOIN resultados r ON r.Id_Result = ult.ultimo
        WHERE k.Id_Area = ?
        ORDER BY k.Id_KPs ASC
    ");

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error' => $conexion->error
        ]);
        exit();
    }

    $stmt->bind_param("i", $id_area);
    $stmt->execute();

    $res = $stmt->get_result();
    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
    exit();
}


/* ───────────────────────────────
   GUARDAR RESULTADO
─────────────────────────────── */
if ($action === 'guardar_resultado') {

    $id_kps    = (int)($_POST['id_kps'] ?? 0);
    $dato      = (float)($_POST['dato'] ?? 0);
    $id_result = (int)($_POST['id_result'] ?? 0);

    if ($id_kps <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'ID KPI inválido'
        ]);
        exit();
    }

    /* Obtener meta */
    $metaStmt = $conexion->prepare("
        SELECT Metas
        FROM kps
        WHERE Id_KPs = ?
    ");

    $metaStmt->bind_param("i", $id_kps);
    $metaStmt->execute();

    $kp = $metaStmt->get_result()->fetch_assoc();

    if (!$kp) {
        echo json_encode([
            'success' => false,
            'error' => 'KPI no encontrado'
        ]);
        exit();
    }

    $meta = (float)$kp['Metas'];

    /* Limitar dato al máximo */
    if ($dato > $meta) {
        $dato = $meta;
    }

    /* Actualizar */
    if ($id_result > 0) {

        $stmt = $conexion->prepare("
            UPDATE resultados
            SET Dato_Ingresado = ?
            WHERE Id_Result = ? AND Id_KPs = ?
        ");

        $stmt->bind_param("dii", $dato, $id_result, $id_kps);

        if (!$stmt->execute()) {
            echo json_encode([
                'success' => false,
                'error' => $conexion->error
            ]);
            exit();
        }

        $newId = $id_result;

    } else {

        /* Insertar */
        $stmt = $conexion->prepare("
            INSERT INTO resultados (Id_KPs, Dato_Ingresado)
            VALUES (?, ?)
        ");

        $stmt->bind_param("id", $id_kps, $dato);

        if (!$stmt->execute()) {
            echo json_encode([
                'success' => false,
                'error' => $conexion->error
            ]);
            exit();
        }

        $newId = $conexion->insert_id;
    }

    $porcentaje = $meta > 0
        ? round(($dato / $meta) * 100, 1)
        : 0;

    echo json_encode([
        'success'    => true,
        'id_result'  => $newId,
        'dato'       => $dato,
        'porcentaje' => $porcentaje
    ]);

    exit();
}


/* ───────────────────────────────
   INSIGNIAS
─────────────────────────────── */
if ($action === 'insignias') {

    $res = $conexion->query("
        SELECT Id_Insignia, Descripcion
        FROM insignias
        ORDER BY Id_Insignia ASC
    ");

    if (!$res) {
        echo json_encode([
            'success' => false,
            'error' => $conexion->error
        ]);
        exit();
    }

    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);

    exit();
}


/* ───────────────────────────────
   ACCIÓN INVÁLIDA
─────────────────────────────── */
http_response_code(400);

echo json_encode([
    'success' => false,
    'error'   => 'Acción no válida',
    'action'  => $action
]);
?>