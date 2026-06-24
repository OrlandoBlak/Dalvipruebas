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

// Query principal: colaboradores evaluados por área
$res = $conexion->query("
    SELECT a.Id_Area, a.Nombre AS area_nombre,
           c.Id_Colaborador, c.Nombre AS colab_nombre,
           ROUND(AVG(e.Evaluacion),1) AS promedio,
           COUNT(e.Id_Evaluacion)     AS total_evals,
           (SELECT est.Descripcion
            FROM reportes r2
            INNER JOIN estadisticas est ON est.Id_Estadistica = r2.Id_Estadistica
            WHERE r2.Id_Colaborador = c.Id_Colaborador
            ORDER BY r2.Id_Data DESC LIMIT 1) AS nivel_desc
    FROM colaboradores c
    INNER JOIN areas a        ON a.Id_Area        = c.`$fkCol`
    INNER JOIN evaluaciones e ON e.Id_Colaborador = c.Id_Colaborador
    GROUP BY a.Id_Area, a.Nombre, c.Id_Colaborador, c.Nombre
    ORDER BY a.Nombre ASC, promedio DESC
");

$porArea = []; $totalEvaluados = 0; $sumaPromedios = 0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = $row['Id_Area'];
        if (!isset($porArea[$id])) $porArea[$id] = ['nombre'=>$row['area_nombre'],'colaboradores'=>[]];
        $porArea[$id]['colaboradores'][] = $row;
        $totalEvaluados++;
        $sumaPromedios += (float)$row['promedio'];
    }
}
$promedioGeneral = $totalEvaluados > 0 ? round($sumaPromedios/$totalEvaluados,1) : 0;
$areasConEval    = count($porArea);

$iconosAreas = [1=>'🛒',2=>'👥',3=>'📢',4=>'🚚',5=>'🏛️',6=>'💻',7=>'🛍️',8=>'📦',9=>'🎨',10=>'🧹',11=>'👔',12=>'🏪',13=>'🏗️'];

