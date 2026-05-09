<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];
$totalAreas = $conexion->query("SELECT COUNT(*) as t FROM areas")->fetch_assoc()['t'];

// ── Query principal: colaboradores evaluados agrupados por área ──
// Una sola query — trae el promedio más reciente por colaborador
// Detectar columna FK de área en colaboradores
$fkCol = 'FK_Id_Area';
$colsRes = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($col = $colsRes->fetch_assoc()) {
    if (stripos($col['Field'], 'area') !== false) {
        $fkCol = $col['Field'];
        break;
    }
}

$res = $conexion->query("
    SELECT
        a.Id_Area,
        a.Nombre                            AS area_nombre,
        c.Id_Colaborador,
        c.Nombre                            AS colab_nombre,
        ROUND(AVG(e.Evaluacion), 1)         AS promedio,
        COUNT(e.Id_Evaluacion)              AS total_evals,
        (SELECT est.Descripcion
         FROM reportes r2
         INNER JOIN estadisticas est ON est.Id_Estadistica = r2.Id_Estadistica
         WHERE r2.Id_Colaborador = c.Id_Colaborador
         ORDER BY r2.Id_Data DESC LIMIT 1)  AS nivel_desc
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY a.Id_Area, a.Nombre, c.Id_Colaborador, c.Nombre
    ORDER BY a.Nombre ASC, promedio DESC
");

if (!$res) {
    error_log('Dashboard query error: ' . $conexion->error);
}

// Agrupar en PHP por área
$porArea  = [];
$totalEvaluados = 0;
$sumaPromedios  = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = $row['Id_Area'];
        if (!isset($porArea[$id])) {
            $porArea[$id] = [
                'nombre'        => $row['area_nombre'],
                'colaboradores' => [],
            ];
        }
        $porArea[$id]['colaboradores'][] = $row;
        $totalEvaluados++;
        $sumaPromedios += (float)$row['promedio'];
    }
}

$promedioGeneral = $totalEvaluados > 0 ? round($sumaPromedios / $totalEvaluados, 1) : 0;
$areasConEval    = count($porArea);

// Iconos por área
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

function nivelClass($desc) {
    if (!$desc) return 'nivel-sin';
    $d = strtolower($desc);
    if (str_contains($d,'excep'))   return 'nivel-excepcional';
    if (str_contains($d,'camino'))  return 'nivel-encamino';
    if (str_contains($d,'desarr'))  return 'nivel-endesarrollo';
    return 'nivel-requiere';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ejecutivo – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
</head>
<body>

<!-- SIDEBAR -->
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
        <a href="heatmap.php" class="nav-item"><span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span></a>
        <a href="dashboard.php" class="nav-item active"><span class="nav-icon">📋</span><span class="nav-text">Dashboard Ejecutivo</span></a>
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

<!-- MAIN -->
<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">Dashboard Ejecutivo</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <!-- STATS RÁPIDAS -->
    <div class="dash-stats">
        <div class="dash-stat-item">
            <span class="dash-stat-num"><?= $totalEvaluados ?></span>
            <span class="dash-stat-label">Colaboradores evaluados</span>
        </div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat-item">
            <span class="dash-stat-num"><?= $promedioGeneral ?><span style="font-size:14px;color:var(--text-muted)">/10</span></span>
            <span class="dash-stat-label">Promedio general</span>
        </div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat-item">
            <span class="dash-stat-num"><?= $areasConEval ?></span>
            <span class="dash-stat-label">Áreas con evaluación</span>
        </div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat-item">
            <span class="dash-stat-num"><?= $totalColab - $totalEvaluados ?></span>
            <span class="dash-stat-label">Sin evaluar</span>
        </div>
    </div>

    <!-- BUSCADOR -->
    <div class="dash-toolbar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscarEvaluado" class="search-global"
                   placeholder="Buscar colaborador..." autocomplete="off">
            <button class="search-clear" id="searchClear">✕</button>
        </div>
        <span class="dash-hint"><?= $totalEvaluados ?> evaluado<?= $totalEvaluados !== 1 ? 's' : '' ?> · <?= $areasConEval ?> departamento<?= $areasConEval !== 1 ? 's' : '' ?></span>
    </div>

    <!-- LISTA POR DEPARTAMENTO -->
    <div class="dash-content" id="dashContent">

        <?php if (empty($porArea)): ?>
        <div class="dash-empty">
            <span class="dash-empty-icon">📋</span>
            <p class="dash-empty-title">Sin evaluaciones registradas</p>
            <p class="dash-empty-sub">Comienza evaluando a tus colaboradores.</p>
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar primera evaluación</a>
        </div>

        <?php else: ?>
        <?php foreach ($porArea as $idArea => $area):
            $icon  = $iconosAreas[$idArea] ?? '🏢';
            $count = count($area['colaboradores']);
            $promA = round(array_sum(array_column($area['colaboradores'], 'promedio')) / $count, 1);
        ?>

        <div class="dash-area-bloque" data-area="<?= strtolower(htmlspecialchars($area['nombre'])) ?>">

            <!-- Cabecera del área -->
            <div class="dash-area-header" onclick="toggleDashArea(this)">
                <div class="dash-area-left">
                    <span class="dash-area-icon"><?= $icon ?></span>
                    <div>
                        <span class="dash-area-nombre"><?= htmlspecialchars($area['nombre']) ?></span>
                        <span class="dash-area-sub"><?= $count ?> evaluado<?= $count !== 1 ? 's' : '' ?> · Prom. <?= $promA ?>/10</span>
                    </div>
                </div>
                <div class="dash-area-right">
                    <div class="dash-area-stars"><?= estrellas($promA) ?></div>
                    <span class="dash-area-chevron">▾</span>
                </div>
            </div>

            <!-- Lista de colaboradores -->
            <div class="dash-area-body">
                <?php foreach ($area['colaboradores'] as $i => $c):
                    $nivel = $c['nivel_desc'] ?? '';
                    $nCls  = nivelClass($nivel);
                    $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ''));
                    $ini   = mb_strtoupper(mb_substr($c['colab_nombre'], 0, 1));
                ?>
                <div class="dash-colab-row"
                     data-nombre="<?= strtolower(htmlspecialchars($c['colab_nombre'])) ?>">

                    <div class="dash-colab-pos"><?= $medal ?: ($i + 1) ?></div>

                    <div class="dash-colab-avatar"><?= $ini ?></div>

                    <div class="dash-colab-info">
                        <span class="dash-colab-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></span>
                        <div class="dash-colab-stars"><?= estrellas($c['promedio']) ?></div>
                    </div>

                    <div class="dash-colab-right">
                        <?php if ($nivel): ?>
                        <span class="dash-nivel-badge <?= $nCls ?>"><?= htmlspecialchars($nivel) ?></span>
                        <?php endif; ?>
                        <span class="dash-colab-score"><?= $c['promedio'] ?></span>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php endforeach; ?>

        <!-- Sin resultados de búsqueda -->
        <div class="dash-no-results" id="dashNoResults" style="display:none">
            <span>🔍</span>
            <p>Sin resultados para <strong id="dashTermino"></strong></p>
        </div>

        <?php endif; ?>
    </div>

</main>

<script src="../../functions/admin_dashboard.js"></script>
<script src="../../functions/dashboard.js"></script>
</body>
</html>