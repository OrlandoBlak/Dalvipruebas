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
while ($c = $cols->fetch_assoc()) {
    if (stripos($c['Field'], 'area') !== false) { $fkCol = $c['Field']; break; }
}

// ── Obtener todos los criterios (columnas del heatmap) ────
$criteriosRes = $conexion->query("SELECT Id_Criterios, Nombre_Criterio, Evaluando FROM puntos ORDER BY Nombre_Criterio ASC");
$criterios = [];
while ($c = $criteriosRes->fetch_assoc()) $criterios[] = $c;
$totalCriterios = count($criterios);

// ── Obtener evaluaciones con sus criterios y sliders ─────
// Cada evaluación guarda Criterios (nombres separados por coma) y Evaluacion (promedio)
// Necesitamos cruzar colaborador × criterio
$evalRes = $conexion->query("
    SELECT
        c.Id_Colaborador,
        c.Nombre        AS colab_nombre,
        a.Nombre        AS area_nombre,
        e.Criterios,
        e.Evaluacion,
        e.Id_Evaluacion
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    ORDER BY a.Nombre ASC, c.Nombre ASC, e.Id_Evaluacion DESC
");

// Agrupar: por colaborador, tomar la evaluación más reciente
$colabs = [];
while ($row = $evalRes->fetch_assoc()) {
    $id = $row['Id_Colaborador'];
    if (isset($colabs[$id])) continue; // solo la más reciente
    $colabs[$id] = [
        'nombre'    => $row['colab_nombre'],
        'area'      => $row['area_nombre'],
        'criterios' => array_map('trim', explode(',', $row['Criterios'] ?? '')),
        'promedio'  => (float)$row['Evaluacion'],
    ];
}

$totalEvaluados = count($colabs);

// Función de color según valor (0-10)
function heatColor($val, $max = 10) {
    if ($val === null) return ['bg'=>'#f1f5f9','text'=>'#94a3b8','label'=>'—'];
    $pct = min($val / $max, 1);
    if ($pct >= 0.85) return ['bg'=>'#d1fae5','text'=>'#065f46','label'=>number_format($val,1)];
    if ($pct >= 0.70) return ['bg'=>'#bbf7d0','text'=>'#166534','label'=>number_format($val,1)];
    if ($pct >= 0.55) return ['bg'=>'#fef9c3','text'=>'#854d0e','label'=>number_format($val,1)];
    if ($pct >= 0.40) return ['bg'=>'#fde68a','text'=>'#92400e','label'=>number_format($val,1)];
    if ($pct >= 0.25) return ['bg'=>'#fed7aa','text'=>'#9a3412','label'=>number_format($val,1)];
    return                   ['bg'=>'#fecaca','text'=>'#b91c1c','label'=>number_format($val,1)];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heatmap – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/heatmap.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
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
        <a href="graficas.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Gráficas</span></a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="ranking.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span></a>
        <a href="heatmap.php" class="nav-item active"><span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span></a>
        <a href="dashboard.php" class="nav-item"><span class="nav-icon">📋</span><span class="nav-text">Dashboard Ejecutivo</span></a>
        <div class="nav-section-label">CONFIGURACIÓN</div>
        <a href="kpis.php" class="nav-item"><span class="nav-icon">🎯</span><span class="nav-text">KPIs y Metas</span></a>
        <a href="criterios.php" class="nav-item"><span class="nav-icon">📝</span><span class="nav-text">Criterios de Eval.</span></a>

    </nav>
    <div class="sidebar-footer">
        <div class="colab-count"><span class="colab-number"><?= $totalColab ?></span><span class="colab-label">COLABORADORES</span></div>
        <div class="sidebar-actions">
            <span class="status-dot"></span><span class="status-text">Activo ahora</span>
            <a href="../../php/logout.php" class="btn-logout">↩ Salir</a>
        </div>
    </div>
</aside>

<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">🗺️ Heatmap de Desempeño</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <!-- STATS -->
    <div class="heat-stats">
        <div class="heat-stat">
            <span class="heat-stat-num"><?= $totalEvaluados ?></span>
            <span class="heat-stat-label">Colaboradores evaluados</span>
        </div>
        <div class="heat-stat-div"></div>
        <div class="heat-stat">
            <span class="heat-stat-num"><?= $totalCriterios ?></span>
            <span class="heat-stat-label">Criterios de evaluación</span>
        </div>
        <div class="heat-stat-div"></div>
        <div class="heat-stat">
            <span class="heat-stat-num"><?= $totalColab - $totalEvaluados ?></span>
            <span class="heat-stat-label">Sin evaluar</span>
        </div>
    </div>

    <!-- LEYENDA -->
    <div class="heat-leyenda">
        <span class="heat-leyenda-label">Escala de color:</span>
        <div class="heat-leyenda-items">
            <span class="heat-chip" style="background:#fecaca;color:#b91c1c">0–2.4</span>
            <span class="heat-chip" style="background:#fed7aa;color:#9a3412">2.5–3.9</span>
            <span class="heat-chip" style="background:#fde68a;color:#92400e">4–5.4</span>
            <span class="heat-chip" style="background:#fef9c3;color:#854d0e">5.5–6.9</span>
            <span class="heat-chip" style="background:#bbf7d0;color:#166534">7–8.4</span>
            <span class="heat-chip" style="background:#d1fae5;color:#065f46">8.5–10</span>
            <span class="heat-chip" style="background:#f1f5f9;color:#94a3b8">Sin dato</span>
        </div>
    </div>

    <!-- FILTRO POR ÁREA -->
    <div class="heat-toolbar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscarColab" class="search-global" placeholder="Buscar colaborador..." autocomplete="off">
            <button class="search-clear" id="heatSearchClear">✕</button>
        </div>
        <select id="filtroAreaHeat" class="heat-select">
            <option value="">Todas las áreas</option>
            <?php
            $areasOpts = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
            while ($ao = $areasOpts->fetch_assoc()):
            ?>
            <option value="<?= htmlspecialchars($ao['Nombre']) ?>"><?= htmlspecialchars($ao['Nombre']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- HEATMAP TABLE -->
    <?php if (empty($colabs)): ?>
    <div class="heat-empty">
        <span class="heat-empty-icon">🗺️</span>
        <p class="heat-empty-title">Sin datos para mostrar</p>
        <p class="heat-empty-sub">El heatmap se genera automáticamente cuando existen evaluaciones registradas.</p>
        <a href="evaluacion.php" class="btn-evaluar">▶ Empezar primera evaluación</a>
    </div>

    <?php else: ?>
    <div class="heat-wrap" id="heatWrap">
        <div class="heat-scroll">
            <table class="heat-table" id="heatTable">
                <thead>
                    <tr>
                        <th class="heat-th-fixed">Colaborador</th>
                        <th class="heat-th-fixed heat-th-area">Área</th>
                        <th class="heat-th-prom">Prom.</th>
                        <?php foreach ($criterios as $crit): ?>
                        <th class="heat-th-crit" title="<?= htmlspecialchars($crit['Nombre_Criterio']) ?>">
                            <div class="heat-th-txt"><?= htmlspecialchars($crit['Nombre_Criterio']) ?></div>
                            <div class="heat-th-max">/<?= (float)$crit['Evaluando'] ?></div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($colabs as $id => $colab):
                    $promColor = heatColor($colab['promedio']);
                    $ini = mb_strtoupper(mb_substr($colab['nombre'], 0, 1));
                ?>
                <tr class="heat-row"
                    data-nombre="<?= strtolower(htmlspecialchars($colab['nombre'])) ?>"
                    data-area="<?= htmlspecialchars($colab['area']) ?>">

                    <td class="heat-td-colab">
                        <div class="heat-avatar"><?= $ini ?></div>
                        <div class="heat-colab-info">
                            <span class="heat-colab-nombre"><?= htmlspecialchars($colab['nombre']) ?></span>
                        </div>
                    </td>

                    <td class="heat-td-area"><?= htmlspecialchars($colab['area']) ?></td>

                    <td class="heat-td-prom">
                        <span class="heat-prom-badge"
                              style="background:<?= $promColor['bg'] ?>;color:<?= $promColor['text'] ?>">
                            <?= $colab['promedio'] ?>
                        </span>
                    </td>

                    <?php foreach ($criterios as $crit):
                        // Verificar si este criterio fue evaluado en esta evaluación
                        $tieneEval = in_array($crit['Nombre_Criterio'], $colab['criterios']);
                        $val       = $tieneEval ? $colab['promedio'] : null;
                        // Normalizar al rango del criterio
                        if ($tieneEval) {
                            $maxCrit = (float)$crit['Evaluando'];
                            $valNorm = $maxCrit > 0 ? round(($colab['promedio'] / 10) * $maxCrit, 1) : $colab['promedio'];
                            $color   = heatColor($valNorm, $maxCrit);
                        } else {
                            $color = heatColor(null);
                        }
                    ?>
                    <td class="heat-cell"
                        style="background:<?= $color['bg'] ?>;color:<?= $color['text'] ?>"
                        title="<?= $crit['Nombre_Criterio'] ?>: <?= $tieneEval ? $color['label'] : 'No evaluado' ?>">
                        <?= $color['label'] ?>
                    </td>
                    <?php endforeach; ?>

                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SIN RESULTADOS -->
    <div class="heat-no-results" id="heatNoResults" style="display:none">
        <span>🔍</span>
        <p>Sin resultados para los filtros aplicados.</p>
    </div>
    <?php endif; ?>

</main>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const buscar   = document.getElementById('buscarColab');
    const clearBtn = document.getElementById('heatSearchClear');
    const filtArea = document.getElementById('filtroAreaHeat');
    const noRes    = document.getElementById('heatNoResults');

    function filtrar() {
        const q    = (buscar?.value || '').toLowerCase().trim();
        const area = (filtArea?.value || '').toLowerCase();
        const filas = document.querySelectorAll('.heat-row');
        let visible  = 0;

        filas.forEach(fila => {
            const nombre  = fila.dataset.nombre || '';
            const fArea   = (fila.dataset.area  || '').toLowerCase();
            const matchQ  = !q    || nombre.includes(q);
            const matchA  = !area || fArea === area;
            fila.style.display = (matchQ && matchA) ? '' : 'none';
            if (matchQ && matchA) visible++;
        });

        if (clearBtn) clearBtn.classList.toggle('visible', q.length > 0);
        if (noRes)    noRes.style.display = visible === 0 ? 'block' : 'none';
    }

    buscar?.addEventListener('input', filtrar);
    filtArea?.addEventListener('change', filtrar);
    clearBtn?.addEventListener('click', () => {
        if (buscar) buscar.value = '';
        filtrar();
        buscar?.focus();
    });
    buscar?.addEventListener('keydown', e => { if (e.key === 'Escape') { buscar.value=''; filtrar(); }});

});
</script>
</body>
</html>