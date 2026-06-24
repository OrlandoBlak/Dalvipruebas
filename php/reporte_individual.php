<?php
session_start();
$roles = ['Administrador','Usuario'];
if (!isset($_SESSION['id']) || !in_array($_SESSION['rol'], $roles)) {
    header("Location: ../../index.php?error=acceso"); exit();
}
require_once "../config/conexion.php";

$id_colaborador = (int)($_GET['id'] ?? 0);
if ($id_colaborador <= 0) { echo "ID inválido"; exit(); }

// Detectar FK área
$fkCol = 'FK_Id_Area'; $cargoCol = null;
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($c = $cols->fetch_assoc()) {
    if (stripos($c['Field'],'area')  !== false) $fkCol    = $c['Field'];
    if (stripos($c['Field'],'cargo') !== false) $cargoCol = $c['Field'];
    if (stripos($c['Field'],'puest') !== false && !$cargoCol) $cargoCol = $c['Field'];
}
$selCargo = $cargoCol ? "c.`$cargoCol` AS Cargo" : "'' AS Cargo";

// Datos colaborador
$stmtC = $conexion->prepare("
    SELECT c.Id_Colaborador, c.Nombre, $selCargo, a.Nombre AS area_nombre, a.Id_Area
    FROM colaboradores c
    LEFT JOIN areas a ON a.Id_Area = c.`$fkCol`
    WHERE c.Id_Colaborador = ? LIMIT 1
");
$stmtC->bind_param("i", $id_colaborador);
$stmtC->execute();
$colab = $stmtC->get_result()->fetch_assoc();
if (!$colab) { echo "Colaborador no encontrado"; exit(); }

// Evaluaciones del colaborador
$stmtE = $conexion->prepare("
    SELECT e.*, o.Observacion, o.Puntos, o.Pendientes, o.Comentarios
    FROM evaluaciones e
    LEFT JOIN observaciones o ON o.Id_Observacion = e.Id_Observacion
    WHERE e.Id_Colaborador = ?
    ORDER BY e.Id_Evaluacion DESC
");
$stmtE->bind_param("i", $id_colaborador);
$stmtE->execute();
$evals = $stmtE->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($evals)) {
    echo "<p style='padding:40px;font-family:sans-serif'>Sin evaluaciones registradas.</p>"; exit();
}

$evalActual     = $evals[0];
$promedio       = (float)$evalActual['Evaluacion'];
$criteriosArr   = array_filter(array_map('trim', explode(',', $evalActual['Criterios'] ?? '')));
$totalCriterios = count($criteriosArr);

// ── CRITERIOS INDIVIDUALES desde criterios_resultados ────
$criteriosResultados = [];
$stmtCR = $conexion->prepare("
    SELECT Datos_Guardado
    FROM criterios_resultados
    WHERE Id_Evaluacion = ?
");
if ($stmtCR) {
    $stmtCR->bind_param("i", $evalActual['Id_Evaluacion']);
    $stmtCR->execute();
    $resCR = $stmtCR->get_result();
    while ($row = $resCR->fetch_assoc()) {
        $data = json_decode($row['Datos_Guardado'], true);
        if ($data && isset($data['criterio'])) $criteriosResultados[$data['criterio']] = $data;
    }
}

// Si no hay datos en criterios_resultados, usar criterios desde tabla puntos
$criteriosDetalle = [];
if (!empty($criteriosArr)) {
    $placeholders = implode(',', array_fill(0, count($criteriosArr), '?'));
    $types        = str_repeat('s', count($criteriosArr));
    $stmtP = $conexion->prepare("SELECT Nombre_Criterio, Evaluando FROM puntos WHERE Nombre_Criterio IN ($placeholders)");
    $stmtP->bind_param($types, ...$criteriosArr);
    $stmtP->execute();
    $pRes = $stmtP->get_result();
    while ($p = $pRes->fetch_assoc()) {
        $criteriosDetalle[$p['Nombre_Criterio']] = $p;
    }
}

// Cargar razones de evaluación desde tabla razones
$razonesData = [];
$stmtRaz = $conexion->prepare("
    SELECT r.Objetivo
    FROM razones r
    INNER JOIN evaluaciones e ON e.Id_Razon = r.Id_Razon
    WHERE e.Id_Evaluacion = ?
    LIMIT 1
");
if ($stmtRaz) {
    $stmtRaz->bind_param("i", $evalActual['Id_Evaluacion']);
    $stmtRaz->execute();
    $razRow = $stmtRaz->get_result()->fetch_assoc();
    if ($razRow && !empty($razRow['Objetivo'])) {
        // Parsear "1.- Criterio: "texto"" → array keyed by criterio
        $lineas = explode("
", $razRow['Objetivo']);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;
            // Regex: "N.- Nombre: "texto""
            if (preg_match('/^\d+\.\-\s*(.+?):\s*"(.*)"$/u', $linea, $m)) {
                $razonesData[trim($m[1])] = trim($m[2]);
            }
        }
    }
}

// KPIs del área con resultados
$kpisArea = [];
if ($colab['Id_Area']) {
    $stmtK = $conexion->prepare("
        SELECT k.Nombre, k.Tipo, k.Metas,
               COALESCE(r.Dato_Ingresado, 0) AS Dato_Ingreso
        FROM kps k
        LEFT JOIN (
            SELECT Id_KPs, MAX(Id_Result) AS maxR FROM resultados GROUP BY Id_KPs
        ) rm ON rm.Id_KPs = k.Id_KPs
        LEFT JOIN resultados r ON r.Id_Result = rm.maxR
        WHERE k.Id_Area = ?
        ORDER BY k.Id_KPs ASC
    ");
    if ($stmtK) {
        $stmtK->bind_param("i", $colab['Id_Area']);
        $stmtK->execute();
        $kpisArea = $stmtK->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Insignia
$idInsignia  = (int)($evalActual['Id_Insignia'] ?? 0);
$insArr      = [];
$insIconos   = ['❤️','💎','🚀','⚡','💬','🤝','🔄'];
if ($idInsignia > 0) {
    $insRes2 = $conexion->query("SELECT Id_Insignia, Descripcion FROM insignias ORDER BY Id_Insignia ASC");
    $allIns  = [];
    while ($ir = $insRes2->fetch_assoc()) $allIns[$ir['Id_Insignia']] = $ir['Descripcion'];
    if (isset($allIns[$idInsignia])) $insArr[$idInsignia] = $allIns[$idInsignia];
}

// Nivel
$pct = ($promedio / 10) * 100;
if ($pct >= 90)     { $nivelTxt = 'Excepcional';   $nivelColor = '#065f46'; }
elseif ($pct >= 75) { $nivelTxt = 'En camino';     $nivelColor = '#1d4ed8'; }
elseif ($pct >= 60) { $nivelTxt = 'En desarrollo'; $nivelColor = '#92400e'; }
else                { $nivelTxt = 'Por Debajo';    $nivelColor = '#b91c1c'; }

function stars($v,$max=5){$l=round(($v/10)*$max);$s='';for($i=1;$i<=$max;$i++)$s.=$i<=$l?'★':'☆';return $s;}

$fechaActual = date('d/m/Y');
$periodo     = date('Y');
$ini         = mb_strtoupper(mb_substr($colab['Nombre'],0,1));
$pesoCriterio = $totalCriterios > 0 ? round(100 / $totalCriterios, 0) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Evaluación — <?= htmlspecialchars($colab['Nombre']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Segoe UI', Arial, sans-serif; background:#f5f7fb; color:#1e293b; font-size:13px; }

.print-bar {
    position:fixed; top:0; left:0; right:0; z-index:100;
    background:#0f1b2d; padding:10px 24px;
    display:flex; align-items:center; justify-content:space-between;
    box-shadow:0 2px 8px rgba(0,0,0,.3);
}
.print-bar-title { color:rgba(255,255,255,.7); font-size:13px; }
.print-bar-btns  { display:flex; gap:10px; }
.btn-print {
    background:linear-gradient(135deg,#2563eb,#3b82f6);
    color:#fff; border:none; padding:8px 20px;
    border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;
}
.btn-back {
    background:rgba(255,255,255,.1); color:#fff;
    border:1px solid rgba(255,255,255,.2);
    padding:8px 16px; border-radius:8px; font-size:13px;
    cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;
}

.page {
    width:216mm; min-height:280mm;
    margin:60px auto 20px; background:#fff;
    padding:18mm 16mm 14mm;
    box-shadow:0 4px 32px rgba(0,0,0,.12);
}

.report-header {
    display:flex; justify-content:space-between; align-items:flex-start;
    margin-bottom:6mm; padding-bottom:4mm;
    border-bottom:1px solid #e2e8f0;
}
.brand-name  { font-size:22px; font-weight:800; color:#2563eb; }
.brand-group { font-size:10px; color:#94a3b8; text-transform:uppercase; letter-spacing:2px; }
.report-meta-title { font-size:13px; font-weight:700; color:#475569; text-align:right; }
.report-meta-date  { font-size:11px; color:#94a3b8; margin-top:3px; text-align:right; }

.perfil-section {
    display:flex; align-items:center; gap:16px;
    background:linear-gradient(135deg,#f0f6ff,#fff);
    border:1px solid #dbeafe; border-radius:12px;
    padding:14px 18px; margin-bottom:6mm;
}
.perfil-avatar {
    width:54px; height:54px; border-radius:50%;
    background:linear-gradient(135deg,#1e40af,#3b82f6);
    color:#fff; font-size:22px; font-weight:700;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.perfil-info    { flex:1; }
.perfil-nombre  { font-size:18px; font-weight:700; color:#1e293b; margin-bottom:2px; }
.perfil-cargo   { font-size:12px; color:#64748b; margin-bottom:5px; }
.perfil-area    { display:inline-block; background:#dbeafe; color:#1d4ed8; font-size:10px; font-weight:700; padding:2px 10px; border-radius:20px; }
.perfil-score   { text-align:center; }
.perfil-score-num { font-size:36px; font-weight:800; color:#2563eb; font-family:'Courier New',monospace; line-height:1; }
.perfil-score-den { font-size:14px; color:#94a3b8; }
.perfil-score-stars { font-size:16px; color:#f59e0b; letter-spacing:2px; margin-top:3px; }
.perfil-score-nivel { font-size:11px; font-weight:700; color:<?= $nivelColor ?>; margin-top:3px; }

.stats-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:6mm; }
.stat-box { border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; text-align:center; }
.stat-box-val { font-size:20px; font-weight:800; color:#2563eb; font-family:'Courier New',monospace; }
.stat-box-lbl { font-size:9px; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin-top:3px; }

.section-title {
    display:flex; align-items:center; gap:8px;
    font-size:11px; font-weight:700; text-transform:uppercase;
    letter-spacing:1.5px; color:#2563eb;
    border-bottom:2px solid #dbeafe; padding-bottom:6px;
    margin-bottom:10px; margin-top:8mm;
}

/* ── TABLA CRITERIOS ── */
.criterios-tabla { width:100%; border-collapse:collapse; font-size:11.5px; }
.criterios-tabla thead tr { background:#0f1b2d; }
.criterios-tabla thead th {
    padding:7px 10px; text-align:left; color:rgba(255,255,255,.8);
    font-size:9px; text-transform:uppercase; letter-spacing:1px; font-weight:600;
}
.criterios-tabla tbody tr { border-bottom:1px solid #f1f5f9; }
.criterios-tabla tbody tr:nth-child(even) { background:#f8faff; }
.criterios-tabla td { padding:8px 10px; vertical-align:middle; }
.td-criterio { font-weight:600; color:#1e293b; }
.td-peso     { text-align:center; color:#64748b; font-size:10px; }
.td-actual   { text-align:center; font-weight:700; color:#2563eb; font-family:'Courier New',monospace; }
.td-deseado  { text-align:center; color:#64748b; }
.td-diff     { text-align:center; font-weight:700; }
.td-diff.positivo { color:#10b981; }
.td-diff.negativo { color:#ef4444; }

.barra-wrap  { display:flex; align-items:center; gap:5px; margin-bottom:2px; }
.barra-label { font-size:9px; color:#94a3b8; width:34px; text-align:right; flex-shrink:0; }
.barra-bg    { flex:1; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; }
.barra-fill-actual { height:100%; background:#3b82f6; border-radius:4px; }
.barra-fill-meta   { height:100%; background:#10b981; border-radius:4px; }
.barra-pct   { font-size:9px; font-weight:700; width:32px; }
.barra-pct.actual { color:#2563eb; }
.barra-pct.meta   { color:#10b981; }

/* ── KPIs ── */
.kpis-tabla { width:100%; border-collapse:collapse; font-size:11.5px; }
.kpis-tabla thead tr { background:#0f1b2d; }
.kpis-tabla thead th { padding:7px 10px; text-align:left; color:rgba(255,255,255,.8); font-size:9px; text-transform:uppercase; }
.kpis-tabla tbody tr { border-bottom:1px solid #f1f5f9; }
.kpis-tabla tbody tr:nth-child(even) { background:#f8faff; }
.kpis-tabla td { padding:8px 10px; }

/* ── HISTORIAL ── */
.historial-tabla { width:100%; border-collapse:collapse; font-size:11.5px; }
.historial-tabla thead tr { background:#0f1b2d; }
.historial-tabla thead th { padding:7px 10px; color:rgba(255,255,255,.8); font-size:9px; text-transform:uppercase; }
.historial-tabla tbody tr { border-bottom:1px solid #f1f5f9; }
.historial-tabla td { padding:8px 10px; }
.hist-prom  { font-weight:700; color:#2563eb; font-family:'Courier New',monospace; }
.hist-stars { color:#f59e0b; }

.obs-box {
    border:1px dashed #f59e0b; border-radius:8px;
    padding:12px 14px; background:#fffbeb;
    font-size:11.5px; color:#92400e; line-height:1.6; min-height:40px;
}
.compromisos-box {
    border:1.5px dashed #f59e0b; border-radius:8px;
    padding:16px 18px; background:#fffbeb; min-height:140px;
    display:flex; flex-direction:column; gap:18px; justify-content:flex-start;
}
.compromisos-linea {
    border-bottom:1px solid #fbbf24; height:1px; width:100%;
}
.pagebreak { page-break-before: always; break-before: page; }
.compromisos-hint { font-style:italic; color:#d97706; font-size:11px; }

.firmas-section { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:8mm; }
.firma-box { text-align:center; }
.firma-linea { border-bottom:2px solid #1e293b; margin-bottom:8px; padding-bottom:4px; min-height:40px; }
.firma-nombre { font-weight:700; font-size:12px; }
.firma-cargo  { font-size:10px; color:#64748b; }
.firma-fecha  { font-size:11px; color:#94a3b8; margin-top:8px; }

.report-footer {
    display:flex; justify-content:space-between;
    font-size:9px; color:#94a3b8;
    border-top:1px solid #e2e8f0; padding-top:6px; margin-top:8mm;
}

@media print {
    .print-bar { display:none !important; }
    body { background:#fff; }
    .page { margin:0; box-shadow:none; padding:14mm 12mm; width:100%; }
    @page { size:letter; margin:0; }
}
</style>
</head>
<body>

<div class="print-bar">
    <span class="print-bar-title">📋 Reporte Individual — <?= htmlspecialchars($colab['Nombre']) ?></span>
    <div class="print-bar-btns">
        <button class="btn-back" onclick="if(document.referrer){window.location.href=document.referrer;}else{window.close();}">← Volver</button>
        <button class="btn-print" id="btnDescargarPDF">
            <span id="btnPDFText">⬇️ Descargar PDF</span>
            <span id="btnPDFSpinner" style="display:none">⏳ Generando...</span>
        </button>
    </div>
</div>

<div class="page">

    <!-- HEADER -->
    <div class="report-header">
        <div>
            <div class="brand-group">GRUPO</div>
            <div class="brand-name">DALVI</div>
        </div>
        <div>
            <div class="report-meta-title">Evaluación de Desempeño</div>
            <div class="report-meta-date">Fecha: <?= date('d \d\e F \d\e Y') ?></div>
            <div class="report-meta-date">Período: <?= $periodo ?></div>
        </div>
    </div>

    <!-- PERFIL -->
    <div class="perfil-section">
        <div class="perfil-avatar"><?= $ini ?></div>
        <div class="perfil-info">
            <div class="perfil-nombre"><?= htmlspecialchars($colab['Nombre']) ?></div>
            <?php if (!empty($colab['Cargo'])): ?>
            <div class="perfil-cargo"><?= htmlspecialchars($colab['Cargo']) ?></div>
            <?php endif; ?>
            <span class="perfil-area">🌐 <?= htmlspecialchars($colab['area_nombre']) ?></span>
        </div>
        <div class="perfil-score">
            <div class="perfil-score-num"><?= number_format($promedio,1) ?><span class="perfil-score-den">/10</span></div>
            <div class="perfil-score-stars"><?= stars($promedio) ?></div>
            <div class="perfil-score-nivel"><?= $nivelTxt ?></div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-box-val"><?= number_format($promedio,1) ?></div>
            <div class="stat-box-lbl">Puntuación Final</div>
        </div>
        <div class="stat-box">
            <div class="stat-box-val"><?= $totalCriterios ?>/<?= $totalCriterios ?></div>
            <div class="stat-box-lbl">Criterios Evaluados</div>
        </div>
        <div class="stat-box" style="border-color:<?= $nivelColor ?>20">
            <div class="stat-box-val" style="color:<?= $nivelColor ?>"><?= $nivelTxt ?></div>
            <div class="stat-box-lbl">Estado</div>
        </div>
    </div>

    <!-- CRITERIOS CON VALORES INDIVIDUALES -->
    <div class="section-title">📊 Criterios — Nivel Actual vs Nivel Deseado</div>
    <table class="criterios-tabla">
        <thead>
            <tr>
                <th style="width:16%">Criterio</th>
                <th style="text-align:center;width:6%">Peso</th>
                <th style="text-align:center;width:8%">Actual</th>
                <th style="text-align:center;width:8%">Deseado</th>
                <th style="width:28%">Progreso</th>
                <th style="width:34%">Razón de Calificación</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($criteriosArr as $cNom):
            if (empty($cNom)) continue;

            // Usar datos reales de criterios_resultados si existen
            if (isset($criteriosResultados[$cNom])) {
                $valActual = (float)$criteriosResultados[$cNom]['actual'];
                $maxVal    = (float)$criteriosResultados[$cNom]['maximo'];
                $pctActual = (float)$criteriosResultados[$cNom]['pct'];
            } else {
                // Fallback: calcular desde promedio general
                $det       = $criteriosDetalle[$cNom] ?? null;
                $maxVal    = $det ? (float)$det['Evaluando'] : 10;
                $valActual = round(($promedio / 10) * $maxVal, 1);
                $pctActual = $maxVal > 0 ? round(($valActual/$maxVal)*100, 1) : 0;
            }

            $isStars = $maxVal <= 5;
            $deseado = $maxVal; // meta = 100%
            $pctMeta = 100;
            $peso    = $pesoCriterio;
        ?>
        <?php $razonTexto = $razonesData[$cNom] ?? ''; ?>
        <tr>
            <td class="td-criterio"><?= htmlspecialchars($cNom) ?></td>
            <td class="td-peso"><?= $peso ?>%</td>
            <td class="td-actual"><?= number_format($valActual,1) ?>/<?= $maxVal ?><?= $isStars?' ⭐':'' ?></td>
            <td class="td-deseado"><?= number_format($deseado,1) ?>/<?= $maxVal ?><?= $isStars?' ⭐':'' ?></td>
            <td>
                <div class="barra-wrap">
                    <span class="barra-label">Actual</span>
                    <div class="barra-bg"><div class="barra-fill-actual" style="width:<?= $pctActual ?>%"></div></div>
                    <span class="barra-pct actual"><?= $pctActual ?>%</span>
                </div>
                <div class="barra-wrap">
                    <span class="barra-label">Meta</span>
                    <div class="barra-bg"><div class="barra-fill-meta" style="width:<?= $pctMeta ?>%"></div></div>
                    <span class="barra-pct meta"><?= $pctMeta ?>%</span>
                </div>
            </td>
            <td style="font-size:10.5px;color:#475569;vertical-align:middle;line-height:1.5">
                <?php if (!empty($razonTexto)): ?>
                    <?= htmlspecialchars($razonTexto) ?>
                <?php else: ?>
                    <span style="color:#cbd5e1;font-style:italic">Sin razones</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- KPIs — salto de página para mejor orden -->
    <div class="pagebreak"></div>
    <?php if (!empty($kpisArea)): ?>
    <div class="section-title" style="margin-top:0">📋 KPIs Asignados</div>
    <table class="kpis-tabla">
        <thead>
            <tr>
                <th>KPI</th>
                <th>Tipo</th>
                <th style="text-align:center">Actual</th>
                <th style="text-align:center">Meta</th>
                <th style="text-align:center">Avance</th>
                <th>Progreso</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($kpisArea as $kpi):
            $meta   = (float)$kpi['Metas'];
            $dato   = (float)$kpi['Dato_Ingreso'];
            $pctKpi = $meta > 0 ? round(($dato/$meta)*100,1) : 0;
            $tipo   = $kpi['Tipo'] ?? '';
            $prefix = ($tipo === 'Dinero (MXN)') ? '$' : '';
            $suffix = ($tipo === 'Porcentaje (%)') ? '%' : (($tipo === 'Unidades') ? ' uds' : '');
        ?>
        <tr>
            <td style="font-weight:600"><?= htmlspecialchars($kpi['Nombre']??'KPI') ?></td>
            <td style="font-size:11px;color:#64748b"><?= htmlspecialchars($tipo) ?></td>
            <td style="text-align:center"><?= $prefix.number_format($dato,0).$suffix ?></td>
            <td style="text-align:center"><?= $prefix.number_format($meta,0).$suffix ?></td>
            <td style="text-align:center;font-weight:700;color:#2563eb"><?= $pctKpi ?>%</td>
            <td>
                <div class="barra-bg" style="max-width:120px">
                    <div class="barra-fill-actual" style="width:<?= min($pctKpi,100) ?>%"></div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="section-title" style="margin-top:0">📋 KPIs Asignados</div>
    <p style="font-size:12px;color:#94a3b8;font-style:italic;margin-bottom:4mm">Sin KPIs asignados.</p>
    <?php endif; ?>

    <!-- INSIGNIAS -->
    <?php if (!empty($insArr)): ?>
    <div class="section-title">🏅 Insignias de Valores Dalvi</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6mm">
        <?php foreach ($insArr as $i => $insNombre): ?>
        <div class="ins-chip" style="display:flex;align-items:center;gap:7px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:20px;padding:5px 13px;">
            <span style="font-size:14px"><?= $insIconos[$i%count($insIconos)] ?? '🏅' ?></span>
            <span style="font-size:11px;font-weight:700;color:#92400e"><?= htmlspecialchars($insNombre) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- HISTORIAL -->
    <div class="section-title">📋 Historial de Evaluaciones</div>
    <table class="historial-tabla">
        <thead>
            <tr>
                <th>#</th>
                <th>Puntuación</th>
                <th style="text-align:center">Estrellas</th>
                <th>Variación</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($evals as $idx => $ev):
            $promEv   = (float)$ev['Evaluacion'];
            $promAnts = isset($evals[$idx+1]) ? (float)$evals[$idx+1]['Evaluacion'] : null;
            $variacion = $promAnts !== null ? round($promEv - $promAnts, 1) : null;
            $varTxt   = $variacion === null
                ? '<span style="background:#dbeafe;color:#1d4ed8;font-size:9px;padding:2px 8px;border-radius:20px;font-weight:700">Primera eval.</span>'
                : ($variacion > 0 ? "<span style='color:#10b981'>+$variacion ↑</span>"
                : ($variacion < 0 ? "<span style='color:#ef4444'>$variacion ↓</span>" : 'Sin cambio'));
        ?>
        <tr>
            <td><?= $ev['Id_Evaluacion'] ?></td>
            <td class="hist-prom"><?= number_format($promEv,1) ?>/10</td>
            <td class="hist-stars" style="text-align:center"><?= stars($promEv) ?></td>
            <td><?= $varTxt ?></td>
            <td style="font-size:10px;color:#64748b">
                <?= $idx === 0 ? htmlspecialchars(mb_substr($ev['Observacion']??'—',0,50)) : '—' ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- OBSERVACIONES -->
    <?php if (!empty($evalActual['Observacion'])): ?>
    <div class="section-title">💬 Observaciones y Recomendaciones</div>
    <div class="obs-box"><?= nl2br(htmlspecialchars($evalActual['Observacion'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($evalActual['Puntos'])): ?>
    <div class="section-title" style="margin-top:5mm">📌 Puntos de Mejora</div>
    <div class="obs-box"><?= nl2br(htmlspecialchars($evalActual['Puntos'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($evalActual['Pendientes'])): ?>
    <div class="section-title" style="margin-top:5mm">📋 Pendientes</div>
    <div class="obs-box"><?= nl2br(htmlspecialchars($evalActual['Pendientes'])) ?></div>
    <?php endif; ?>

    <!-- COMPROMISOS — salto de página -->
    <div class="pagebreak"></div>
    <div class="section-title" style="margin-top:0">🔔 Compromisos por Parte del Colaborador</div>
    <div class="compromisos-box">
        <p class="compromisos-hint" style="margin-bottom:4px">El colaborador declara los siguientes compromisos de mejora y acción para el próximo período:</p>
        <div class="compromisos-linea"></div>
        <div class="compromisos-linea"></div>
        <div class="compromisos-linea"></div>
        <div class="compromisos-linea"></div>
        <div class="compromisos-linea"></div>
    </div>
    <p style="font-size:10px;color:#94a3b8;margin-top:4px;font-style:italic">* Este apartado debe ser completado de puño y letra por el colaborador al momento de recibir el documento.</p>

    <!-- FIRMAS -->
    <div class="section-title" style="margin-top:6mm">✏️ Firmas y Validación</div>
    <div class="firmas-section">
        <div class="firma-box">
            <div class="firma-linea"></div>
            <div class="firma-nombre"><?= htmlspecialchars($colab['Nombre']) ?></div>
            <?php if (!empty($colab['Cargo'])): ?>
            <div class="firma-cargo"><?= htmlspecialchars($colab['Cargo']) ?></div>
            <?php endif; ?>
            <div class="firma-cargo"><?= htmlspecialchars($colab['area_nombre']) ?></div>
            <div class="firma-fecha">Fecha: _____ / _____ / __________</div>
        </div>
        <div class="firma-box">
            <div class="firma-linea"></div>
            <div class="firma-nombre">________________________________</div>
            <div class="firma-cargo">Jefe Directo / Evaluador</div>
            <div class="firma-cargo">Nombre y cargo</div>
            <div class="firma-fecha">Fecha: _____ / _____ / __________</div>
        </div>
    </div>
    <p style="font-size:10px;color:#94a3b8;text-align:center;margin-top:8px;font-style:italic">
        Al firmar este documento, ambas partes confirman haber revisado y discutido el contenido de esta evaluación.
    </p>

    <!-- FOOTER -->
    <div class="report-footer">
        <span>Grupo Dalvi · Sistema Integral de Evaluación de Desempeño</span>
        <span>Documento generado automáticamente · <?= $fechaActual ?></span>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.getElementById('btnDescargarPDF')?.addEventListener('click', async function() {
    const btn    = this;
    const txtEl  = document.getElementById('btnPDFText');
    const spinEl = document.getElementById('btnPDFSpinner');
    btn.disabled = true; txtEl.style.display='none'; spinEl.style.display='inline';
    try {
        const { jsPDF } = window.jspdf;
        const page  = document.querySelector('.page');
        const scale = 2;
        window.scrollTo(0,0);

        const canvas = await html2canvas(page, {
            scale, useCORS:true, backgroundColor:'#ffffff',
            logging:false, scrollY:-window.scrollY,
            windowWidth:page.scrollWidth, windowHeight:page.scrollHeight,
        });

        const pageW=215.9, pageH=279.4;
        const marginH = 8;  // mm margen superior/inferior por página
        const marginW = 0;  // mm margen lateral (la página ya tiene padding)
        const usableW   = pageW - marginW*2;
        const usableH   = pageH - marginH*2;
        const pxPerMm   = canvas.width / usableW;
        const usableHpx = usableH * pxPerMm;

        const pageRect = page.getBoundingClientRect();

        // Saltos de página obligatorios (.pagebreak)
        const forcedBreaks = Array.from(page.querySelectorAll('.pagebreak')).map(el => {
            const r = el.getBoundingClientRect();
            return Math.round((r.top - pageRect.top) * scale);
        }).filter(y => y > 0 && y < canvas.height).sort((a,b)=>a-b);

        // Elementos que nunca deben cortarse a la mitad
        const avoidSelectors = '.perfil-section, .stats-row, .criterios-tabla tr, .kpis-tabla tr, .historial-tabla tr, .firma-box, .compromisos-box, .ins-chip, .stat-box';
        const avoidRanges = Array.from(page.querySelectorAll(avoidSelectors)).map(el => {
            const r = el.getBoundingClientRect();
            return {
                top:    Math.round((r.top    - pageRect.top) * scale),
                bottom: Math.round((r.bottom - pageRect.top) * scale)
            };
        });

        function snapCut(y) {
            for (const rg of avoidRanges) {
                if (y > rg.top && y < rg.bottom) return rg.top;
            }
            return y;
        }

        // Construir límites de corte respetando saltos forzados y elementos protegidos
        const boundaries = [0];
        let cursor = 0;
        let guard  = 0;
        while (cursor < canvas.height && guard < 200) {
            guard++;
            let next = cursor + usableHpx;

            const upcoming = forcedBreaks.find(b => b > cursor && b < next);
            if (upcoming) {
                next = upcoming;
            } else {
                next = Math.min(next, canvas.height);
                const snapped = snapCut(next);
                next = (snapped > cursor) ? snapped : next;
            }
            next = Math.min(next, canvas.height);
            if (next <= cursor) next = Math.min(cursor + usableHpx, canvas.height);
            boundaries.push(next);
            cursor = next;
        }

        const pdf = new jsPDF({orientation:'portrait', unit:'mm', format:'letter'});
        for (let i = 0; i < boundaries.length - 1; i++) {
            const srcY = boundaries[i];
            const srcH = boundaries[i+1] - srcY;
            if (srcH <= 0) continue;
            if (i > 0) pdf.addPage();

            const tmp = document.createElement('canvas');
            tmp.width  = canvas.width;
            tmp.height = srcH;
            tmp.getContext('2d').drawImage(canvas, 0, srcY, canvas.width, srcH, 0, 0, canvas.width, srcH);

            const pageImgH = (srcH / canvas.width) * usableW;
            pdf.addImage(tmp.toDataURL('image/jpeg', 0.95), 'JPEG', marginW, marginH, usableW, pageImgH);
        }

        const nombre = <?= json_encode($colab['Nombre']) ?>;
        const fecha  = new Date().toISOString().slice(0,10);
        pdf.save('Evaluacion_'+nombre.replace(/\s+/g,'_')+'_'+fecha+'.pdf');
    } catch(err) {
        console.error(err); alert('Error al generar PDF.');
    } finally {
        btn.disabled=false; txtEl.style.display='inline'; spinEl.style.display='none';
    }
});
</script>
</body>
</html>