<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso"); exit();
}
require_once "../../config/conexion.php";

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: dashboard.php"); exit(); }

$fkCol = 'FK_Id_Area';
$cols  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($c = $cols->fetch_assoc()) {
    if (stripos($c['Field'], 'area') !== false) { $fkCol = $c['Field']; break; }
}

$stmt = $conexion->prepare("
    SELECT e.*, ROUND(e.Evaluacion,1) AS promedio,
           c.Nombre AS colab_nombre,
           a.Nombre AS area_nombre,
           ins.Descripcion AS insignia_nombre,
           o.Observacion, o.Puntos, o.Pendientes, o.Comentarios
    FROM evaluaciones e
    INNER JOIN colaboradores c ON c.Id_Colaborador  = e.Id_Colaborador
    LEFT  JOIN areas a         ON a.Id_Area          = c.`$fkCol`
    LEFT  JOIN insignias ins   ON ins.Id_Insignia    = e.Id_Insignia
    LEFT  JOIN observaciones o ON o.Id_Observacion  = e.Id_Observacion
    WHERE e.Id_Evaluacion = ? LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$eval = $stmt->get_result()->fetch_assoc();
if (!$eval) { header("Location: dashboard.php"); exit(); }

$criteriosRes = $conexion->query("SELECT Nombre_Criterio, Evaluando FROM puntos ORDER BY Nombre_Criterio ASC");
$criterios    = [];
while ($c = $criteriosRes->fetch_assoc()) $criterios[] = $c;

$criteriosSel = array_filter(array_map('trim', explode(',', $eval['Criterios'] ?? '')));

// Cargar valores individuales guardados en criterios_resultados
$criteriosVals = [];
$stmtCR = $conexion->prepare("SELECT Datos_Guardado FROM criterios_resultados WHERE Id_Evaluacion = ?");
if ($stmtCR) {
    $stmtCR->bind_param("i", $id);
    $stmtCR->execute();
    $resCR = $stmtCR->get_result();
    while ($row = $resCR->fetch_assoc()) {
        $d = json_decode($row['Datos_Guardado'], true);
        if ($d && isset($d['criterio'])) {
            $criteriosVals[$d['criterio']] = [
                'actual' => $d['actual'] ?? 0,
                'maximo' => $d['maximo'] ?? 10,
                'pct'    => $d['pct']    ?? 0,
            ];
        }
    }
}

$insigniasRes = $conexion->query("SELECT Id_Insignia, Descripcion FROM insignias ORDER BY Id_Insignia ASC");
$insignias    = [];
while ($ins = $insigniasRes->fetch_assoc()) $insignias[] = $ins;

$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];

