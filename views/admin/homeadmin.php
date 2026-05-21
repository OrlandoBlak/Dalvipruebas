<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

// ── UNA SOLA QUERY: stats globales + áreas en un solo viaje a BD ──────────
// Todo lo que necesita el HTML inicial lo obtenemos aquí de una vez.
$stats = $conexion->query("
    SELECT
        (SELECT COUNT(*) FROM colaboradores)               AS total_colab,
        (SELECT COUNT(*) FROM areas)                       AS total_areas,
        COALESCE(ROUND(AVG(e.Evaluacion),1), 0)            AS promedio,
        SUM(CASE WHEN e.Evaluacion >= 7 THEN 1 ELSE 0 END) AS en_meta
    FROM evaluaciones e
")->fetch_assoc();

$totalColab = (int)$stats['total_colab'];
$totalAreas = (int)$stats['total_areas'];
$promedio   = (float)$stats['promedio'];
$enMeta     = (int)$stats['en_meta'];

// ── Detectar FK área ──────────────────────────────────────────────────────
$fkCol = 'FK_Id_Area';
$colsRes = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($col = $colsRes->fetch_assoc()) {
    if (stripos($col['Field'], 'area') !== false) { $fkCol = $col['Field']; break; }
}

// ── Áreas con conteo de colaboradores ─────────────────────────────────────
$areas = $conexion->query("
    SELECT a.Id_Area, a.Nombre,
           COUNT(c.Id_Colaborador) AS total_colab
    FROM areas a
    LEFT JOIN colaboradores c ON c.`$fkCol` = a.Id_Area
    GROUP BY a.Id_Area, a.Nombre
    ORDER BY a.Id_Area ASC
");

// ── Top 5 colaboradores con más evaluaciones ───────────────────────────────
$topColab = $conexion->query("
    SELECT c.Id_Colaborador, c.Nombre AS colab_nombre,
           a.Nombre AS area_nombre,
           ROUND(AVG(e.Evaluacion), 1) AS promedio,
           COUNT(e.Id_Evaluacion) AS total_evals
    FROM colaboradores c
    LEFT JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    LEFT JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY c.Id_Colaborador, c.Nombre, a.Nombre
    ORDER BY promedio DESC, total_evals DESC
    LIMIT 5
");

// ── Colaboradores que requieren atención (nivel más bajo) ──────────────────
$notifRes = $conexion->query("
    SELECT c.Id_Colaborador, c.Nombre AS colab_nombre,
           a.Nombre AS area_nombre,
           ROUND(AVG(e.Evaluacion),1) AS promedio
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY c.Id_Colaborador, c.Nombre, a.Nombre
    HAVING promedio < 6
    ORDER BY promedio ASC
    LIMIT 10
");
$notificaciones = [];
while ($n = $notifRes->fetch_assoc()) $notificaciones[] = $n;
$totalNotif = count($notificaciones);

$iconosAreas = [
    1=>'🛒', 2=>'👥', 3=>'📢', 4=>'🚚', 5=>'🏛️',
    6=>'💻', 7=>'🛍️', 8=>'📦', 9=>'🎨', 10=>'🧹',
    11=>'👔', 12=>'🏪', 13=>'🏗️',
];

// Áreas para select del modal (PHP directo, sin fetch)
$areasSelect = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
$areasArr = [];
while ($a = $areasSelect->fetch_assoc()) $areasArr[] = $a;

function generarEstrellas($valor, $max = 5) {
    $llenas = (int) round(($valor / 10) * $max);
    $html = '';
    for ($i = 1; $i <= $max; $i++) {
        $html .= $i <= $llenas ? '<span class="star filled">★</span>' : '<span class="star">★</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin – Grupo Dalvi</title>
    <!-- CSS crítico primero -->
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <!-- Fuentes: display=swap para no bloquear render -->
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
        <a href="homeadmin.php" class="nav-item active">
            <span class="nav-icon">⊞</span>
            <span class="nav-text">Resumen General</span>
            <?php if ($totalColab > 0): ?>
                <span class="nav-badge"><?= $totalColab ?></span>
            <?php endif; ?>
        </a>
        <a href="departamentos.php" class="nav-item">
            <span class="nav-icon">🏢</span>
            <span class="nav-text">Departamentos</span>
            <span class="nav-badge"><?= $totalAreas ?></span>
        </a>
        <a href="graficas.php" class="nav-item">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Gráficas</span>
        </a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="ranking.php" class="nav-item">
            <span class="nav-icon">🏆</span>
            <span class="nav-text">Ranking General</span>
        </a>
        <a href="heatmap.php" class="nav-item">
            <span class="nav-icon">🗺️</span>
            <span class="nav-text">Heatmap</span>
        </a>
        <a href="dashboard.php" class="nav-item">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Dashboard Ejecutivo</span>
        </a>
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

<!-- MAIN -->
<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">Resumen General</h1>
        </div>
        <div class="topbar-right">
            <!-- CAMPANA CON DROPDOWN -->
            <div class="notif-wrap" id="notifWrap">
                <button class="btn-notif" id="btnNotif" title="Notificaciones">
                    🔔
                    <?php if ($totalNotif > 0): ?>
                    <span class="notif-badge"><?= $totalNotif ?></span>
                    <?php endif; ?>
                </button>
                <!-- DROPDOWN -->
                <div class="notif-dropdown" id="notifDropdown" style="display:none">
                    <div class="notif-dropdown-header">
                        <span class="notif-dropdown-title">⚠️ Requieren atención</span>
                        <span class="notif-dropdown-count"><?= $totalNotif ?></span>
                    </div>
                    <?php if (empty($notificaciones)): ?>
                    <div class="notif-empty">
                        <span>✅</span>
                        <p>Todos en buen desempeño</p>
                    </div>
                    <?php else: ?>
                    <div class="notif-lista">
                        <?php foreach ($notificaciones as $n):
                            $ini = mb_strtoupper(mb_substr($n['colab_nombre'], 0, 1));
                            $color = $n['promedio'] < 4 ? '#ef4444' : '#f59e0b';
                        ?>
                        <div class="notif-item">
                            <div class="notif-avatar" style="background:<?= $color ?>20;border:1.5px solid <?= $color ?>40;color:<?= $color ?>">
                                <?= $ini ?>
                            </div>
                            <div class="notif-info">
                                <span class="notif-nombre"><?= htmlspecialchars($n['colab_nombre']) ?></span>
                                <span class="notif-area"><?= htmlspecialchars($n['area_nombre']) ?></span>
                            </div>
                            <span class="notif-score" style="color:<?= $color ?>"><?= $n['promedio'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="ranking.php" class="notif-ver-todos">Ver ranking completo →</a>
                    <?php endif; ?>
                </div>
            </div>
            <button class="btn-secondary" id="btnAgregarColab">➕ Agregar Colaborador</button>
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
            <a href="graficas.php" class="btn-primary">📄 PDF Ejecutivo</a>
        </div>
    </header>

    <!-- STATS: datos ya disponibles desde PHP, se pintan de inmediato -->
    <section class="stats-grid">
        <div class="stat-card stat-promedio">
            <div class="stat-icon">🏆</div>
            <div class="stat-body">
                <div class="stat-value"><?= $promedio ?><span class="stat-denom">/10</span></div>
                <div class="stat-label">PROMEDIO GENERAL</div>
                <div class="stat-stars"><?= generarEstrellas($promedio) ?></div>
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
            <input type="text" id="buscarDepto" class="depto-search-input" placeholder="🔍 Buscar...">
        </div>

        <div class="deptos-grid" id="deptosGrid">
            <?php while ($a = $areas->fetch_assoc()):
                $icon  = $iconosAreas[$a['Id_Area']] ?? '🏢';
                $count = (int)$a['total_colab'];
            ?>
            <div class="depto-card"
                 data-nombre="<?= strtolower(htmlspecialchars($a['Nombre'])) ?>"
                 onclick="window.location.href='departamentos.php'">
                <div class="depto-card-top">
                    <span class="depto-card-icon"><?= $icon ?></span>
                    <span class="depto-card-badge <?= $count > 0 ? '' : 'sin' ?>">
                        <?= $count ?>
                    </span>
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
            <a href="ranking.php" class="section-ver-mas">Ver ranking completo →</a>
        </div>
        <div class="top-colab-list">
            <?php
            $medallas = ['🥇','🥈','🥉'];
            $pos = 0;
            if ($topColab && $topColab->num_rows > 0):
                while ($c = $topColab->fetch_assoc()):
                    $medal = $medallas[$pos] ?? ($pos + 1);
                    $prom  = $c['promedio'];
                    $ini   = mb_strtoupper(mb_substr($c['colab_nombre'], 0, 1));
                    $topCls = $pos === 0 ? ' colab-top1' : '';
            ?>
            <div class="colab-row<?= $topCls ?>">
                <div class="colab-rank"><?= $medal ?></div>
                <div class="colab-avatar">
                    <div class="avatar-circle"><?= $ini ?></div>
                </div>
                <div class="colab-data">
                    <div class="colab-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></div>
                    <div class="colab-area"><?= htmlspecialchars($c['area_nombre'] ?? '—') ?></div>
                    <div class="colab-stars"><?= generarEstrellas($prom ?? 0) ?></div>
                </div>
                <div class="colab-score">
                    <?= $prom ? $prom : '<span class="sin-score">—</span>' ?>
                </div>
            </div>
            <?php $pos++; endwhile;
            else: ?>
            <p class="no-data">Sin evaluaciones registradas aún.
                <a href="evaluacion.php" style="color:var(--accent);font-weight:600">Iniciar primera evaluación →</a>
            </p>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- ══ MODAL AGREGAR COLABORADOR ══ -->
<div class="modal-backdrop" id="bdAgregarHome" style="display:none"></div>
<div class="modal-container" id="mAgregarHome" style="display:none" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon">👤</div>
        <div><h3 class="modal-title">Agregar Colaborador</h3><p class="modal-subtitle">Nuevo registro</p></div>
        <button class="modal-close" id="mHomeClose">✕</button>
    </div>
    <form id="fAgregarHome" novalidate style="padding:22px">
        <div class="modal-field">
            <label class="modal-label">Nombre completo <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">✏️</span>
                <input type="text" id="hNombre" class="modal-input" placeholder="Ej. Juan Pérez García" maxlength="120" autocomplete="off">
            </div>
            <span class="field-error" id="hErrNombre"></span>
        </div>
        <div class="modal-field">
            <label class="modal-label">Departamento / Área <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">🏢</span>
                <select id="hArea" class="modal-select">
                    <option value="">— Selecciona un área —</option>
                    <?php foreach ($areasArr as $a): ?>
                    <option value="<?= $a['Id_Area'] ?>"><?= htmlspecialchars($a['Nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
            </div>
            <span class="field-error" id="hErrArea"></span>
        </div>
        <div class="modal-alert" id="hAlert" style="display:none"></div>
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="mHomeCancel">Cancelar</button>
            <button type="submit" class="btn-modal-save" id="hBtnGuardar">
                <span class="btn-text">Guardar colaborador</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </form>
    <div id="hSuccess" style="display:none;padding:32px;text-align:center">
        <div style="font-size:48px;margin-bottom:12px">✅</div>
        <p style="font-size:17px;font-weight:700;margin-bottom:6px">¡Colaborador agregado!</p>
        <p id="hSuccessMsg" style="font-size:13px;color:#64748b;margin-bottom:20px"></p>
        <div class="modal-actions" style="justify-content:center;gap:10px">
            <button class="btn-modal-cancel" id="hOtro">➕ Agregar otro</button>
            <button class="btn-modal-save"   id="hListo">Listo</button>
        </div>
    </div>
</div>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const API = '../../php/api_colaboradores.php';

    function abrirHome() {
        document.getElementById('bdAgregarHome').style.display = 'block';
        document.getElementById('bdAgregarHome').classList.add('active');
        document.getElementById('mAgregarHome').style.display  = 'block';
        document.getElementById('mAgregarHome').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('hNombre')?.focus(), 200);
    }
    function cerrarHome() {
        document.getElementById('bdAgregarHome').style.display = 'none';
        document.getElementById('bdAgregarHome').classList.remove('active');
        document.getElementById('mAgregarHome').style.display  = 'none';
        document.getElementById('mAgregarHome').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('btnAgregarColab')?.addEventListener('click', () => {
        document.getElementById('fAgregarHome').reset();
        document.getElementById('fAgregarHome').style.display = 'block';
        document.getElementById('hSuccess').style.display     = 'none';
        document.getElementById('hAlert').style.display       = 'none';
        document.getElementById('hNombre').classList.remove('is-error');
        document.getElementById('hArea').classList.remove('is-error');
        document.getElementById('hErrNombre').textContent = '';
        document.getElementById('hErrArea').textContent   = '';
        abrirHome();
    });

    document.getElementById('mHomeClose')?.addEventListener('click',  cerrarHome);
    document.getElementById('mHomeCancel')?.addEventListener('click', cerrarHome);
    document.getElementById('bdAgregarHome')?.addEventListener('click', cerrarHome);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarHome(); });

    document.getElementById('fAgregarHome')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const nombre  = document.getElementById('hNombre').value.trim();
        const id_area = document.getElementById('hArea').value;
        document.getElementById('hAlert').style.display = 'none';
        let ok = true;

        if (!nombre || nombre.length < 2) {
            document.getElementById('hNombre').classList.add('is-error');
            document.getElementById('hErrNombre').textContent = 'Mínimo 2 caracteres.'; ok = false;
        } else { document.getElementById('hNombre').classList.remove('is-error'); document.getElementById('hErrNombre').textContent = ''; }

        if (!id_area) {
            document.getElementById('hArea').classList.add('is-error');
            document.getElementById('hErrArea').textContent = 'Selecciona un área.'; ok = false;
        } else { document.getElementById('hArea').classList.remove('is-error'); document.getElementById('hErrArea').textContent = ''; }

        if (!ok) return;

        const btn = document.getElementById('hBtnGuardar');
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display   = 'none';
        btn.querySelector('.btn-spinner').style.display = 'inline';

        const fd = new FormData();
        fd.append('action','crear'); fd.append('nombre',nombre); fd.append('id_area',id_area);

        try {
            const res  = await fetch(API, {method:'POST', body:fd, credentials:'include'});
            const data = await res.json();
            if (data.success) {
                document.getElementById('fAgregarHome').style.display = 'none';
                document.getElementById('hSuccess').style.display     = 'block';
                document.getElementById('hSuccessMsg').textContent    = '"' + nombre + '" fue agregado correctamente.';
            } else {
                document.getElementById('hAlert').textContent   = '⚠️ ' + (data.error||'Error al guardar');
                document.getElementById('hAlert').style.display = 'block';
            }
        } catch {
            document.getElementById('hAlert').textContent   = '⚠️ Error de conexión';
            document.getElementById('hAlert').style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display    = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';
        }
    });

    document.getElementById('hOtro')?.addEventListener('click', () => {
        document.getElementById('fAgregarHome').reset();
        document.getElementById('fAgregarHome').style.display = 'block';
        document.getElementById('hSuccess').style.display     = 'none';
    });
    document.getElementById('hListo')?.addEventListener('click', () => {
        cerrarHome();
        setTimeout(() => location.reload(), 200);
    });

});
</script>
</body>
</html>