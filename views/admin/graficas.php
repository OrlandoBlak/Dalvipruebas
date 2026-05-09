<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];
$totalAreas = $conexion->query("SELECT COUNT(*) as t FROM areas")->fetch_assoc()['t'];

// Detectar FK área
$fkCol = 'FK_Id_Area';
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($col = $cols->fetch_assoc()) {
    if (stripos($col['Field'], 'area') !== false) { $fkCol = $col['Field']; break; }
}

// ── DATOS PARA GRÁFICA PASTEL: colaboradores por área ────
$pastelRes = $conexion->query("
    SELECT a.Nombre AS area, COUNT(DISTINCT r.Id_Colaborador) AS total
    FROM reportes r
    INNER JOIN colaboradores c ON c.Id_Colaborador = r.Id_Colaborador
    INNER JOIN areas a ON a.Id_Area = c.`$fkCol`
    GROUP BY a.Id_Area, a.Nombre
    ORDER BY total DESC
");
$pastelLabels = []; $pastelData = [];
while ($pastelRes && $row = $pastelRes->fetch_assoc()) {
    $pastelLabels[] = $row['area'];
    $pastelData[]   = (int)$row['total'];
}

// ── DATOS PARA GRÁFICA BARRA: promedio por departamento ──
$barraRes = $conexion->query("
    SELECT a.Nombre AS area, ROUND(AVG(e.Evaluacion), 1) AS promedio
    FROM evaluaciones e
    INNER JOIN colaboradores c ON c.Id_Colaborador = e.Id_Colaborador
    INNER JOIN areas a ON a.Id_Area = c.`$fkCol`
    GROUP BY a.Id_Area, a.Nombre
    ORDER BY promedio DESC
");
$barraLabels = []; $barraData = [];
while ($barraRes && $row = $barraRes->fetch_assoc()) {
    $barraLabels[] = $row['area'];
    $barraData[]   = (float)$row['promedio'];
}

// ── TABLA DE REPORTES ─────────────────────────────────────
$reportesRes = $conexion->query("
    SELECT
        r.Id_Data,
        r.Descripcion,
        c.Nombre          AS colab_nombre,
        a.Nombre          AS area_nombre,
        e.Evaluacion,
        e.Criterios,
        ins.Descripcion   AS Insignias,
        est.Descripcion   AS nivel,
        est.Porcentaje    AS rango
    FROM reportes r
    INNER JOIN colaboradores c   ON c.Id_Colaborador  = r.Id_Colaborador
    INNER JOIN areas a           ON a.Id_Area          = c.`$fkCol`
    LEFT  JOIN evaluaciones e    ON e.Id_Evaluacion   = r.Id_Evaluacion
    LEFT  JOIN insignias ins     ON ins.Id_Insignia   = e.Id_Insignia
    LEFT  JOIN estadisticas est  ON est.Id_Estadistica = r.Id_Estadistica
    ORDER BY r.Id_Data DESC
");
$reportes = [];
if ($reportesRes) { while ($row = $reportesRes->fetch_assoc()) $reportes[] = $row; }

$totalReportes   = count($reportes);

// Colaboradores con evaluaciones para el selector
$colabsEvalRes = $conexion->query("
    SELECT DISTINCT c.Id_Colaborador, c.Nombre, a.Nombre AS area_nombre
    FROM colaboradores c
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    LEFT  JOIN areas a ON a.Id_Area = c.`$fkCol`
    ORDER BY a.Nombre ASC, c.Nombre ASC
");
$colabsEval = [];
while ($colabsEvalRes && $ce = $colabsEvalRes->fetch_assoc()) $colabsEval[] = $ce;
$promedioGeneral = $totalReportes
    ? round(array_sum(array_column($reportes, 'Evaluacion')) / $totalReportes, 1)
    : 0;

function nivelCls($nivel) {
    if (!$nivel) return '';
    $d = strtolower($nivel);
    if (str_contains($d,'excep'))  return 'nivel-excepcional';
    if (str_contains($d,'camino')) return 'nivel-encamino';
    if (str_contains($d,'desarr')) return 'nivel-endesarrollo';
    return 'nivel-requiere';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráficas – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/graficas.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <!-- SheetJS para Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- html2canvas + jsPDF para exportar -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../../assets/logo.jpeg" alt="Logo" class="logo-img" onerror="this.style.display='none'">
            <div class="logo-text">
                <span class="logo-group">GRUPO</span>
                <span class="logo-name">GRUPO DALVI</span>
                <span class="logo-sub">EVALUACIÓN</span>
            </div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">✕</button>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">PANEL</div>
        <a href="homeadmin.php" class="nav-item"><span class="nav-icon">⊞</span><span class="nav-text">Resumen General</span></a>
        <a href="departamentos.php" class="nav-item"><span class="nav-icon">🏢</span><span class="nav-text">Departamentos</span><span class="nav-badge"><?= $totalAreas ?></span></a>
        <a href="graficas.php" class="nav-item active"><span class="nav-icon">📊</span><span class="nav-text">Gráficas</span></a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="ranking.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span></a>
        <a href="heatmap.php" class="nav-item"><span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span></a>
        <a href="dashboard.php" class="nav-item"><span class="nav-icon">📋</span><span class="nav-text">Dashboard Ejecutivo</span></a>
        <div class="nav-section-label">CONFIGURACIÓN</div>
        <a href="kpis.php" class="nav-item"><span class="nav-icon">🎯</span><span class="nav-text">KPIs y Metas</span></a>
        <a href="criterios.php" class="nav-item"><span class="nav-icon">📝</span><span class="nav-text">Criterios de Eval.</span></a>

    </nav>
    <div class="sidebar-footer">
        <div class="colab-count">
            <span class="colab-number"><?= $totalColab ?></span>
            <span class="colab-label">COLABORADORES</span>
        </div>
        <div class="sidebar-actions">
            <span class="status-dot"></span>
            <span class="status-text">Activo ahora</span>
            <a href="../../php/logout.php" class="btn-logout">↩ Salir</a>
        </div>
    </div>
</aside>

<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">📊 Gráficas</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
            <button class="btn-excel" id="btnExportExcel" onclick="exportarExcel()">
                <span class="btn-text">📊 Exportar Excel</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
            <button class="btn-pdf" id="btnExportPDF">
                <span class="btn-text">📄 Exportar PDF</span>
                <span class="btn-spinner" style="display:none">⏳ Generando...</span>
            </button>
        </div>
    </header>

    <!-- SELECTOR REPORTE INDIVIDUAL -->
    <div class="reporte-selector-wrap">
        <div class="reporte-selector-inner">
            <span class="reporte-selector-label">📋 Reporte Individual:</span>
            <select id="selectColabReporte" class="reporte-select">
                <option value="">— Selecciona un colaborador —</option>
                <?php
                $areaActual = '';
                foreach ($colabsEval as $ce):
                    if ($ce['area_nombre'] !== $areaActual):
                        if ($areaActual !== '') echo '</optgroup>';
                        echo '<optgroup label="' . htmlspecialchars($ce['area_nombre']) . '">';
                        $areaActual = $ce['area_nombre'];
                    endif;
                ?>
                <option value="<?= $ce['Id_Colaborador'] ?>"><?= htmlspecialchars($ce['Nombre']) ?></option>
                <?php endforeach;
                if ($areaActual !== '') echo '</optgroup>'; ?>
            </select>
            <button class="btn-reporte-ind" id="btnReporteInd" onclick="abrirReporte()" disabled>
                📄 Ver Reporte Individual
            </button>
        </div>
        <?php if (empty($colabsEval)): ?>
        <p class="reporte-sin-datos">Sin colaboradores evaluados aún.</p>
        <?php endif; ?>
    </div>

    <!-- STATS RÁPIDAS -->
    <div class="graf-stats">
        <div class="graf-stat">
            <span class="graf-stat-num"><?= $totalReportes ?></span>
            <span class="graf-stat-label">Reportes totales</span>
        </div>
        <div class="graf-stat-div"></div>
        <div class="graf-stat">
            <span class="graf-stat-num"><?= $promedioGeneral ?><span class="graf-stat-den">/10</span></span>
            <span class="graf-stat-label">Promedio general</span>
        </div>
        <div class="graf-stat-div"></div>
        <div class="graf-stat">
            <span class="graf-stat-num"><?= count($barraLabels) ?></span>
            <span class="graf-stat-label">Áreas evaluadas</span>
        </div>
        <div class="graf-stat-div"></div>
        <div class="graf-stat">
            <span class="graf-stat-num"><?= $totalColab ?></span>
            <span class="graf-stat-label">Colaboradores</span>
        </div>
    </div>

    <!-- SECCIÓN A EXPORTAR -->
    <div id="exportSection">

        <!-- ENCABEZADO DEL REPORTE (solo visible en PDF) -->
        <div class="pdf-header" id="pdfHeader" style="display:none">
            <div class="pdf-header-logo">📊 GRUPO DALVI — Reporte de Evaluación</div>
            <div class="pdf-header-fecha">Generado: <?= date('d/m/Y H:i') ?></div>
        </div>

        <!-- GRÁFICAS -->
        <div class="graf-grid">

            <!-- PASTEL: Colaboradores por área -->
            <div class="graf-card" id="grafPastel">
                <div class="graf-card-title">
                    <span>🥧 Colaboradores Evaluados por Área</span>
                </div>
                <?php if (empty($pastelData)): ?>
                <div class="graf-empty">Sin datos aún</div>
                <?php else: ?>
                <div class="graf-canvas-wrap">
                    <canvas id="chartPastel"></canvas>
                </div>
                <div class="graf-leyenda" id="leyendaPastel"></div>
                <?php endif; ?>
            </div>

            <!-- BARRA: Promedio por departamento -->
            <div class="graf-card" id="grafBarra">
                <div class="graf-card-title">
                    <span>📊 Promedio de Evaluación por Departamento</span>
                </div>
                <?php if (empty($barraData)): ?>
                <div class="graf-empty">Sin datos aún</div>
                <?php else: ?>
                <div class="graf-canvas-wrap graf-canvas-barra">
                    <canvas id="chartBarra"></canvas>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- TABLA DE REPORTES -->
        <div class="graf-tabla-section">
            <div class="graf-tabla-header">
                <span class="graf-tabla-title">📋 Tabla de Reportes Detallados</span>
                <span class="graf-tabla-count"><?= $totalReportes ?> registro<?= $totalReportes !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($reportes)): ?>
            <div class="graf-empty" style="padding:32px;text-align:center">
                <p style="color:#94a3b8;font-size:14px">Sin reportes generados aún.</p>
            </div>
            <?php else: ?>
            <div class="graf-tabla-wrap">
                <table class="graf-tabla" id="tablaReportes">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Colaborador</th>
                            <th>Área</th>
                            <th>Promedio</th>
                            <th>Nivel</th>
                            <th>Criterios</th>
                            <th>Insignias</th>
                            <th>Reporte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportes as $i => $r): ?>
                        <tr>
                            <td class="td-num"><?= $r['Id_Data'] ?></td>
                            <td class="td-nombre"><?= htmlspecialchars($r['colab_nombre']) ?></td>
                            <td class="td-area"><?= htmlspecialchars($r['area_nombre']) ?></td>
                            <td class="td-prom">
                                <span class="tabla-prom"><?= number_format($r['Evaluacion'], 1) ?></span>/10
                            </td>
                            <td>
                                <?php if ($r['nivel']): ?>
                                <span class="tabla-nivel <?= nivelCls($r['nivel']) ?>">
                                    <?= htmlspecialchars($r['nivel']) ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#94a3b8;font-size:11px">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-criterios">
                                <?php
                                $crits = explode(',', $r['Criterios'] ?? '');
                                foreach (array_slice($crits, 0, 3) as $cr):
                                    $cr = trim($cr);
                                    if ($cr): ?>
                                    <span class="crit-tag"><?= htmlspecialchars($cr) ?></span>
                                <?php endif; endforeach;
                                if (count($crits) > 3): ?>
                                    <span class="crit-more">+<?= count($crits)-3 ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="td-insignias">
                                <?php
                                $insTxt = trim($r['Insignias'] ?? '');
                                if (!empty($insTxt) && $insTxt !== '0'):
                                    echo "<span class='ins-badge' title='" . htmlspecialchars($insTxt) . "'>🏅 " . htmlspecialchars($insTxt) . "</span>";
                                else:
                                    echo '—';
                                endif; ?>
                            </td>
                            <td class="td-desc" title="<?= htmlspecialchars($r['Descripcion']) ?>">
                                <?= htmlspecialchars(mb_substr($r['Descripcion'] ?? '—', 0, 30)) ?>
                                <?= strlen($r['Descripcion'] ?? '') > 30 ? '…' : '' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /exportSection -->

</main>

<style>
.reporte-selector-wrap {
    margin: 0 28px 20px;
    background: #fff;
    border: 1.5px solid #dbeafe;
    border-radius: var(--radius);
    padding: 16px 20px;
    box-shadow: var(--shadow-sm);
}
.reporte-selector-inner {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.reporte-selector-label {
    font-size: 12px; font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .8px;
    white-space: nowrap;
}
.reporte-select {
    flex: 1; min-width: 220px;
    padding: 9px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px; font-size: 13px;
    font-family: 'Sora', sans-serif;
    background: var(--bg-main); color: var(--text-primary);
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.reporte-select:focus { border-color: var(--accent-light); }
.btn-reporte-ind {
    padding: 9px 20px; border-radius: 10px;
    font-size: 13px; font-weight: 600;
    border: none; cursor: pointer;
    font-family: 'Sora', sans-serif;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: #fff;
    box-shadow: 0 4px 12px rgba(37,99,235,.25);
    transition: all .2s; white-space: nowrap;
}
.btn-reporte-ind:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,99,235,.35); }
.btn-reporte-ind:disabled { opacity: .45; cursor: not-allowed; transform: none; }
.reporte-sin-datos { font-size: 12px; color: var(--text-muted); font-style: italic; margin-top: 8px; }
@media(max-width:640px) {
    .reporte-selector-wrap { margin: 0 12px 14px; }
    .reporte-select { min-width: 100%; }
}
</style>

<script src="../../functions/admin_dashboard.js"></script>
<script src="../../functions/excel.js"></script>
<script>
function abrirReporte() {
    const sel = document.getElementById('selectColabReporte');
    const id  = sel?.value;
    if (!id) return;
    window.open('../../php/reporte_individual.php?id=' + id, '_blank');
}
document.getElementById('selectColabReporte')?.addEventListener('change', function() {
    const btn = document.getElementById('btnReporteInd');
    if (btn) btn.disabled = !this.value;
});
</script>
<script>
// ── DATOS DESDE PHP ────────────────────────────────────────
const PASTEL_LABELS = <?= json_encode($pastelLabels) ?>;
const PASTEL_DATA   = <?= json_encode($pastelData) ?>;
const BARRA_LABELS  = <?= json_encode($barraLabels) ?>;
const BARRA_DATA    = <?= json_encode($barraData) ?>;

// ── PALETA DE COLORES ──────────────────────────────────────
const PALETTE = [
    '#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6',
    '#06b6d4','#ec4899','#84cc16','#f97316','#6366f1',
    '#14b8a6','#e11d48','#a16207',
];

// ── GRÁFICA PASTEL ─────────────────────────────────────────
if (PASTEL_DATA.length > 0) {
    const ctxP = document.getElementById('chartPastel')?.getContext('2d');
    if (ctxP) {
        new Chart(ctxP, {
            type: 'doughnut',
            data: {
                labels: PASTEL_LABELS,
                datasets: [{
                    data: PASTEL_DATA,
                    backgroundColor: PALETTE.slice(0, PASTEL_DATA.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '55%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} colaborador${ctx.parsed !== 1 ? 'es' : ''}`
                        }
                    }
                }
            }
        });

        // Leyenda personalizada
        const leyenda = document.getElementById('leyendaPastel');
        if (leyenda) {
            leyenda.innerHTML = PASTEL_LABELS.map((lbl, i) => `
                <div class="leyenda-item">
                    <span class="leyenda-dot" style="background:${PALETTE[i]}"></span>
                    <span class="leyenda-txt">${lbl}</span>
                    <span class="leyenda-num">${PASTEL_DATA[i]}</span>
                </div>`).join('');
        }
    }
}

// ── GRÁFICA BARRA ──────────────────────────────────────────
if (BARRA_DATA.length > 0) {
    const ctxB = document.getElementById('chartBarra')?.getContext('2d');
    if (ctxB) {
        new Chart(ctxB, {
            type: 'bar',
            data: {
                labels: BARRA_LABELS,
                datasets: [{
                    label: 'Promedio /10',
                    data: BARRA_DATA,
                    backgroundColor: BARRA_DATA.map(v =>
                        v >= 9  ? '#10b981' :
                        v >= 7.5? '#2563eb' :
                        v >= 6  ? '#f59e0b' : '#ef4444'
                    ),
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        min: 0, max: 10,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: 'Space Mono', size: 11 },
                            callback: v => v + '/10'
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Sora', size: 11 },
                            callback: function(val) {
                                const lbl = this.getLabelForValue(val);
                                return lbl.length > 18 ? lbl.slice(0,17)+'…' : lbl;
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` Promedio: ${ctx.parsed.x}/10`
                        }
                    }
                }
            }
        });
    }
}

// ── EXPORTAR PDF ───────────────────────────────────────────
document.getElementById('btnExportPDF')?.addEventListener('click', async function() {
    const btn     = this;
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.btn-spinner');

    btn.disabled         = true;
    btnText.style.display = 'none';
    spinner.style.display = 'inline';

    try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });

        const pageW = 215.9; // letter width mm
        const pageH = 279.4; // letter height mm
        const margin = 14;
        const contentW = pageW - margin * 2;
        let y = margin;

        // ── Encabezado ───────────────────────────────────
        pdf.setFillColor(15, 27, 45);
        pdf.rect(0, 0, pageW, 20, 'F');
        pdf.setTextColor(255, 255, 255);
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(13);
        pdf.text('GRUPO DALVI — Reporte de Graficas y Evaluaciones', margin, 13);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(9);
        pdf.text('Generado: <?= date('d/m/Y H:i') ?>', pageW - margin, 13, { align: 'right' });

        y = 28;

        // ── Stats resumen ─────────────────────────────────
        const stats = [
            ['Reportes totales', '<?= $totalReportes ?>'],
            ['Promedio general', '<?= $promedioGeneral ?>/10'],
            ['Areas evaluadas', '<?= count($barraLabels) ?>'],
            ['Colaboradores', '<?= $totalColab ?>'],
        ];
        const statW = contentW / 4;
        pdf.setFillColor(240, 244, 249);
        pdf.roundedRect(margin, y, contentW, 18, 3, 3, 'F');
        stats.forEach((s, i) => {
            const sx = margin + i * statW + statW / 2;
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(12);
            pdf.setTextColor(15, 27, 45);
            pdf.text(s[1], sx, y + 8, { align: 'center' });
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(7);
            pdf.setTextColor(100, 116, 139);
            pdf.text(s[0].toUpperCase(), sx, y + 14, { align: 'center' });
        });
        y += 24;

        // ── Capturar gráficas con html2canvas ─────────────
        const grafGrid = document.querySelector('.graf-grid');
        if (grafGrid) {
            const canvas = await html2canvas(grafGrid, {
                scale: 2, backgroundColor: '#ffffff',
                useCORS: true, logging: false
            });
            const imgData = canvas.toDataURL('image/png');
            const ratio   = canvas.width / canvas.height;
            const imgH    = contentW / ratio;
            pdf.addImage(imgData, 'PNG', margin, y, contentW, imgH);
            y += imgH + 8;
        }

        // ── Tabla de reportes ─────────────────────────────
        const tabla = document.getElementById('tablaReportes');
        if (tabla) {
            // Nueva página si no cabe
            if (y > pageH - 60) { pdf.addPage(); y = margin + 6; }

            // Título tabla
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(10);
            pdf.setTextColor(15, 27, 45);
            pdf.text('TABLA DE REPORTES DETALLADOS', margin, y);
            y += 7;

            // Encabezado tabla
            const cols   = ['#', 'Colaborador', 'Area', 'Prom.', 'Nivel', 'Insignias'];
            const colsW  = [12, 52, 44, 18, 36, 18];
            const rowH   = 7;

            pdf.setFillColor(15, 27, 45);
            pdf.rect(margin, y, contentW, rowH, 'F');
            pdf.setTextColor(255, 255, 255);
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(8);
            let cx = margin + 2;
            cols.forEach((col, i) => {
                pdf.text(col, cx, y + 5);
                cx += colsW[i];
            });
            y += rowH;

            // Filas
            const rows = tabla.querySelectorAll('tbody tr');
            rows.forEach((row, idx) => {
                if (y > pageH - margin - rowH) { pdf.addPage(); y = margin + 6; }

                const cells = row.querySelectorAll('td');
                const bg    = idx % 2 === 0 ? [248,250,255] : [255,255,255];
                pdf.setFillColor(...bg);
                pdf.rect(margin, y, contentW, rowH, 'F');

                pdf.setTextColor(30, 30, 30);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(7.5);

                const values = [
                    cells[0]?.textContent.trim(),
                    cells[1]?.textContent.trim(),
                    cells[2]?.textContent.trim(),
                    cells[3]?.textContent.trim().replace('/10',''),
                    cells[4]?.textContent.trim(),
                    cells[6]?.textContent.trim(),
                ];
                cx = margin + 2;
                values.forEach((val, i) => {
                    const maxLen = Math.floor(colsW[i] / 1.8);
                    const txt = val && val.length > maxLen ? val.slice(0, maxLen - 1) + '…' : (val || '—');
                    pdf.text(txt, cx, y + 5);
                    cx += colsW[i];
                });

                // Línea separadora
                pdf.setDrawColor(230, 235, 240);
                pdf.line(margin, y + rowH, margin + contentW, y + rowH);
                y += rowH;
            });
        }

        // ── Pie de página ─────────────────────────────────
        const totalPages = pdf.internal.getNumberOfPages();
        for (let p = 1; p <= totalPages; p++) {
            pdf.setPage(p);
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.setTextColor(148, 163, 184);
            pdf.text(`Grupo Dalvi · Sistema de Evaluación`, margin, pageH - 6);
            pdf.text(`Pág. ${p} / ${totalPages}`, pageW - margin, pageH - 6, { align: 'right' });
        }

        pdf.save(`reporte-dalvi-<?= date('Ymd') ?>.pdf`);

    } catch(err) {
        console.error('Error generando PDF:', err);
        alert('Error al generar PDF. Intenta de nuevo.');
    } finally {
        btn.disabled          = false;
        btnText.style.display = 'inline';
        spinner.style.display = 'none';
    }
});
</script>
</body>
</html>