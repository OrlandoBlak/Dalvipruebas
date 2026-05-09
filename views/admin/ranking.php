<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];
$totalAreas = $conexion->query("SELECT COUNT(*) as t FROM areas")->fetch_assoc()['t'];

// Detectar FK de área
$fkCol = 'FK_Id_Area';
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($col = $cols->fetch_assoc()) {
    if (stripos($col['Field'], 'area') !== false) { $fkCol = $col['Field']; break; }
}

// Query principal: ranking por promedio de evaluaciones
$res = $conexion->query("
    SELECT
        c.Id_Colaborador,
        c.Nombre                        AS colab_nombre,
        c.Cargo,
        a.Id_Area,
        a.Nombre                        AS area_nombre,
        ROUND(AVG(e.Evaluacion), 2)     AS promedio,
        COUNT(e.Id_Evaluacion)          AS total_evals,
        (SELECT ins.Descripcion
         FROM evaluaciones e2
         LEFT JOIN insignias ins ON ins.Id_Insignia = e2.Id_Insignia
         WHERE e2.Id_Colaborador = c.Id_Colaborador
         ORDER BY e2.Id_Evaluacion DESC LIMIT 1) AS insignia_nombre,
        (SELECT est.Descripcion
         FROM reportes r2
         INNER JOIN estadisticas est ON est.Id_Estadistica = r2.Id_Estadistica
         WHERE r2.Id_Colaborador = c.Id_Colaborador
         ORDER BY r2.Id_Data DESC LIMIT 1) AS nivel
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY c.Id_Colaborador, c.Nombre, c.Cargo, a.Id_Area, a.Nombre
    ORDER BY promedio DESC, total_evals DESC
");

$ranking = [];
if ($res) { while ($r = $res->fetch_assoc()) $ranking[] = $r; }
else { error_log('Ranking query error: ' . $conexion->error); }

$totalEvaluados  = count($ranking);
$promedioGeneral = $totalEvaluados
    ? round(array_sum(array_column($ranking, 'promedio')) / $totalEvaluados, 1)
    : 0;

// Iconos área
$iconosAreas = [
    1=>'🛒',2=>'👥',3=>'📢',4=>'🚚',5=>'🏛️',
    6=>'💻',7=>'🛍️',8=>'📦',9=>'🎨',10=>'🧹',
    11=>'👔',12=>'🏪',13=>'🏗️',
];

function estrellas($val, $max = 5) {
    $l = (int) round(($val / 10) * $max);
    $s = '';
    for ($i = 1; $i <= $max; $i++)
        $s .= $i <= $l ? '<span class="star filled">★</span>' : '<span class="star">★</span>';
    return $s;
}

function nivelBadge($nivel) {
    if (!$nivel) return '';
    $d = strtolower($nivel);
    $cls = str_contains($d,'excep') ? 'excepcional'
         : (str_contains($d,'camino') ? 'encamino'
         : (str_contains($d,'desarr') ? 'endesarrollo' : 'requiere'));
    return "<span class=\"rank-nivel $cls\">$nivel</span>";
}

function medallaIcon($pos) {
    return match($pos) {
        1 => '🥇', 2 => '🥈', 3 => '🥉', default => ''
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking General – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/ranking.css">
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
        <a href="ranking.php" class="nav-item active"><span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span></a>
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
        <button class="btn-tour">▶ Ver Tour de Bienvenida</button>
    </div>
</aside>

<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">🏆 Ranking General</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <!-- STATS -->
    <div class="rank-stats">
        <div class="rank-stat">
            <span class="rank-stat-num"><?= $totalEvaluados ?></span>
            <span class="rank-stat-label">Evaluados</span>
        </div>
        <div class="rank-stat-div"></div>
        <div class="rank-stat">
            <span class="rank-stat-num"><?= $promedioGeneral ?><span class="rank-stat-den">/10</span></span>
            <span class="rank-stat-label">Promedio general</span>
        </div>
        <div class="rank-stat-div"></div>
        <div class="rank-stat">
            <span class="rank-stat-num"><?= $totalColab - $totalEvaluados ?></span>
            <span class="rank-stat-label">Sin evaluar</span>
        </div>
        <div class="rank-stat-div"></div>
        <div class="rank-stat">
            <?php
            $top = $ranking[0] ?? null;
            if ($top): ?>
            <span class="rank-stat-num rank-top-nombre"><?= htmlspecialchars(explode(' ', $top['colab_nombre'])[0]) ?></span>
            <span class="rank-stat-label">Líder actual 🥇</span>
            <?php else: ?>
            <span class="rank-stat-num">—</span>
            <span class="rank-stat-label">Líder actual</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOP 3 PODIO -->
    <?php if ($totalEvaluados >= 1): ?>
    <div class="rank-podio">
        <?php
        $podio = array_slice($ranking, 0, 3);
        // Reordenar para el podio visual: 2º - 1º - 3º
        $orden = [];
        if (isset($podio[1])) $orden[] = ['data' => $podio[1], 'pos' => 2];
        if (isset($podio[0])) $orden[] = ['data' => $podio[0], 'pos' => 1];
        if (isset($podio[2])) $orden[] = ['data' => $podio[2], 'pos' => 3];

        foreach ($orden as $item):
            $p   = $item['pos'];
            $c   = $item['data'];
            $ini = mb_strtoupper(mb_substr($c['colab_nombre'], 0, 1));
            $podioClass = "podio-$p";
        ?>
        <div class="podio-item <?= $podioClass ?>">
            <div class="podio-medalla"><?= medallaIcon($p) ?></div>
            <div class="podio-avatar"><?= $ini ?></div>
            <div class="podio-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></div>
            <div class="podio-area"><?= htmlspecialchars($c['area_nombre']) ?></div>
            <div class="podio-score"><?= number_format($c['promedio'], 1) ?></div>
            <div class="podio-stars"><?= estrellas($c['promedio']) ?></div>
            <div class="podio-base podio-base-<?= $p ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- BUSCADOR Y FILTROS -->
    <div class="rank-toolbar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscarRanking" class="search-global"
                   placeholder="Buscar colaborador o área..." autocomplete="off">
            <button class="search-clear" id="rankSearchClear">✕</button>
        </div>
        <div class="rank-filtros">
            <select id="filtroArea" class="rank-select">
                <option value="">Todas las áreas</option>
                <?php
                $areas = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
                while ($a = $areas->fetch_assoc()):
                ?>
                <option value="<?= $a['Id_Area'] ?>"><?= htmlspecialchars($a['Nombre']) ?></option>
                <?php endwhile; ?>
            </select>
            <select id="filtroNivel" class="rank-select">
                <option value="">Todos los niveles</option>
                <option value="Excepcional">🟢 Excepcional</option>
                <option value="En camino">🔵 En camino</option>
                <option value="En desarrollo">🟡 En desarrollo</option>
                <option value="Requiere atencion">🔴 Requiere atención</option>
            </select>
        </div>
    </div>

    <!-- TABLA RANKING -->
    <div class="rank-content">
        <?php if (empty($ranking)): ?>
        <div class="rank-empty">
            <span class="rank-empty-icon">🏆</span>
            <p class="rank-empty-title">Sin evaluaciones registradas</p>
            <p class="rank-empty-sub">El ranking se actualizará automáticamente conforme se evalúen colaboradores.</p>
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar primera evaluación</a>
        </div>

        <?php else: ?>
        <div class="rank-tabla">

            <!-- Header -->
            <div class="rank-tabla-header">
                <span class="col-rank-pos">#</span>
                <span class="col-rank-colab">Colaborador</span>
                <span class="col-rank-area">Área</span>
                <span class="col-rank-nivel">Nivel</span>
                <span class="col-rank-evals">Evals</span>
                <span class="col-rank-score">Promedio</span>
            </div>

            <!-- Filas -->
            <?php foreach ($ranking as $pos => $c):
                $num    = $pos + 1;
                $medal  = medallaIcon($num);
                $ini    = mb_strtoupper(mb_substr($c['colab_nombre'], 0, 1));
                $icon   = $iconosAreas[$c['Id_Area']] ?? '🏢';
                $topCls = $num <= 3 ? "rank-fila-top rank-top-$num" : '';
            ?>
            <div class="rank-fila <?= $topCls ?>"
                 data-nombre="<?= strtolower(htmlspecialchars($c['colab_nombre'])) ?>"
                 data-area="<?= $c['Id_Area'] ?>"
                 data-nivel="<?= htmlspecialchars($c['nivel'] ?? '') ?>">

                <span class="col-rank-pos">
                    <?= $medal ?: "<span class='rank-num'>$num</span>" ?>
                </span>

                <span class="col-rank-colab">
                    <div class="rank-avatar rank-avatar-<?= $num <= 3 ? $num : '0' ?>"><?= $ini ?></div>
                    <div class="rank-colab-info">
                        <span class="rank-colab-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></span>
                        <?php if ($c['Cargo']): ?>
                        <span class="rank-colab-cargo"><?= htmlspecialchars($c['Cargo']) ?></span>
                        <?php endif; ?>
                        <div class="rank-stars"><?= estrellas($c['promedio']) ?></div>
                    </div>
                </span>

                <span class="col-rank-area">
                    <span class="rank-area-icon"><?= $icon ?></span>
                    <?= htmlspecialchars($c['area_nombre']) ?>
                </span>

                <span class="col-rank-nivel">
                    <?= nivelBadge($c['nivel']) ?>
                </span>

                <span class="col-rank-evals">
                    <span class="rank-evals-badge"><?= $c['total_evals'] ?></span>
                </span>

                <span class="col-rank-score">
                    <span class="rank-score-num <?= $num === 1 ? 'score-gold' : ($num === 2 ? 'score-silver' : ($num === 3 ? 'score-bronze' : '')) ?>">
                        <?= number_format($c['promedio'], 1) ?>
                    </span>
                    <span class="rank-score-max">/10</span>
                </span>

            </div>
            <?php endforeach; ?>

        </div>

        <!-- Sin resultados filtro -->
        <div class="rank-no-results" id="rankNoResults" style="display:none">
            <span>🔍</span>
            <p>Sin resultados para los filtros aplicados.</p>
            <button class="btn-secondary" onclick="limpiarFiltros()">Limpiar filtros</button>
        </div>

        <?php endif; ?>
    </div>

</main>

<script src="../../functions/admin_dashboard.js"></script>
<script>
// ── RANKING JS ─────────────────────────────────────────────
(function() {
    const buscar      = document.getElementById('buscarRanking');
    const clearBtn    = document.getElementById('rankSearchClear');
    const filtroArea  = document.getElementById('filtroArea');
    const filtroNivel = document.getElementById('filtroNivel');
    const noResults   = document.getElementById('rankNoResults');

    function filtrar() {
        const q     = (buscar?.value || '').toLowerCase().trim();
        const area  = filtroArea?.value  || '';
        const nivel = (filtroNivel?.value || '').toLowerCase();
        const filas = document.querySelectorAll('.rank-fila');
        let visible = 0;

        filas.forEach(fila => {
            const nombre   = fila.dataset.nombre || '';
            const fArea    = fila.dataset.area   || '';
            const fNivel   = (fila.dataset.nivel || '').toLowerCase();

            const matchQ     = !q     || nombre.includes(q);
            const matchArea  = !area  || fArea === area;
            const matchNivel = !nivel || fNivel.includes(nivel.replace('atencion','aten'));

            const show = matchQ && matchArea && matchNivel;
            fila.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
        if (clearBtn)  clearBtn.classList.toggle('visible', q.length > 0);
    }

    buscar?.addEventListener('input', filtrar);
    filtroArea?.addEventListener('change', filtrar);
    filtroNivel?.addEventListener('change', filtrar);

    clearBtn?.addEventListener('click', () => {
        if (buscar) buscar.value = '';
        filtrar();
        buscar?.focus();
    });

    buscar?.addEventListener('keydown', e => {
        if (e.key === 'Escape') { buscar.value=''; filtrar(); }
    });

    window.limpiarFiltros = () => {
        if (buscar)      buscar.value      = '';
        if (filtroArea)  filtroArea.value  = '';
        if (filtroNivel) filtroNivel.value = '';
        filtrar();
    };
})();
</script>
</body>
</html>