<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Usuario') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

$idDalvi = (int)$_SESSION['id'];

// Detectar columnas reales de asesores
$colsAsesor = $conexion->query("SHOW COLUMNS FROM asesores");
$camposAsesor = [];
while ($ca = $colsAsesor->fetch_assoc()) $camposAsesor[] = $ca['Field'];

$colNombre = null; $colFkArea = null;
foreach ($camposAsesor as $f) {
    if (!$colNombre  && (stripos($f,'name')!==false || stripos($f,'nombre')!==false) && stripos($f,'user')===false) $colNombre = $f;
    if (!$colFkArea  && stripos($f,'area')!==false) $colFkArea = $f;
}
$colNombre = $colNombre ?? 'UserName'; // fallback al username

// Datos del asesor logueado
$asesorQ = $colFkArea
    ? "SELECT s.UserName, s.`$colNombre` AS NombreDisplay, s.Rol, a.Nombre AS area_nombre
       FROM asesores s LEFT JOIN areas a ON a.Id_Area = s.`$colFkArea`
       WHERE s.Id_Dalvi = $idDalvi LIMIT 1"
    : "SELECT s.UserName, s.`$colNombre` AS NombreDisplay, s.Rol, '' AS area_nombre
       FROM asesores s WHERE s.Id_Dalvi = $idDalvi LIMIT 1";

$asesorRes = $conexion->query($asesorQ);
$asesor    = $asesorRes ? $asesorRes->fetch_assoc() : null;

$nombreUser = $asesor['NombreDisplay'] ?? $asesor['UserName'] ?? $_SESSION['usuario'] ?? 'Usuario';
$areaNombre = $asesor['area_nombre']   ?? '—';
$inicial    = mb_strtoupper(mb_substr($nombreUser, 0, 1));

// Detectar FK área colaboradores
$fkCol = 'FK_Id_Area';
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($c = $cols->fetch_assoc()) {
    if (stripos($c['Field'], 'area') !== false) { $fkCol = $c['Field']; break; }
}