function estrellas($val,$max=5){$l=(int)round(($val/10)*$max);$s='';for($i=1;$i<=$max;$i++)$s.=$i<=$l?'<span class="star filled">★</span>':'<span class="star">★</span>';return $s;}
function nivelCls($desc){if(!$desc)return 'nivel-sin';$d=strtolower($desc);if(str_contains($d,'excep'))return 'nivel-excepcional';if(str_contains($d,'camino'))return 'nivel-encamino';if(str_contains($d,'desarr'))return 'nivel-endesarrollo';return 'nivel-requiere';}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ejecutivo – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
    /* ── PANEL EVALUACIONES ─────────────────────── */
    .eval-panel {
        width: min(780px, calc(100vw - 32px));
        max-height: 85vh;
        overflow-y: auto;
    }
    .eval-panel-toolbar {
        display: flex; align-items: center; gap: 10px;
        padding: 0 20px 16px; flex-wrap: wrap;
    }
    .eval-panel-search {
        flex: 1; min-width: 180px;
        padding: 8px 14px; border: 1.5px solid var(--border);
        border-radius: 10px; font-size: 13px;
        font-family: 'Sora',sans-serif; outline: none;
        transition: border-color .2s;
    }
    .eval-panel-search:focus { border-color: var(--accent-light); }
    .eval-count-badge {
        font-size: 11px; color: var(--text-muted);
        background: var(--bg-main); border: 1px solid var(--border);
        padding: 4px 12px; border-radius: 20px;
        font-family: 'Space Mono',monospace;
    }
    .btn-eliminar-sel {
        padding: 8px 16px; border-radius: 10px;
        font-size: 12px; font-weight: 600;
        border: none; cursor: pointer;
        font-family: 'Sora',sans-serif;
        background: #fee2e2; color: #b91c1c;
        transition: all .2s; white-space: nowrap;
    }
    .btn-eliminar-sel:hover { background: #fecaca; }
    .btn-eliminar-sel:disabled { opacity: .4; cursor: not-allowed; }

    /* Tabla evaluaciones */
    .eval-tabla { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .eval-tabla thead tr { background: #0f1b2d; position: sticky; top: 0; z-index: 5; }
    .eval-tabla thead th {
        padding: 10px 12px; text-align: left;
        color: rgba(255,255,255,.75); font-size: 10px;
        text-transform: uppercase; letter-spacing: 1px;
        font-family: 'Space Mono',monospace; font-weight: 600;
    }
    .eval-tabla tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
    .eval-tabla tbody tr:last-child { border-bottom: none; }
    .eval-tabla tbody tr:hover { background: #f8faff; }
    .eval-tabla tbody tr.selected { background: #eff6ff; }
    .eval-tabla td { padding: 10px 12px; vertical-align: middle; }

    .eval-chk { width: 16px; height: 16px; cursor: pointer; accent-color: #2563eb; }
    .eval-nombre { font-weight: 600; color: var(--text-primary); }
    .eval-area   { font-size: 11px; color: var(--text-muted); }
    .eval-prom   { font-weight: 700; font-family: 'Space Mono',monospace; color: var(--accent); }
    .eval-criterios { font-size: 11px; color: var(--text-muted); max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .btn-eval-edit {
        padding: 5px 12px; border-radius: 8px; border: none;
        background: #dbeafe; color: #1d4ed8;
        font-size: 11px; font-weight: 600; cursor: pointer;
        font-family: 'Sora',sans-serif; transition: background .15s;
        margin-right: 4px;
    }
    .btn-eval-edit:hover { background: #bfdbfe; }
    .btn-eval-del {
        padding: 5px 12px; border-radius: 8px; border: none;
        background: #fee2e2; color: #b91c1c;
        font-size: 11px; font-weight: 600; cursor: pointer;
        font-family: 'Sora',sans-serif; transition: background .15s;
    }
    .btn-eval-del:hover { background: #fecaca; }

    .eval-empty { text-align: center; padding: 32px; color: var(--text-muted); font-size: 13px; }
    </style>
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
            <h1 class="page-title">Dashboard Ejecutivo</h1>
        </div>
        <div class="topbar-right">
            <button class="btn-secondary" id="btnVerEvaluaciones">📋 Evaluaciones</button>
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <!-- STATS -->
    <div class="dash-stats">
        <div class="dash-stat-item"><span class="dash-stat-num"><?= $totalEvaluados ?></span><span class="dash-stat-label">Colaboradores evaluados</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat-item"><span class="dash-stat-num"><?= $promedioGeneral ?><span style="font-size:14px;color:var(--text-muted)">/10</span></span><span class="dash-stat-label">Promedio general</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat-item"><span class="dash-stat-num"><?= $areasConEval ?></span><span class="dash-stat-label">Áreas con evaluación</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat-item"><span class="dash-stat-num"><?= $totalColab - $totalEvaluados ?></span><span class="dash-stat-label">Sin evaluar</span></div>
    </div>

    <!-- BUSCADOR -->
    <div class="dash-toolbar">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="buscarEvaluado" class="search-global" placeholder="Buscar colaborador..." autocomplete="off">
            <button class="search-clear" id="searchClear">✕</button>
        </div>
        <span class="dash-hint"><?= $totalEvaluados ?> evaluado<?= $totalEvaluados!==1?'s':'' ?> · <?= $areasConEval ?> departamento<?= $areasConEval!==1?'s':'' ?></span>
    </div>

    <!-- LISTA POR DEPARTAMENTO -->
    <div class="dash-content" id="dashContent">
        <?php if (empty($porArea)): ?>
        <div class="dash-empty">
            <span class="dash-empty-icon">📋</span>
            <p class="dash-empty-title">Sin evaluaciones registradas</p>
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar primera evaluación</a>
        </div>
        <?php else: ?>
        <?php foreach ($porArea as $idArea => $area):
            $icon  = $iconosAreas[$idArea] ?? '🏢';
            $count = count($area['colaboradores']);
            $promA = round(array_sum(array_column($area['colaboradores'],'promedio'))/$count,1);
        ?>
        <div class="dash-area-bloque" data-area="<?= strtolower(htmlspecialchars($area['nombre'])) ?>">
            <div class="dash-area-header" onclick="toggleDashArea(this)">
                <div class="dash-area-left">
                    <span class="dash-area-icon"><?= $icon ?></span>
                    <div>
                        <span class="dash-area-nombre"><?= htmlspecialchars($area['nombre']) ?></span>
                        <span class="dash-area-sub"><?= $count ?> evaluado<?= $count!==1?'s':'' ?> · Prom. <?= $promA ?>/10</span>
                    </div>
                </div>
                <div class="dash-area-right">
                    <div class="dash-area-stars"><?= estrellas($promA) ?></div>
                    <span class="dash-area-chevron">▾</span>
                </div>
            </div>
            <div class="dash-area-body">
                <?php foreach ($area['colaboradores'] as $i => $c):
                    $medal = $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':''));
                    $ini   = mb_strtoupper(mb_substr($c['colab_nombre'],0,1));
                    $nCls  = nivelCls($c['nivel_desc']??'');
                ?>
                <div class="dash-colab-row" data-nombre="<?= strtolower(htmlspecialchars($c['colab_nombre'])) ?>">
                    <div class="dash-colab-pos"><?= $medal ?: ($i+1) ?></div>
                    <div class="dash-colab-avatar"><?= $ini ?></div>
                    <div class="dash-colab-info">
                        <span class="dash-colab-nombre"><?= htmlspecialchars($c['colab_nombre']) ?></span>
                        <div class="dash-colab-stars"><?= estrellas($c['promedio']) ?></div>
                    </div>
                    <div class="dash-colab-right">
                        <?php if ($c['nivel_desc']): ?>
                        <span class="dash-nivel-badge <?= $nCls ?>"><?= htmlspecialchars($c['nivel_desc']) ?></span>
                        <?php endif; ?>
                        <span class="dash-colab-score"><?= $c['promedio'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="dash-no-results" id="dashNoResults" style="display:none">
            <span>🔍</span>
            <p>Sin resultados para <strong id="dashTermino"></strong></p>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- ══ MODAL EVALUACIONES ══ -->
<div class="modal-backdrop" id="bdEvaluaciones" style="display:none"></div>
<div class="modal-container eval-panel" id="mEvaluaciones" style="display:none" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6)">📋</div>
        <div>
            <h3 class="modal-title">Evaluaciones del Sistema</h3>
            <p class="modal-subtitle" id="evalSubtitle">Cargando...</p>
        </div>
        <button class="modal-close" id="mEvalClose">✕</button>
    </div>
    <div class="eval-panel-toolbar">
        <input type="text" id="evalSearch" class="eval-panel-search" placeholder="Buscar colaborador o área...">
        <span class="eval-count-badge" id="evalCountBadge">0 registros</span>
        <button class="btn-eliminar-sel" id="btnEliminarSel" disabled>🗑️ Eliminar seleccionados</button>
    </div>
    <div id="evalTableWrap">
        <table class="eval-tabla" id="evalTabla">
            <thead>
                <tr>
                    <th><input type="checkbox" id="chkTodos" class="eval-chk"></th>
                    <th>#</th>
                    <th>Colaborador</th>
                    <th>Área</th>
                    <th>Promedio</th>
                    <th>Criterios</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="evalTbody">
                <tr><td colspan="7" class="eval-empty">Cargando evaluaciones...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ MODAL CONFIRMAR ELIMINAR ══ -->
<div class="modal-backdrop" id="bdConfElim" style="display:none"></div>
<div class="modal-container modal-sm" id="mConfElim" style="display:none" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#7f1d1d,#ef4444)">🗑️</div>
        <div><h3 class="modal-title">Confirmar eliminación</h3><p class="modal-subtitle">No se puede deshacer</p></div>
        <button class="modal-close" id="mConfElimClose">✕</button>
    </div>
    <div style="padding:22px">
        <p id="confElimMsg" style="font-size:14px;color:#4a5568;line-height:1.6;margin-bottom:20px"></p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" id="btnConfElimCancelar">Cancelar</button>
            <button class="btn-eliminar-confirm" id="btnConfElimOk">
                <span class="btn-text">Sí, eliminar</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </div>
</div>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const API      = '../../php/api_evaluaciones_crud.php';
    let evaluaciones = [];
    let seleccionados = new Set();
    let pendingDelete = null; // ids a eliminar

    // ── ACORDEÓN ──────────────────────────────────────
    const primero = document.querySelector('.dash-area-bloque');
    if (primero) primero.classList.add('open');

    window.toggleDashArea = function(header) {
        header.closest('.dash-area-bloque')?.classList.toggle('open');
    };

    // ── BUSCADOR DASHBOARD ────────────────────────────
    const busInput = document.getElementById('buscarEvaluado');
    const busClear = document.getElementById('searchClear');
    const noRes    = document.getElementById('dashNoResults');
    const termEl   = document.getElementById('dashTermino');

    busInput?.addEventListener('input', () => {
        const q = busInput.value.trim().toLowerCase();
        busClear?.classList.toggle('visible', q.length > 0);
        filtrarDash(q);
    });
    busClear?.addEventListener('click', () => { busInput.value=''; busClear.classList.remove('visible'); filtrarDash(''); });

    function filtrarDash(q) {
        let vis = 0;
        document.querySelectorAll('.dash-area-bloque').forEach(bloque => {
            const filas = bloque.querySelectorAll('.dash-colab-row');
            const areaNombre = bloque.dataset.area || '';
            let v = 0;
            if (!q) { filas.forEach(f => f.style.display=''); bloque.style.display=''; vis+=filas.length; return; }
            filas.forEach(f => { const ok=(f.dataset.nombre||'').includes(q)||areaNombre.includes(q); f.style.display=ok?'':'none'; if(ok)v++; });
            if(v>0||areaNombre.includes(q)){bloque.style.display='';bloque.classList.add('open');vis+=v;}
            else bloque.style.display='none';
        });
        if(noRes) noRes.style.display=(q&&vis===0)?'block':'none';
        if(termEl&&q) termEl.textContent='"'+busInput.value+'"';
    }

    // ── ABRIR MODAL EVALUACIONES ──────────────────────
    function abrirModal(bd, m) {
        document.getElementById(bd).style.display='block';
        document.getElementById(bd).classList.add('active');
        document.getElementById(m).style.display='block';
        document.getElementById(m).classList.add('active');
        document.body.style.overflow='hidden';
    }
    function cerrarModal(bd, m) {
        document.getElementById(bd).style.display='none';
        document.getElementById(bd).classList.remove('active');
        document.getElementById(m).style.display='none';
        document.getElementById(m).classList.remove('active');
        document.body.style.overflow='';
    }

    document.getElementById('btnVerEvaluaciones')?.addEventListener('click', () => {
        abrirModal('bdEvaluaciones','mEvaluaciones');
        cargarEvaluaciones();
    });
    document.getElementById('mEvalClose')?.addEventListener('click', () => cerrarModal('bdEvaluaciones','mEvaluaciones'));
    document.getElementById('bdEvaluaciones')?.addEventListener('click', () => cerrarModal('bdEvaluaciones','mEvaluaciones'));

    // ── CARGAR EVALUACIONES ───────────────────────────
    function cargarEvaluaciones() {
        const tbody = document.getElementById('evalTbody');
        tbody.innerHTML = '<tr><td colspan="7" class="eval-empty">Cargando...</td></tr>';

        fetch(API + '?action=list', {credentials:'include'})
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error);
                evaluaciones = data.data || [];
                seleccionados.clear();
                renderTabla(evaluaciones);
                document.getElementById('evalSubtitle').textContent = evaluaciones.length + ' evaluaciones en el sistema';
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="7" class="eval-empty" style="color:#ef4444">Error: ${err.message}</td></tr>`;
            });
    }

    function renderTabla(lista) {
        const tbody    = document.getElementById('evalTbody');
        const countEl  = document.getElementById('evalCountBadge');
        if (countEl) countEl.textContent = lista.length + ' registros';

        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="eval-empty">Sin evaluaciones.</td></tr>';
            return;
        }

        tbody.innerHTML = lista.map(e => {
            const crits = (e.Criterios||'').split(',').slice(0,2).join(', ');
            const mas   = (e.Criterios||'').split(',').length > 2 ? '...' : '';
            const sel   = seleccionados.has(e.Id_Evaluacion);
            return `
            <tr class="${sel?'selected':''}" data-nombre="${(e.colab_nombre||'').toLowerCase()}" data-area="${(e.area_nombre||'').toLowerCase()}">
                <td><input type="checkbox" class="eval-chk eval-row-chk" data-id="${e.Id_Evaluacion}" ${sel?'checked':''}></td>
                <td style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text-muted)">${e.Id_Evaluacion}</td>
                <td>
                    <div class="eval-nombre">${esc(e.colab_nombre||'—')}</div>
                    <div class="eval-area">${esc(e.area_nombre||'—')}</div>
                </td>
                <td><span class="eval-area">${esc(e.area_nombre||'—')}</span></td>
                <td><span class="eval-prom">${e.promedio}/10</span></td>
                <td><span class="eval-criterios" title="${esc(e.Criterios||'')}">${esc(crits)}${mas}</span></td>
                <td>
                    <button class="btn-eval-edit" onclick="editarEval(${e.Id_Evaluacion})">✏️ Editar</button>
                    <button class="btn-eval-del"  onclick="confirmarEliminar([${e.Id_Evaluacion}], '${esc(e.colab_nombre||'')}')">🗑️</button>
                </td>
            </tr>`;
        }).join('');

        // Eventos checkboxes
        tbody.querySelectorAll('.eval-row-chk').forEach(chk => {
            chk.addEventListener('change', () => {
                const id = parseInt(chk.dataset.id);
                const tr = chk.closest('tr');
                if (chk.checked) { seleccionados.add(id); tr.classList.add('selected'); }
                else             { seleccionados.delete(id); tr.classList.remove('selected'); }
                actualizarBtnEliminar();
            });
        });
    }

    function actualizarBtnEliminar() {
        const btn = document.getElementById('btnEliminarSel');
        if (btn) {
            btn.disabled = seleccionados.size === 0;
            btn.textContent = seleccionados.size > 0
                ? `🗑️ Eliminar seleccionados (${seleccionados.size})`
                : '🗑️ Eliminar seleccionados';
        }
        const chkTodos = document.getElementById('chkTodos');
        if (chkTodos) chkTodos.checked = seleccionados.size === evaluaciones.length && evaluaciones.length > 0;
    }

    // Seleccionar todos
    document.getElementById('chkTodos')?.addEventListener('change', function() {
        const checks = document.querySelectorAll('.eval-row-chk');
        checks.forEach(chk => {
            chk.checked = this.checked;
            const id = parseInt(chk.dataset.id);
            const tr = chk.closest('tr');
            if (this.checked) { seleccionados.add(id); tr.classList.add('selected'); }
            else              { seleccionados.delete(id); tr.classList.remove('selected'); }
        });
        actualizarBtnEliminar();
    });

    // Eliminar seleccionados
    document.getElementById('btnEliminarSel')?.addEventListener('click', () => {
        if (!seleccionados.size) return;
        const ids   = Array.from(seleccionados);
        const names = evaluaciones.filter(e => ids.includes(e.Id_Evaluacion)).map(e => e.colab_nombre).join(', ');
        confirmarEliminar(ids, names);
    });

    // Búsqueda en modal
    document.getElementById('evalSearch')?.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        if (!q) { renderTabla(evaluaciones); return; }
        const filtradas = evaluaciones.filter(e =>
            (e.colab_nombre||'').toLowerCase().includes(q) ||
            (e.area_nombre||'').toLowerCase().includes(q)
        );
        renderTabla(filtradas);
    });

    // ── CONFIRMAR ELIMINAR ────────────────────────────
    window.confirmarEliminar = function(ids, nombres) {
        pendingDelete = ids;
        const msg = document.getElementById('confElimMsg');
        if (msg) msg.innerHTML = ids.length === 1
            ? `¿Eliminar la evaluación de <strong>${nombres}</strong>? No se puede deshacer.`
            : `¿Eliminar <strong>${ids.length} evaluaciones</strong> (${nombres})? No se puede deshacer.`;
        abrirModal('bdConfElim','mConfElim');
    };

    document.getElementById('mConfElimClose')?.addEventListener('click',   () => cerrarModal('bdConfElim','mConfElim'));
    document.getElementById('btnConfElimCancelar')?.addEventListener('click', () => cerrarModal('bdConfElim','mConfElim'));
    document.getElementById('bdConfElim')?.addEventListener('click',         () => cerrarModal('bdConfElim','mConfElim'));

    document.getElementById('btnConfElimOk')?.addEventListener('click', async function() {
        if (!pendingDelete?.length) return;
        const btn = this;
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display   = 'none';
        btn.querySelector('.btn-spinner').style.display = 'inline';

        try {
            await Promise.all(pendingDelete.map(id => {
                const fd = new FormData();
                fd.append('action','eliminar');
                fd.append('id', id);
                return fetch(API, {method:'POST', body:fd, credentials:'include'}).then(r=>r.json());
            }));
            cerrarModal('bdConfElim','mConfElim');
            seleccionados.clear();
            cargarEvaluaciones();
            setTimeout(() => location.reload(), 800);
        } catch(err) {
            console.error(err);
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display    = 'inline';
            btn.querySelector('.btn-spinner').style.display = 'none';
        }
    });

    // ── EDITAR ────────────────────────────────────────
    window.editarEval = function(id) {
        window.location.href = `editar_evaluacion.php?id=${id}`;
    };

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ESC cierra modales
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        cerrarModal('bdEvaluaciones','mEvaluaciones');
        cerrarModal('bdConfElim','mConfElim');
    });
});
</script>
</body>
</html>