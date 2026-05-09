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

$ranking = [];
$res = $conexion->query("
    SELECT c.Id_Colaborador, c.Nombre AS colab_nombre, c.Cargo,
           a.Id_Area, a.Nombre AS area_nombre,
           ROUND(AVG(e.Evaluacion),2) AS promedio,
           COUNT(e.Id_Evaluacion)     AS total_evals,
           (SELECT est.Descripcion
            FROM reportes r2
            INNER JOIN estadisticas est ON est.Id_Estadistica = r2.Id_Estadistica
            WHERE r2.Id_Colaborador = c.Id_Colaborador
            ORDER BY r2.Id_Data DESC LIMIT 1) AS nivel
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY c.Id_Colaborador, c.Nombre, c.Cargo, a.Id_Area, a.Nombre
    ORDER BY promedio DESC
");
while ($r = $res->fetch_assoc()) $ranking[] = $r;

$totalEvaluados  = count($ranking);
$promedioGeneral = $totalEvaluados ? round(array_sum(array_column($ranking,'promedio'))/$totalEvaluados,1) : 0;

$iconosAreas = [1=>'🛒',2=>'👥',3=>'📢',4=>'🚚',5=>'🏛️',6=>'💻',7=>'🛍️',8=>'📦',9=>'🎨',10=>'🧹',11=>'👔',12=>'🏪',13=>'🏗️'];

function est($v,$m=5){$l=(int)round(($v/10)*$m);$s='';for($i=1;$i<=$m;$i++)$s.=$i<=$l?'<span class="star filled">★</span>':'<span class="star">★</span>';return $s;}
function nivelBadge($nivel){if(!$nivel)return '';$d=strtolower($nivel);$cls=str_contains($d,'excep')?'excepcional':(str_contains($d,'camino')?'encamino':(str_contains($d,'desarr')?'endesarrollo':'requiere'));return "<span class='rank-nivel $cls'>$nivel</span>";}
function medal($n){return match($n){1=>'🥇',2=>'🥈',3=>'🥉',default=>''};}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/user.css">
    <link rel="stylesheet" href="../../css/ranking.css">
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
        <a href="user_ranking.php" class="nav-item active"><span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span></a>
        <a href="user_heatmap.php" class="nav-item"><span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span></a>
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
            <h1 class="page-title">🏆 Ranking General</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion_user.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <!-- STATS -->
    <div class="rank-stats">
        <div class="rank-stat"><span class="rank-stat-num"><?= $totalEvaluados ?></span><span class="rank-stat-label">Evaluados</span></div>
        <div class="rank-stat-div"></div>
        <div class="rank-stat"><span class="rank-stat-num"><?= $promedioGeneral ?><span class="rank-stat-den">/10</span></span><span class="rank-stat-label">Promedio general</span></div>
        <div class="rank-stat-div"></div>
        <div class="rank-stat"><span class="rank-stat-num"><?= $totalColab - $totalEvaluados ?></span><span class="rank-stat-label">Sin evaluar</span></div>
        <div class="rank-stat-div"></div>
        <div class="rank-stat">
            <?php $top = $ranking[0] ?? null; if ($top): ?>
            <span class="rank-stat-num rank-top-nombre"><?= htmlspecialchars(explode(' ',$top['colab_nombre'])[0]) ?></span>
            <span class="rank-stat-label">Líder actual 🥇</span>
            <?php else: ?><span class="rank-stat-num">—</span><span class="rank-stat-label">Líder actual</span><?php endif; ?>
        </div>
    </div>

    <!-- PODIO -->
    <?php if ($totalEvaluados >= 1):
        $podio = array_slice($ranking,0,3);
        $orden = [];
        if (isset($podio[1])) $orden[]=['data'=>$podio[1],'pos'=>2];
        if (isset($podio[0])) $orden[]=['data'=>$podio[0],'pos'=>1];
        if (isset($podio[2])) $orden[]=['data'=>$podio[2],'pos'=>3];
    ?>
    <div class="rank-podio">
        <?php foreach ($orden as $item):
            $p=$item['pos']; $c=$item['data'];
            $ini=mb_strtoupper(mb_substr($c['colab_nombre'],0,1));
        ?>
        <div class="podio-item podio-<?= $p ?>">
            <div class="podio-medalla"><?= medal($p) ?></div>
            <div class="podio-avatar"><?= $ini ?></div>
            <div class="podio-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></div>
            <div class="podio-area"><?= htmlspecialchars($c['area_nombre']) ?></div>
            <div class="podio-score"><?= number_format($c['promedio'],1) ?></div>
            <div class="podio-stars"><?= est($c['promedio']) ?></div>
            <div class="podio-base podio-base-<?= $p ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- TOOLBAR -->
    <div class="rank-toolbar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscarRanking" class="search-global" placeholder="Buscar colaborador o área..." autocomplete="off">
            <button class="search-clear" id="rankClear">✕</button>
        </div>
        <div class="rank-filtros">
            <select id="filtroArea" class="rank-select">
                <option value="">Todas las áreas</option>
                <?php $ar=$conexion->query("SELECT Id_Area,Nombre FROM areas ORDER BY Nombre"); while($a=$ar->fetch_assoc()): ?>
                <option value="<?= $a['Id_Area'] ?>"><?= htmlspecialchars($a['Nombre']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- TABLA -->
    <div class="rank-content">
        <?php if (empty($ranking)): ?>
        <div class="rank-empty">
            <span class="rank-empty-icon">🏆</span>
            <p class="rank-empty-title">Sin evaluaciones aún</p>
            <p class="rank-empty-sub">El ranking se genera automáticamente con las evaluaciones.</p>
        </div>
        <?php else: ?>
        <div class="rank-tabla">
            <div class="rank-tabla-header">
                <span class="col-rank-pos">#</span>
                <span class="col-rank-colab">Colaborador</span>
                <span class="col-rank-area">Área</span>
                <span class="col-rank-nivel">Nivel</span>
                <span class="col-rank-evals">Evals</span>
                <span class="col-rank-score">Promedio</span>
            </div>
            <?php foreach ($ranking as $pos => $c):
                $num=$pos+1; $ini=mb_strtoupper(mb_substr($c['colab_nombre'],0,1));
                $icon=$iconosAreas[$c['Id_Area']]??'🏢';
                $topCls=$num<=3?"rank-fila-top rank-top-$num":'';
            ?>
            <div class="rank-fila <?= $topCls ?>"
                 data-nombre="<?= strtolower(htmlspecialchars($c['colab_nombre'])) ?>"
                 data-area="<?= $c['Id_Area'] ?>"
                 data-nivel="<?= htmlspecialchars($c['nivel']??'') ?>">
                <span class="col-rank-pos"><?= medal($num) ?: "<span class='rank-num'>$num</span>" ?></span>
                <span class="col-rank-colab">
                    <div class="rank-avatar rank-avatar-<?= $num<=3?$num:'0' ?>"><?= $ini ?></div>
                    <div class="rank-colab-info">
                        <span class="rank-colab-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></span>
                        <?php if($c['Cargo']): ?><span class="rank-colab-cargo"><?= htmlspecialchars($c['Cargo']) ?></span><?php endif; ?>
                        <div class="rank-stars"><?= est($c['promedio']) ?></div>
                    </div>
                </span>
                <span class="col-rank-area"><span class="rank-area-icon"><?= $icon ?></span><?= htmlspecialchars($c['area_nombre']) ?></span>
                <span class="col-rank-nivel"><?= nivelBadge($c['nivel']) ?></span>
                <span class="col-rank-evals"><span class="rank-evals-badge"><?= $c['total_evals'] ?></span></span>
                <span class="col-rank-score">
                    <span class="rank-score-num <?= $num===1?'score-gold':($num===2?'score-silver':($num===3?'score-bronze':'')) ?>"><?= number_format($c['promedio'],1) ?></span>
                    <span class="rank-score-max">/10</span>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="rank-no-results" id="rankNoResults" style="display:none">
            <span>🔍</span><p>Sin resultados.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscar    = document.getElementById('buscarRanking');
    const clearBtn  = document.getElementById('rankClear');
    const filtroArea= document.getElementById('filtroArea');
    const noRes     = document.getElementById('rankNoResults');
    function filtrar() {
        const q    = (buscar?.value||'').toLowerCase().trim();
        const area = filtroArea?.value||'';
        let vis    = 0;
        document.querySelectorAll('.rank-fila').forEach(f => {
            const ok = (!q||f.dataset.nombre.includes(q)) && (!area||f.dataset.area===area);
            f.style.display = ok?'':'none';
            if(ok) vis++;
        });
        if(clearBtn) clearBtn.classList.toggle('visible', q.length>0);
        if(noRes) noRes.style.display = vis===0?'block':'none';
    }
    buscar?.addEventListener('input', filtrar);
    filtroArea?.addEventListener('change', filtrar);
    clearBtn?.addEventListener('click', () => { buscar.value=''; filtrar(); });
});
</script>
</body>
</html>