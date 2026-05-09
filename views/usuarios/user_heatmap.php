<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Usuario') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

$fkCol = 'FK_Id_Area';
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($c = $cols->fetch_assoc()) {
    if (stripos($c['Field'], 'area') !== false) { $fkCol = $c['Field']; break; }
}

$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];

$criteriosRes = $conexion->query("SELECT Id_Criterios, Nombre_Criterio, Evaluando FROM puntos ORDER BY Nombre_Criterio ASC");
$criterios    = [];
while ($c = $criteriosRes->fetch_assoc()) $criterios[] = $c;
$totalCriterios = count($criterios);

$evalRes = $conexion->query("
    SELECT c.Id_Colaborador, c.Nombre AS colab_nombre, a.Nombre AS area_nombre,
           e.Criterios, e.Evaluacion, e.Id_Evaluacion
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    ORDER BY a.Nombre ASC, c.Nombre ASC, e.Id_Evaluacion DESC
");
$colabs = [];
while ($row = $evalRes->fetch_assoc()) {
    $id = $row['Id_Colaborador'];
    if (isset($colabs[$id])) continue;
    $colabs[$id] = ['nombre'=>$row['colab_nombre'],'area'=>$row['area_nombre'],'criterios'=>array_map('trim',explode(',',$row['Criterios']??'')),'promedio'=>(float)$row['Evaluacion']];
}
$totalEvaluados = count($colabs);

function heatColor($val, $max=10) {
    if ($val===null) return ['bg'=>'#f1f5f9','text'=>'#94a3b8','label'=>'—'];
    $p=min($val/$max,1);
    if ($p>=0.85) return ['bg'=>'#d1fae5','text'=>'#065f46','label'=>number_format($val,1)];
    if ($p>=0.70) return ['bg'=>'#bbf7d0','text'=>'#166534','label'=>number_format($val,1)];
    if ($p>=0.55) return ['bg'=>'#fef9c3','text'=>'#854d0e','label'=>number_format($val,1)];
    if ($p>=0.40) return ['bg'=>'#fde68a','text'=>'#92400e','label'=>number_format($val,1)];
    if ($p>=0.25) return ['bg'=>'#fed7aa','text'=>'#9a3412','label'=>number_format($val,1)];
    return               ['bg'=>'#fecaca','text'=>'#b91c1c','label'=>number_format($val,1)];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heatmap – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/user.css">
    <link rel="stylesheet" href="../../css/heatmap.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<aside class="sidebar user-sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../../assets/logo.jpeg" alt="Logo" class="logo-img" onerror="this.style.display='none'">
            <div class="logo-text"><span class="logo-group">GRUPO</span><span class="logo-name">GRUPO DALVI</span><span class="logo-sub">EVALUACIÓN</span></div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">✕</button>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">PANEL</div>
        <a href="homeuser.php" class="nav-item"><span class="nav-icon">⊞</span><span class="nav-text">Resumen General</span></a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="user_ranking.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span></a>
        <a href="user_heatmap.php" class="nav-item active"><span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span></a>
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
            <a href="evaluacion_user.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <div class="heat-stats">
        <div class="heat-stat"><span class="heat-stat-num"><?= $totalEvaluados ?></span><span class="heat-stat-label">Evaluados</span></div>
        <div class="heat-stat-div"></div>
        <div class="heat-stat"><span class="heat-stat-num"><?= $totalCriterios ?></span><span class="heat-stat-label">Criterios</span></div>
        <div class="heat-stat-div"></div>
        <div class="heat-stat"><span class="heat-stat-num"><?= $totalColab - $totalEvaluados ?></span><span class="heat-stat-label">Sin evaluar</span></div>
    </div>

    <div class="heat-leyenda">
        <span class="heat-leyenda-label">Escala:</span>
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

    <div class="heat-toolbar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscarColab" class="search-global" placeholder="Buscar colaborador..." autocomplete="off">
            <button class="search-clear" id="heatClear">✕</button>
        </div>
        <select id="filtroArea" class="heat-select">
            <option value="">Todas las áreas</option>
            <?php $ar=$conexion->query("SELECT Id_Area,Nombre FROM areas ORDER BY Nombre"); while($a=$ar->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($a['Nombre']) ?>"><?= htmlspecialchars($a['Nombre']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <?php if (empty($colabs)): ?>
    <div class="heat-empty">
        <span class="heat-empty-icon">🗺️</span>
        <p class="heat-empty-title">Sin datos para mostrar</p>
        <p class="heat-empty-sub">El heatmap se genera automáticamente con las evaluaciones.</p>
    </div>
    <?php else: ?>
    <div class="heat-wrap" id="heatWrap">
        <div class="heat-scroll">
            <table class="heat-table">
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
                    $pc = heatColor($colab['promedio']);
                    $ini = mb_strtoupper(mb_substr($colab['nombre'],0,1));
                ?>
                <tr class="heat-row"
                    data-nombre="<?= strtolower(htmlspecialchars($colab['nombre'])) ?>"
                    data-area="<?= htmlspecialchars($colab['area']) ?>">
                    <td class="heat-td-colab">
                        <div class="heat-avatar"><?= $ini ?></div>
                        <div class="heat-colab-info"><span class="heat-colab-nombre"><?= htmlspecialchars($colab['nombre']) ?></span></div>
                    </td>
                    <td class="heat-td-area"><?= htmlspecialchars($colab['area']) ?></td>
                    <td class="heat-td-prom">
                        <span class="heat-prom-badge" style="background:<?= $pc['bg'] ?>;color:<?= $pc['text'] ?>"><?= $colab['promedio'] ?></span>
                    </td>
                    <?php foreach ($criterios as $crit):
                        $tiene = in_array($crit['Nombre_Criterio'], $colab['criterios']);
                        if ($tiene) {
                            $mx = (float)$crit['Evaluando'];
                            $vn = $mx>0 ? round(($colab['promedio']/10)*$mx,1) : $colab['promedio'];
                            $cl = heatColor($vn,$mx);
                        } else { $cl = heatColor(null); }
                    ?>
                    <td class="heat-cell" style="background:<?= $cl['bg'] ?>;color:<?= $cl['text'] ?>"
                        title="<?= $crit['Nombre_Criterio'] ?>: <?= $tiene?$cl['label']:'No evaluado' ?>">
                        <?= $cl['label'] ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="heat-no-results" id="heatNoResults" style="display:none">
        <span>🔍</span><p>Sin resultados.</p>
    </div>
    <?php endif; ?>
</main>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscar   = document.getElementById('buscarColab');
    const clearBtn = document.getElementById('heatClear');
    const filtArea = document.getElementById('filtroArea');
    const noRes    = document.getElementById('heatNoResults');
    function filtrar() {
        const q    = (buscar?.value||'').toLowerCase().trim();
        const area = (filtArea?.value||'').toLowerCase();
        let vis    = 0;
        document.querySelectorAll('.heat-row').forEach(f => {
            const ok = (!q||(f.dataset.nombre||'').includes(q)) && (!area||(f.dataset.area||'').toLowerCase()===area);
            f.style.display=ok?'':'none'; if(ok) vis++;
        });
        if(clearBtn) clearBtn.classList.toggle('visible', (buscar?.value||'').length>0);
        if(noRes) noRes.style.display=vis===0?'block':'none';
    }
    buscar?.addEventListener('input', filtrar);
    filtArea?.addEventListener('change', filtrar);
    clearBtn?.addEventListener('click', () => { buscar.value=''; filtrar(); });
});
</script>
</body>
</html>