// Stats generales
$stats = $conexion->query("
    SELECT
        (SELECT COUNT(*) FROM colaboradores) AS total_colab,
        (SELECT COUNT(*) FROM areas)         AS total_areas,
        COALESCE(ROUND(AVG(e.Evaluacion),1),0) AS promedio,
        SUM(CASE WHEN e.Evaluacion >= 7 THEN 1 ELSE 0 END) AS en_meta
    FROM evaluaciones e
")->fetch_assoc();

$totalColab = (int)$stats['total_colab'];
$totalAreas = (int)$stats['total_areas'];
$promedio   = (float)$stats['promedio'];
$enMeta     = (int)$stats['en_meta'];

// Notificaciones (colaboradores con promedio < 6)
$notifRes = $conexion->query("
    SELECT c.Nombre AS colab_nombre, a.Nombre AS area_nombre,
           ROUND(AVG(e.Evaluacion),1) AS promedio
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY c.Id_Colaborador, c.Nombre, a.Nombre
    HAVING promedio < 6
    ORDER BY promedio ASC LIMIT 10
");
$notifs     = [];
while ($n = $notifRes->fetch_assoc()) $notifs[] = $n;
$totalNotif = count($notifs);

// Áreas con colaboradores
$areas = $conexion->query("
    SELECT a.Id_Area, a.Nombre,
           COUNT(c.Id_Colaborador) AS total_colab
    FROM areas a
    LEFT JOIN colaboradores c ON c.`$fkCol` = a.Id_Area
    GROUP BY a.Id_Area, a.Nombre ORDER BY a.Id_Area ASC
");

// Top 5 colaboradores
$topColab = $conexion->query("
    SELECT c.Id_Colaborador, c.Nombre AS colab_nombre,
           a.Nombre AS area_nombre,
           ROUND(AVG(e.Evaluacion),1) AS promedio
    FROM colaboradores c
    LEFT JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    LEFT JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY c.Id_Colaborador, c.Nombre, a.Nombre
    ORDER BY promedio DESC LIMIT 5
");

$iconosAreas = [1=>'🛒',2=>'👥',3=>'📢',4=>'🚚',5=>'🏛️',6=>'💻',7=>'🛍️',8=>'📦',9=>'🎨',10=>'🧹',11=>'👔',12=>'🏪',13=>'🏗️'];

function estrellas($v,$m=5){$l=(int)round(($v/10)*$m);$s='';for($i=1;$i<=$m;$i++)$s.=$i<=$l?'<span class="star filled">★</span>':'<span class="star">★</span>';return $s;}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/user.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
</head>
<body>

<aside class="sidebar user-sidebar" id="sidebar">
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
        <a href="homeuser.php" class="nav-item active">
            <span class="nav-icon">⊞</span><span class="nav-text">Resumen General</span>
        </a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="user_ranking.php" class="nav-item">
            <span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span>
        </a>
        <a href="user_heatmap.php" class="nav-item">
            <span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="colab-count">
            <span class="colab-number"><?= $totalColab ?></span>
            <span class="colab-label">COLABORADORES</span>
        </div>
        <div class="sidebar-actions">
            <span class="status-dot"></span>
            <span class="status-text"><?= htmlspecialchars($nombreUser) ?></span>
            <a href="../../php/logout.php" class="btn-logout">↩ Salir</a>
        </div>
    </div>
</aside>

<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">Resumen General</h1>
        </div>
        <div class="topbar-right">
            <!-- Campana notificaciones -->
            <div class="notif-wrap" id="notifWrap">
                <button class="btn-notif" id="btnNotif" title="Colaboradores en riesgo">
                    🔔
                    <?php if ($totalNotif > 0): ?>
                    <span class="notif-badge"><?= $totalNotif ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown" style="display:none">
                    <div class="notif-dropdown-header">
                        <span class="notif-dropdown-title">⚠️ Requieren atención</span>
                        <span class="notif-dropdown-count"><?= $totalNotif ?></span>
                    </div>
                    <?php if (empty($notifs)): ?>
                    <div class="notif-empty"><span>✅</span><p>Todos en buen desempeño</p></div>
                    <?php else: ?>
                    <div class="notif-lista">
                        <?php foreach ($notifs as $n):
                            $color = $n['promedio'] < 4 ? '#ef4444' : '#f59e0b';
                            $ini2  = mb_strtoupper(mb_substr($n['colab_nombre'],0,1));
                        ?>
                        <div class="notif-item">
                            <div class="notif-avatar" style="background:<?= $color ?>20;border:1.5px solid <?= $color ?>40;color:<?= $color ?>"><?= $ini2 ?></div>
                            <div class="notif-info">
                                <span class="notif-nombre"><?= htmlspecialchars($n['colab_nombre']) ?></span>
                                <span class="notif-area"><?= htmlspecialchars($n['area_nombre']) ?></span>
                            </div>
                            <span class="notif-score" style="color:<?= $color ?>"><?= $n['promedio'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="user_ranking.php" class="notif-ver-todos">Ver ranking completo →</a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Solo botón evaluar -->
            <a href="evaluacion_user.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <!-- BIENVENIDA -->
    <div class="user-welcome">
        <div class="user-welcome-avatar"><?= $inicial ?></div>
        <div class="user-welcome-info">
            <div class="user-welcome-greeting">Bienvenido de vuelta</div>
            <div class="user-welcome-nombre"><?= htmlspecialchars($nombreUser) ?></div>
            <div class="user-welcome-area"><?= htmlspecialchars($areaNombre) ?></div>
        </div>
        <div class="user-welcome-score">
            <span class="user-score-num"><?= $promedio ?><span class="user-score-den">/10</span></span>
            <div class="user-score-stars"><?= estrellas($promedio) ?></div>
        </div>
    </div>

    <!-- STATS -->
    <section class="stats-grid">
        <div class="stat-card stat-promedio">
            <div class="stat-icon">🏆</div>
            <div class="stat-body">
                <div class="stat-value"><?= $promedio ?><span class="stat-denom">/10</span></div>
                <div class="stat-label">PROMEDIO GENERAL</div>
                <div class="stat-stars"><?= estrellas($promedio) ?></div>
            </div>
            <div class="stat-bg-shape"></div>
        </div>
        <div class="stat-card stat-colab">
            <div class="stat-icon">👥</div>
            <div class="stat-body">
                <div class="stat-value"><?= $totalColab ?></div>
                <div class="stat-label">COLABORADORES ACTIVOS</div>
            </div>
            <div class="stat-bg-shape"></div>
        </div>
        <div class="stat-card stat-meta">
            <div class="stat-icon">✅</div>
            <div class="stat-body">
                <div class="stat-value"><?= $enMeta ?><span class="stat-denom"> / <?= $totalColab ?></span></div>
                <div class="stat-label">EN META (≥ 7/10)</div>
            </div>
            <div class="stat-bg-shape"></div>
        </div>
        <div class="stat-card stat-deptos">
            <div class="stat-icon">🏢</div>
            <div class="stat-body">
                <div class="stat-value"><?= $totalAreas ?></div>
                <div class="stat-label">DEPARTAMENTOS</div>
            </div>
            <div class="stat-bg-shape"></div>
        </div>
    </section>

    <!-- POR DEPARTAMENTO -->
    <section class="section-block">
        <div class="section-header">
            <span class="section-icon">🏢</span>
            <h2 class="section-title">POR DEPARTAMENTO</h2>
            <span class="section-count"><?= $totalAreas ?> áreas</span>
        </div>
        <div class="deptos-grid">
            <?php while ($a = $areas->fetch_assoc()):
                $icon  = $iconosAreas[$a['Id_Area']] ?? '🏢';
                $count = (int)$a['total_colab'];
            ?>
            <div class="depto-card" style="cursor:default">
                <div class="depto-card-top">
                    <span class="depto-card-icon"><?= $icon ?></span>
                    <span class="depto-card-badge <?= $count > 0 ? '' : 'sin' ?>"><?= $count ?></span>
                </div>
                <div class="depto-card-nombre"><?= htmlspecialchars($a['Nombre']) ?></div>
                <div class="depto-card-colab">
                    <span class="colab-dot"></span>
                    <?= $count ?> colaborador<?= $count !== 1 ? 'es' : '' ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- TOP COLABORADORES -->
    <section class="section-block">
        <div class="section-header">
            <span class="section-icon">🏅</span>
            <h2 class="section-title">TOP COLABORADORES</h2>
            <a href="user_ranking.php" class="section-ver-mas">Ver ranking completo →</a>
        </div>
        <div class="top-colab-list">
            <?php
            $medallas = ['🥇','🥈','🥉']; $pos = 0;
            if ($topColab && $topColab->num_rows > 0):
                while ($c = $topColab->fetch_assoc()):
                    $medal = $medallas[$pos] ?? ($pos+1);
                    $ini2  = mb_strtoupper(mb_substr($c['colab_nombre'],0,1));
            ?>
            <div class="colab-row <?= $pos===0?'colab-top1':'' ?>">
                <div class="colab-rank"><?= $medal ?></div>
                <div class="colab-avatar"><div class="avatar-circle"><?= $ini2 ?></div></div>
                <div class="colab-data">
                    <div class="colab-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></div>
                    <div class="colab-area"><?= htmlspecialchars($c['area_nombre']??'—') ?></div>
                    <div class="colab-stars"><?= estrellas($c['promedio']??0) ?></div>
                </div>
                <div class="colab-score"><?= $c['promedio'] ?? '<span class="sin-score">—</span>' ?></div>
            </div>
            <?php $pos++; endwhile;
            else: ?>
            <p class="no-data">Sin evaluaciones aún. <a href="evaluacion_user.php" style="color:var(--accent);font-weight:600">Iniciar evaluación →</a></p>
            <?php endif; ?>
        </div>
    </section>

</main>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnNotif      = document.getElementById('btnNotif');
    const notifDropdown = document.getElementById('notifDropdown');
    btnNotif?.addEventListener('click', e => {
        e.stopPropagation();
        const open = notifDropdown?.style.display === 'block';
        if (notifDropdown) notifDropdown.style.display = open ? 'none' : 'block';
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#notifWrap') && notifDropdown)
            notifDropdown.style.display = 'none';
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && notifDropdown) notifDropdown.style.display = 'none';
    });
});
</script>
</body>
</html>