$pct = ($eval['promedio'] / 10) * 100;
if ($pct >= 90)     { $nivelTxt='Excepcional';      $nivelBg='#d1fae5'; $nivelColor='#065f46'; }
elseif ($pct >= 75) { $nivelTxt='En camino';        $nivelBg='#dbeafe'; $nivelColor='#1d4ed8'; }
elseif ($pct >= 60) { $nivelTxt='En desarrollo';    $nivelBg='#fef3c7'; $nivelColor='#92400e'; }
else                { $nivelTxt='Requiere atención';$nivelBg='#fee2e2'; $nivelColor='#b91c1c'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Evaluación #<?= $id ?> – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/evaluacion.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../../assets/logo.jpeg" alt="Logo" class="logo-img" onerror="this.style.display='none'">
            <div class="logo-text"><span class="logo-group">GRUPO</span><span class="logo-name">GRUPO DALVI</span><span class="logo-sub">EVALUACIÓN</span></div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">✕</button>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">PANEL</div>
        <a href="homeadmin.php" class="nav-item"><span class="nav-icon">⊞</span><span class="nav-text">Resumen General</span></a>
        <a href="departamentos.php" class="nav-item"><span class="nav-icon">🏢</span><span class="nav-text">Departamentos</span></a>
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
            <h1 class="page-title">✏️ Editar Evaluación #<?= $id ?></h1>
        </div>
        <div class="topbar-right">
            <a href="dashboard.php" class="btn-secondary">← Volver</a>
        </div>
    </header>

    <div style="max-width:900px;margin:0 auto;padding:0 28px 48px">

        <!-- COLABORADOR INFO -->
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-sm)">
            <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;font-size:20px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?= mb_strtoupper(mb_substr($eval['colab_nombre'],0,1)) ?>
            </div>
            <div style="flex:1">
                <div style="font-size:17px;font-weight:700"><?= htmlspecialchars($eval['colab_nombre']) ?></div>
                <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($eval['area_nombre']) ?></div>
            </div>
            <div style="text-align:right">
                <div style="font-size:28px;font-weight:800;color:#2563eb;font-family:'Space Mono',monospace"><?= $eval['promedio'] ?><span style="font-size:13px;color:var(--text-muted)">/10</span></div>
                <div style="font-size:11px;font-weight:700;color:<?= $nivelColor ?>"><?= $nivelTxt ?></div>
            </div>
        </div>

        <form id="formEditar">
            <input type="hidden" id="fId"    value="<?= $id ?>">
            <input type="hidden" id="fIdObs" value="<?= $eval['Id_Observacion'] ?? 0 ?>">

            <!-- PROMEDIO -->
            <div class="eval-card" style="margin-bottom:16px">
                <div class="eval-card-title">📊 Promedio de Evaluación</div>
                <div style="display:flex;align-items:center;gap:16px;padding:10px 0">
                    <input type="number" id="fEvaluacion" value="<?= $eval['promedio'] ?>" min="0" max="10" step="0.1"
                           style="width:120px;padding:10px;border:1.5px solid var(--border);border-radius:10px;font-size:18px;font-weight:700;font-family:'Space Mono',monospace;text-align:center;outline:none">
                    <span style="font-size:13px;color:var(--text-muted)">/10</span>
                    <span id="nivelInd" style="font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;background:<?= $nivelBg ?>;color:<?= $nivelColor ?>"><?= $nivelTxt ?></span>
                </div>
            </div>

            <!-- CRITERIOS CON SLIDERS -->
            <div class="eval-card" style="margin-bottom:16px">
                <div class="eval-card-title">📝 Criterios Evaluados y Calificaciones</div>
                <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px">Activa el criterio con el checkbox y ajusta la calificación con el slider.</p>
                <div id="criteriosEditList">
                <?php foreach ($criterios as $crit):
                    $nom     = $crit['Nombre_Criterio'];
                    $maxVal  = (float)$crit['Evaluando'];
                    $isStars = $maxVal <= 5;
                    $checked = in_array($nom, $criteriosSel);
                    $valData = $criteriosVals[$nom] ?? null;
                    $valAct  = $valData ? (float)$valData['actual'] : ($checked ? round(($eval['promedio']/10)*$maxVal,1) : 0);
                    $pctAct  = $maxVal > 0 ? round(($valAct/$maxVal)*100) : 0;
                    $step    = $maxVal <= 5 ? 0.5 : 0.5;
                ?>
                <div class="crit-edit-row" style="background:<?= $checked?'#f0f6ff':'#f8fafc' ?>;border:1.5px solid <?= $checked?'#bfdbfe':'var(--border)' ?>;border-radius:10px;padding:12px 14px;margin-bottom:8px;transition:all .2s" id="critRow-<?= md5($nom) ?>">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:<?= $checked?'10px':'0' ?>">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1">
                            <input type="checkbox" class="crit-chk"
                                   id="chk-<?= md5($nom) ?>"
                                   value="<?= htmlspecialchars($nom) ?>"
                                   data-max="<?= $maxVal ?>"
                                   data-nom="<?= md5($nom) ?>"
                                   <?= $checked?'checked':'' ?>
                                   style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
                            <span style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($nom) ?></span>
                            <span style="font-size:10px;color:var(--text-muted);margin-left:auto">/<?= $maxVal ?><?= $isStars?' ⭐':'' ?></span>
                        </label>
                        <span class="crit-pct-badge" id="pct-<?= md5($nom) ?>" style="font-size:11px;font-weight:700;font-family:'Space Mono',monospace;color:<?= $pctAct>=90?'#065f46':($pctAct>=60?'#92400e':'#b91c1c') ?>;background:<?= $pctAct>=90?'#d1fae5':($pctAct>=60?'#fef3c7':'#fee2e2') ?>;padding:2px 8px;border-radius:20px">
                            <?= $pctAct ?>%
                        </span>
                    </div>
                    <div class="crit-slider-section" id="slid-<?= md5($nom) ?>" style="display:<?= $checked?'block':'none' ?>">
                        <div style="display:flex;align-items:center;gap:10px">
                            <input type="range" class="eval-slider crit-slider"
                                   id="slider-<?= md5($nom) ?>"
                                   min="0" max="<?= $maxVal ?>" step="<?= $step ?>"
                                   value="<?= $valAct ?>"
                                   data-nom="<?= md5($nom) ?>"
                                   data-max="<?= $maxVal ?>">
                            <span class="crit-val-badge" id="val-<?= md5($nom) ?>"
                                  style="font-size:14px;font-weight:700;font-family:'Space Mono',monospace;color:#2563eb;min-width:52px;text-align:center">
                                <?= $valAct ?>/<?= $maxVal ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            <!-- INSIGNIA -->
            <?php if (!empty($insignias)): ?>
            <div class="eval-card" style="margin-bottom:16px">
                <div class="eval-card-title">🏅 Insignia Asignada</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px 0">
                    <label class="ins-lbl" style="display:flex;align-items:center;gap:6px;background:<?= !$eval['Id_Insignia']?'#f1f5f9':'var(--bg-main)' ?>;border:1.5px solid <?= !$eval['Id_Insignia']?'#94a3b8':'var(--border)' ?>;border-radius:20px;padding:6px 14px;cursor:pointer;font-size:12px">
                        <input type="radio" name="ins_r" value="" style="display:none" <?= !$eval['Id_Insignia']?'checked':'' ?>>
                        Sin insignia
                    </label>
                    <?php foreach ($insignias as $ins):
                        $sel = ($eval['Id_Insignia'] == $ins['Id_Insignia']);
                    ?>
                    <label class="ins-lbl" style="display:flex;align-items:center;gap:6px;background:<?= $sel?'#fef3c7':'var(--bg-main)' ?>;border:1.5px solid <?= $sel?'#f59e0b':'var(--border)' ?>;border-radius:20px;padding:6px 14px;cursor:pointer;font-size:12px;font-weight:600">
                        <input type="radio" name="ins_r" value="<?= $ins['Id_Insignia'] ?>" style="display:none" <?= $sel?'checked':'' ?>>
                        🏅 <?= htmlspecialchars($ins['Descripcion']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- OBSERVACIONES -->
            <?php $textareas = [
                ['id'=>'fObs',  'title'=>'💬 Observaciones y Recomendaciones', 'val'=>$eval['Observacion']??'',  'ph'=>'Logros, fortalezas, reconocimientos...'],
                ['id'=>'fPunt', 'title'=>'📌 Puntos de Mejora',                'val'=>$eval['Puntos']??'',       'ph'=>'Áreas de oportunidad...'],
                ['id'=>'fPend', 'title'=>'📋 Pendientes por Entregar',          'val'=>$eval['Pendientes']??'',  'ph'=>'Documentos, tareas pendientes...'],
                ['id'=>'fComt', 'title'=>'🗒️ Comentarios Adicionales',          'val'=>$eval['Comentarios']??'', 'ph'=>'Comentarios del evaluador...'],
            ];
            foreach ($textareas as $ta): ?>
            <div class="eval-card" style="margin-bottom:16px">
                <div class="eval-card-title"><?= $ta['title'] ?></div>
                <textarea id="<?= $ta['id'] ?>" rows="3" placeholder="<?= $ta['ph'] ?>"
                    style="width:100%;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:13px;resize:vertical;outline:none;margin-top:8px"><?= htmlspecialchars($ta['val']) ?></textarea>
            </div>
            <?php endforeach; ?>

            <!-- ALERTAS -->
            <div id="aOk"  style="display:none;background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:14px 18px;border-radius:10px;margin-bottom:16px;font-weight:600">✅ Evaluación actualizada correctamente.</div>
            <div id="aErr" style="display:none;background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;padding:14px 18px;border-radius:10px;margin-bottom:16px;font-weight:600">⚠️ <span id="aErrMsg"></span></div>

            <!-- BOTONES -->
            <div style="display:flex;gap:12px;justify-content:flex-end">
                <a href="dashboard.php" class="btn-secondary" style="padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;display:inline-flex;align-items:center">Cancelar</a>
                <button type="submit" id="btnSave" style="padding:12px 28px;border-radius:10px;border:none;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 4px 12px rgba(37,99,235,.3)">
                    <span class="btn-text">💾 Guardar cambios</span>
                    <span class="btn-spinner" style="display:none">⏳ Guardando...</span>
                </button>
            </div>
        </form>
    </div>
</main>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const API = '../../php/api_evaluaciones_crud.php';

    // Criterios — checkbox muestra/oculta slider y actualiza promedio
    document.querySelectorAll('.crit-chk').forEach(chk => {
        const nom    = chk.dataset.nom;
        const slidEl = document.getElementById('slid-'   + nom);
        const rowEl  = document.getElementById('critRow-'+ nom);

        chk.addEventListener('change', () => {
            if (slidEl) slidEl.style.display = chk.checked ? 'block' : 'none';
            if (rowEl)  {
                rowEl.style.background  = chk.checked ? '#f0f6ff' : '#f8fafc';
                rowEl.style.borderColor = chk.checked ? '#bfdbfe' : 'var(--border)';
            }
            recalcularPromedio();
        });
    });

    // Sliders criterios
    document.querySelectorAll('.crit-slider').forEach(slider => {
        const nom   = slider.dataset.nom;
        const max   = parseFloat(slider.dataset.max) || 10;
        const valEl = document.getElementById('val-' + nom);
        const pctEl = document.getElementById('pct-' + nom);

        slider.addEventListener('input', () => {
            const v   = parseFloat(slider.value);
            const pct = max > 0 ? Math.round((v/max)*100) : 0;
            if (valEl) valEl.textContent = v + '/' + max;
            if (pctEl) {
                pctEl.textContent = pct + '%';
                pctEl.style.color      = pct>=90?'#065f46':pct>=60?'#92400e':'#b91c1c';
                pctEl.style.background = pct>=90?'#d1fae5':pct>=60?'#fef3c7':'#fee2e2';
            }
            recalcularPromedio();
        });
    });

    function recalcularPromedio() {
        const sliders  = Array.from(document.querySelectorAll('.crit-chk:checked'));
        if (!sliders.length) return;
        let suma = 0;
        sliders.forEach(chk => {
            const nom    = chk.dataset.nom;
            const max    = parseFloat(chk.dataset.max) || 10;
            const slider = document.getElementById('slider-' + nom);
            const val    = slider ? parseFloat(slider.value) : 0;
            suma += max > 0 ? (val/max)*10 : 0;
        });
        const prom = (suma / sliders.length).toFixed(1);
        const inp  = document.getElementById('fEvaluacion');
        if (inp) { inp.value = prom; inp.dispatchEvent(new Event('input')); }
    }

    // Insignias toggle
    document.querySelectorAll('.ins-lbl').forEach(lbl => {
        lbl.addEventListener('click', () => {
            document.querySelectorAll('.ins-lbl').forEach(l => {
                l.style.background  = 'var(--bg-main)';
                l.style.borderColor = 'var(--border)';
            });
            lbl.style.background  = '#fef3c7';
            lbl.style.borderColor = '#f59e0b';
        });
    });

    // Nivel indicador
    document.getElementById('fEvaluacion')?.addEventListener('input', function() {
        const pct = (parseFloat(this.value)||0) / 10 * 100;
        const el  = document.getElementById('nivelInd');
        if (!el) return;
        if (pct>=90)      { el.textContent='Excepcional';      el.style.background='#d1fae5'; el.style.color='#065f46'; }
        else if (pct>=75) { el.textContent='En camino';        el.style.background='#dbeafe'; el.style.color='#1d4ed8'; }
        else if (pct>=60) { el.textContent='En desarrollo';    el.style.background='#fef3c7'; el.style.color='#92400e'; }
        else              { el.textContent='Requiere atención';el.style.background='#fee2e2'; el.style.color='#b91c1c'; }
    });

    // Guardar
    document.getElementById('formEditar')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const criterios = Array.from(document.querySelectorAll('.crit-chk:checked')).map(c=>c.value).join(',');
        document.getElementById('aOk').style.display  = 'none';
        document.getElementById('aErr').style.display = 'none';

        if (!criterios) {
            document.getElementById('aErrMsg').textContent = 'Selecciona al menos un criterio.';
            document.getElementById('aErr').style.display  = 'block'; return;
        }

        const btn = document.getElementById('btnSave');
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display    = 'none';
        btn.querySelector('.btn-spinner').style.display = 'inline';

        // Recoger valores individuales de sliders
        const criteriosData = [];
        document.querySelectorAll('.crit-chk:checked').forEach(chk => {
            const nom    = chk.dataset.nom;
            const max    = parseFloat(chk.dataset.max) || 10;
            const slider = document.getElementById('slider-' + nom);
            const val    = slider ? parseFloat(slider.value) : 0;
            const nombre = chk.value;
            criteriosData.push({
                criterio: nombre,
                actual:   val,
                maximo:   max,
                pct:      max > 0 ? Math.round((val/max)*100) : 0
            });
        });

        const fd = new FormData();
        fd.append('action',          'actualizar');
        fd.append('id',              document.getElementById('fId').value);
        fd.append('id_obs',          document.getElementById('fIdObs').value);
        fd.append('evaluacion',      document.getElementById('fEvaluacion').value);
        fd.append('criterios',       criterios);
        fd.append('criterios_data',  JSON.stringify(criteriosData));
        fd.append('observacion',     document.getElementById('fObs').value);
        fd.append('puntos',          document.getElementById('fPunt').value);
        fd.append('pendientes',      document.getElementById('fPend').value);
        fd.append('comentarios',     document.getElementById('fComt').value);

        try {
            const res  = await fetch(API, {method:'POST',body:fd,credentials:'include'});
            const data = await res.json();
            if (data.success) {
                document.getElementById('aOk').style.display = 'block';
                document.getElementById('aOk').scrollIntoView({behavior:'smooth',block:'center'});
                setTimeout(() => window.location.href='dashboard.php', 1500);
            } else {
                document.getElementById('aErrMsg').textContent = data.error||'Error al guardar.';
                document.getElementById('aErr').style.display  = 'block';
            }
        } catch {
            document.getElementById('aErrMsg').textContent = 'Error de conexión.';
            document.getElementById('aErr').style.display  = 'block';
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display    = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';
        }
    });
});
</script>
</body>
</html>