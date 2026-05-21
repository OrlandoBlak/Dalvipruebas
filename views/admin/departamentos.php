<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

// ── Detectar columnas dinámicamente ──────────────────────
$fkCol    = 'FK_Id_Area';
$cargoCol = null;
$colsRes  = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($col = $colsRes->fetch_assoc()) {
    if (stripos($col['Field'], 'area')  !== false && !$fkCol)    $fkCol    = $col['Field'];
    if (stripos($col['Field'], 'cargo') !== false && !$cargoCol) $cargoCol = $col['Field'];
    if (stripos($col['Field'], 'puest') !== false && !$cargoCol) $cargoCol = $col['Field'];
}
// Forzar detección separada
$fkCol = 'FK_Id_Area';
$colsRes2 = $conexion->query("SHOW COLUMNS FROM colaboradores");
while ($col = $colsRes2->fetch_assoc()) {
    if (stripos($col['Field'], 'area') !== false)  { $fkCol    = $col['Field']; }
    if (stripos($col['Field'], 'cargo') !== false) { $cargoCol = $col['Field']; }
    if (stripos($col['Field'], 'puest') !== false && !$cargoCol) { $cargoCol = $col['Field']; }
}

$selectCargo = $cargoCol ? "c.`$cargoCol` AS Cargo" : "'' AS Cargo";

// ── Query principal ───────────────────────────────────────
$res = $conexion->query("
    SELECT a.Id_Area, a.Nombre AS area_nombre,
           c.Id_Colaborador, c.Nombre AS colab_nombre, $selectCargo
    FROM areas a
    LEFT JOIN colaboradores c ON c.`$fkCol` = a.Id_Area
    ORDER BY a.Nombre ASC, c.Nombre ASC
");
if (!$res) die("Error BD: " . $conexion->error);

$areas = [];
while ($row = $res->fetch_assoc()) {
    $id = $row['Id_Area'];
    if (!isset($areas[$id])) $areas[$id] = ['nombre'=>$row['area_nombre'],'colaboradores'=>[]];
    if ($row['Id_Colaborador']) $areas[$id]['colaboradores'][] = [
        'id'=>$row['Id_Colaborador'], 'nombre'=>$row['colab_nombre'], 'cargo'=>$row['Cargo']
    ];
}

// ── Áreas para el select del modal ───────────────────────
$areasSelect = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
$areasArr = [];
while ($a = $areasSelect->fetch_assoc()) $areasArr[] = $a;

$totalAreas = count($areas);
$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];

$iconosAreas = [1=>'🛒',2=>'👥',3=>'📢',4=>'🚚',5=>'🏛️',6=>'💻',7=>'🛍️',8=>'📦',9=>'🎨',10=>'🧹',11=>'👔',12=>'🏪',13=>'🏗️'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departamentos – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <link rel="stylesheet" href="../../css/departamentos.css">
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
        <a href="departamentos.php" class="nav-item active"><span class="nav-icon">🏢</span><span class="nav-text">Departamentos</span><span class="nav-badge"><?= $totalAreas ?></span></a>
        <a href="graficas.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Gráficas</span></a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="ranking.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span></a>
        <a href="heatmap.php" class="nav-item"><span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span></a>
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
            <h1 class="page-title">Departamentos</h1>
        </div>
        <div class="topbar-right">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="buscadorGlobal" class="search-global" placeholder="Buscar colaborador o área..." autocomplete="off">
                <button class="search-clear" id="searchClear">✕</button>
            </div>
            <button class="btn-secondary" id="btnAgregarColab">➕ Agregar Colaborador</button>
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
        </div>
    </header>

    <div class="deptos-summary">
        <div class="summary-item"><span class="summary-num"><?= $totalAreas ?></span><span class="summary-label">Departamentos</span></div>
        <div class="summary-divider"></div>
        <div class="summary-item"><span class="summary-num"><?= $totalColab ?></span><span class="summary-label">Colaboradores</span></div>
        <div class="summary-divider"></div>
        <div class="summary-item"><span class="summary-num" id="conteoFiltrado"><?= $totalColab ?></span><span class="summary-label">Mostrando</span></div>
    </div>

    <div class="deptos-lista" id="deptosLista">
        <?php foreach ($areas as $idArea => $area):
            $icon  = $iconosAreas[$idArea] ?? '🏢';
            $count = count($area['colaboradores']);
        ?>
        <div class="area-bloque" data-area="<?= strtolower(htmlspecialchars($area['nombre'])) ?>">

            <div class="area-header">
                <div class="area-header-left">
                    <span class="area-icon"><?= $icon ?></span>
                    <div class="area-info">
                        <span class="area-nombre"><?= htmlspecialchars($area['nombre']) ?></span>
                        <span class="area-sub"><?= $count ?> colaborador<?= $count !== 1 ? 'es' : '' ?></span>
                    </div>
                </div>
                <div class="area-header-right">
                    <span class="area-badge <?= $count > 0 ? '' : 'empty' ?>"><?= $count ?></span>
                </div>
            </div>

            <div class="area-body">
                <?php if ($count === 0): ?>
                <div class="area-vacia">
                    <span class="area-vacia-icon">👤</span>
                    <p class="area-vacia-texto">Por el momento no hay colaboradores en este departamento.</p>
                </div>
                <?php else: ?>
                <div class="colab-tabla">
                    <div class="colab-tabla-header">
                        <span class="col-num">#</span>
                        <span class="col-nombre">Colaborador</span>
                        <span class="col-cargo">Cargo</span>
                        <span class="col-acciones">Acciones</span>
                    </div>
                    <?php foreach ($area['colaboradores'] as $i => $c): ?>
                    <div class="colab-fila"
                         data-nombre="<?= strtolower(htmlspecialchars($c['nombre'])) ?>"
                         data-cargo="<?= strtolower(htmlspecialchars($c['cargo'] ?? '')) ?>">
                        <span class="col-num"><?= $i + 1 ?></span>
                        <span class="col-nombre">
                            <div class="colab-avatar-sm"><?= strtoupper(mb_substr($c['nombre'], 0, 1)) ?></div>
                            <span class="colab-nombre-txt"><?= htmlspecialchars($c['nombre']) ?></span>
                        </span>
                        <span class="col-cargo">
                            <?php if (!empty($c['cargo'])): ?>
                                <span class="cargo-tag"><?= htmlspecialchars($c['cargo']) ?></span>
                            <?php else: ?>
                                <span class="cargo-sin">—</span>
                            <?php endif; ?>
                        </span>
                        <span class="col-acciones">
                            <button class="btn-accion editar"
                                data-action="editar"
                                data-id="<?= $c['id'] ?>"
                                data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                                data-cargo="<?= htmlspecialchars($c['cargo'] ?? '', ENT_QUOTES) ?>"
                                data-area="<?= $idArea ?>"
                                title="Editar">✏️</button>
                            <button class="btn-accion eliminar"
                                data-action="eliminar"
                                data-id="<?= $c['id'] ?>"
                                data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                                title="Eliminar">🗑️</button>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="sin-resultados" id="sinResultados" style="display:none">
            <span class="sin-icon">🔍</span>
            <p>Sin resultados para <strong id="terminoBusqueda"></strong></p>
            <button class="btn-secondary" id="btnLimpiarBusqueda">Limpiar búsqueda</button>
        </div>
    </div>

</main>

<!-- ══ MODAL AGREGAR ══ -->
<div class="modal-backdrop" id="bdAgregar" style="display:none"></div>
<div class="modal-container"  id="mAgregar"  style="display:none" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon">👤</div>
        <div><h3 class="modal-title">Agregar Colaborador</h3><p class="modal-subtitle">Nuevo registro</p></div>
        <button class="modal-close" id="mAgregarClose">✕</button>
    </div>
    <form id="fAgregar" novalidate style="padding:22px">
        <div class="modal-field">
            <label class="modal-label">Nombre completo <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">✏️</span>
                <input type="text" id="aNombre" class="modal-input" placeholder="Ej. Juan Pérez García" maxlength="120" autocomplete="off">
            </div>
            <span class="field-error" id="aErrNombre"></span>
        </div>
        <div class="modal-field">
            <label class="modal-label">Departamento / Área <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">🏢</span>
                <select id="aArea" class="modal-select">
                    <option value="">— Selecciona un área —</option>
                    <?php foreach ($areasArr as $a): ?>
                    <option value="<?= $a['Id_Area'] ?>"><?= htmlspecialchars($a['Nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
            </div>
            <span class="field-error" id="aErrArea"></span>
        </div>
        <div class="modal-alert" id="aAlert" style="display:none"></div>
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="mAgregarCancel">Cancelar</button>
            <button type="submit" class="btn-modal-save" id="aBtnGuardar">
                <span class="btn-text">Guardar colaborador</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </form>
    <div id="aSuccess" style="display:none;padding:32px;text-align:center">
        <div style="font-size:48px;margin-bottom:12px">✅</div>
        <p style="font-size:17px;font-weight:700;margin-bottom:6px">¡Colaborador agregado!</p>
        <p id="aSuccessMsg" style="font-size:13px;color:#64748b;margin-bottom:20px"></p>
        <div class="modal-actions" style="justify-content:center;gap:10px">
            <button class="btn-modal-cancel" id="aOtro">➕ Agregar otro</button>
            <button class="btn-modal-save"   id="aListo">Listo</button>
        </div>
    </div>
</div>

<!-- ══ MODAL EDITAR ══ -->
<div class="modal-backdrop" id="bdEditar" style="display:none"></div>
<div class="modal-container"  id="mEditar"  style="display:none" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6)">✏️</div>
        <div><h3 class="modal-title">Editar Colaborador</h3><p class="modal-subtitle" id="eSubtitle">Modificar datos</p></div>
        <button class="modal-close" id="mEditarClose">✕</button>
    </div>
    <form id="fEditar" novalidate style="padding:22px">
        <input type="hidden" id="eId">
        <div class="modal-field">
            <label class="modal-label">Nombre completo <span class="required">*</span></label>
            <div class="input-wrap"><span class="input-icon">✏️</span>
                <input type="text" id="eNombre" class="modal-input" maxlength="120" autocomplete="off">
            </div>
            <span class="field-error" id="eErrNombre"></span>
        </div>
        <div class="modal-field">
            <label class="modal-label">Cargo / Puesto</label>
            <div class="input-wrap"><span class="input-icon">💼</span>
                <input type="text" id="eCargo" class="modal-input" maxlength="100" autocomplete="off">
            </div>
        </div>
        <div class="modal-field">
            <label class="modal-label">Departamento / Área <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">🏢</span>
                <select id="eArea" class="modal-select">
                    <option value="">— Selecciona —</option>
                    <?php foreach ($areasArr as $a): ?>
                    <option value="<?= $a['Id_Area'] ?>"><?= htmlspecialchars($a['Nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
            </div>
            <span class="field-error" id="eErrArea"></span>
        </div>
        <div class="modal-alert" id="eAlert" style="display:none"></div>
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="mEditarCancel">Cancelar</button>
            <button type="submit" class="btn-modal-save" id="eBtnGuardar">
                <span class="btn-text">Guardar cambios</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </form>
</div>

<!-- ══ MODAL ELIMINAR ══ -->
<div class="modal-backdrop" id="bdEliminar" style="display:none"></div>
<div class="modal-container modal-sm" id="mEliminar" style="display:none" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#7f1d1d,#ef4444)">🗑️</div>
        <div><h3 class="modal-title">Eliminar colaborador</h3><p class="modal-subtitle">No se puede deshacer</p></div>
        <button class="modal-close" id="mEliminarClose">✕</button>
    </div>
    <div style="padding:22px">
        <p style="font-size:14px;color:#4a5568;line-height:1.6">
            ¿Seguro que deseas eliminar a <strong id="eNombreElim"></strong>?<br>
            <span style="font-size:12px;color:#94a3b8">Se eliminarán todos sus datos.</span>
        </p>
        <div class="modal-actions" style="margin-top:20px">
            <button class="btn-modal-cancel"   id="mEliminarCancel">Cancelar</button>
            <button class="btn-eliminar-confirm" id="eBtnEliminar">
                <span class="btn-text">Sí, eliminar</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </div>
</div>

<script src="../../functions/admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

// ══════════════════════════════════════════════════════
// DEPARTAMENTOS — JS completo autocontenido
// ══════════════════════════════════════════════════════

const API = '../../php/api_colaboradores.php';

// ── Helpers modal ────────────────────────────────────
function abrirM(bd, m) {
    const bdEl = document.getElementById(bd);
    const mEl  = document.getElementById(m);
    if (bdEl) { bdEl.style.display = 'block'; bdEl.classList.add('active'); }
    if (mEl)  { mEl.style.display  = 'block'; mEl.classList.add('active');  }
    document.body.style.overflow = 'hidden';
}
function cerrarM(bd, m) {
    const bdEl = document.getElementById(bd);
    const mEl  = document.getElementById(m);
    if (bdEl) { bdEl.style.display = 'none'; bdEl.classList.remove('active'); }
    if (mEl)  { mEl.style.display  = 'none'; mEl.classList.remove('active');  }
    document.body.style.overflow = '';
}
function loadBtn(id, on) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.disabled = on;
    const t = btn.querySelector('.btn-text');
    const s = btn.querySelector('.btn-spinner');
    if (t) t.style.display = on ? 'none'   : 'inline';
    if (s) s.style.display = on ? 'inline' : 'none';
}

// Cerrar con ESC
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    cerrarM('bdAgregar', 'mAgregar');
    cerrarM('bdEditar',  'mEditar');
    cerrarM('bdEliminar','mEliminar');
});
// Cerrar al click backdrop
['bdAgregar','bdEditar','bdEliminar'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => {
        const map = {bdAgregar:'mAgregar', bdEditar:'mEditar', bdEliminar:'mEliminar'};
        cerrarM(id, map[id]);
    });
});

// ── MODAL AGREGAR ─────────────────────────────────────
document.getElementById('btnAgregarColab')?.addEventListener('click', () => {
    document.getElementById('fAgregar').reset();
    document.getElementById('fAgregar').style.display = 'block';
    document.getElementById('aSuccess').style.display  = 'none';
    document.getElementById('aAlert').style.display    = 'none';
    document.getElementById('aNombre').classList.remove('is-error');
    document.getElementById('aArea').classList.remove('is-error');
    document.getElementById('aErrNombre').textContent = '';
    document.getElementById('aErrArea').textContent   = '';
    abrirM('bdAgregar','mAgregar');
    setTimeout(() => document.getElementById('aNombre')?.focus(), 200);
});
document.getElementById('mAgregarClose')?.addEventListener('click',  () => cerrarM('bdAgregar','mAgregar'));
document.getElementById('mAgregarCancel')?.addEventListener('click', () => cerrarM('bdAgregar','mAgregar'));

document.getElementById('fAgregar')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const nombre  = document.getElementById('aNombre').value.trim();
    const id_area = document.getElementById('aArea').value;
    const alertEl = document.getElementById('aAlert');
    alertEl.style.display = 'none';
    let ok = true;

    if (!nombre || nombre.length < 2) {
        document.getElementById('aNombre').classList.add('is-error');
        document.getElementById('aErrNombre').textContent = 'Mínimo 2 caracteres.'; ok = false;
    } else { document.getElementById('aNombre').classList.remove('is-error'); document.getElementById('aErrNombre').textContent = ''; }

    if (!id_area) {
        document.getElementById('aArea').classList.add('is-error');
        document.getElementById('aErrArea').textContent = 'Selecciona un área.'; ok = false;
    } else { document.getElementById('aArea').classList.remove('is-error'); document.getElementById('aErrArea').textContent = ''; }

    if (!ok) return;
    loadBtn('aBtnGuardar', true);

    const fd = new FormData();
    fd.append('action', 'crear'); fd.append('nombre', nombre); fd.append('id_area', id_area);

    try {
        const res  = await fetch(API, {method:'POST', body:fd, credentials:'include'});
        const data = await res.json();
        if (data.success) {
            document.getElementById('fAgregar').style.display  = 'none';
            document.getElementById('aSuccess').style.display  = 'block';
            document.getElementById('aSuccessMsg').textContent = '"' + nombre + '" fue agregado correctamente.';
        } else {
            alertEl.textContent = '⚠️ ' + (data.error||'Error al guardar'); alertEl.style.display = 'block';
        }
    } catch { alertEl.textContent = '⚠️ Error de conexión'; alertEl.style.display = 'block'; }
    finally  { loadBtn('aBtnGuardar', false); }
});

document.getElementById('aOtro')?.addEventListener('click', () => {
    document.getElementById('fAgregar').reset();
    document.getElementById('fAgregar').style.display = 'block';
    document.getElementById('aSuccess').style.display  = 'none';
});
document.getElementById('aListo')?.addEventListener('click', () => {
    cerrarM('bdAgregar','mAgregar');
    setTimeout(() => location.reload(), 200);
});

// ── MODAL EDITAR ──────────────────────────────────────
document.getElementById('deptosLista')?.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const {action, id, nombre, cargo, area} = btn.dataset;

    if (action === 'editar') {
        document.getElementById('eId').value      = id;
        document.getElementById('eNombre').value  = nombre;
        document.getElementById('eCargo').value   = cargo || '';
        document.getElementById('eArea').value    = area;
        document.getElementById('eSubtitle').textContent = 'Editando: ' + nombre;
        document.getElementById('eAlert').style.display   = 'none';
        document.getElementById('eNombre').classList.remove('is-error');
        document.getElementById('eArea').classList.remove('is-error');
        document.getElementById('eErrNombre').textContent = '';
        document.getElementById('eErrArea').textContent   = '';
        abrirM('bdEditar','mEditar');
        setTimeout(() => document.getElementById('eNombre')?.focus(), 200);
    }

    if (action === 'eliminar') {
        document.getElementById('eNombreElim').textContent = nombre;
        document.getElementById('eBtnEliminar').dataset.id = id;
        abrirM('bdEliminar','mEliminar');
    }
});

document.getElementById('mEditarClose')?.addEventListener('click',  () => cerrarM('bdEditar','mEditar'));
document.getElementById('mEditarCancel')?.addEventListener('click', () => cerrarM('bdEditar','mEditar'));

document.getElementById('fEditar')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const id      = document.getElementById('eId').value;
    const nombre  = document.getElementById('eNombre').value.trim();
    const cargo   = document.getElementById('eCargo').value.trim();
    const id_area = document.getElementById('eArea').value;
    const alertEl = document.getElementById('eAlert');
    alertEl.style.display = 'none';
    let ok = true;

    if (!nombre || nombre.length < 2) {
        document.getElementById('eNombre').classList.add('is-error');
        document.getElementById('eErrNombre').textContent = 'Mínimo 2 caracteres.'; ok = false;
    } else { document.getElementById('eNombre').classList.remove('is-error'); document.getElementById('eErrNombre').textContent = ''; }

    if (!id_area) {
        document.getElementById('eArea').classList.add('is-error');
        document.getElementById('eErrArea').textContent = 'Selecciona un área.'; ok = false;
    } else { document.getElementById('eArea').classList.remove('is-error'); document.getElementById('eErrArea').textContent = ''; }

    if (!ok) return;
    loadBtn('eBtnGuardar', true);

    const fd = new FormData();
    fd.append('action','editar'); fd.append('id',id);
    fd.append('nombre',nombre); fd.append('cargo',cargo); fd.append('id_area',id_area);

    try {
        const res  = await fetch(API, {method:'POST', body:fd, credentials:'include'});
        const data = await res.json();
        if (data.success) { cerrarM('bdEditar','mEditar'); setTimeout(() => location.reload(), 200); }
        else { alertEl.textContent = '⚠️ ' + (data.error||'Error'); alertEl.style.display = 'block'; }
    } catch { alertEl.textContent = '⚠️ Error de conexión'; alertEl.style.display = 'block'; }
    finally  { loadBtn('eBtnGuardar', false); }
});

// ── MODAL ELIMINAR ────────────────────────────────────
document.getElementById('mEliminarClose')?.addEventListener('click',  () => cerrarM('bdEliminar','mEliminar'));
document.getElementById('mEliminarCancel')?.addEventListener('click', () => cerrarM('bdEliminar','mEliminar'));

document.getElementById('eBtnEliminar')?.addEventListener('click', async function() {
    const id = this.dataset.id;
    loadBtn('eBtnEliminar', true);
    const fd = new FormData();
    fd.append('action','eliminar'); fd.append('id', id);
    try {
        const res  = await fetch(API, {method:'POST', body:fd, credentials:'include'});
        const data = await res.json();
        if (data.success) { cerrarM('bdEliminar','mEliminar'); setTimeout(() => location.reload(), 200); }
    } catch { console.error('Error al eliminar'); }
    finally  { loadBtn('eBtnEliminar', false); }
});

// ── BUSCADOR ──────────────────────────────────────────
const busInput = document.getElementById('buscadorGlobal');
const busClear = document.getElementById('searchClear');

busInput?.addEventListener('input', () => {
    const q = busInput.value.trim().toLowerCase();
    busClear?.classList.toggle('visible', q.length > 0);
    filtrar(q);
});
busClear?.addEventListener('click', () => { busInput.value=''; busClear.classList.remove('visible'); filtrar(''); });
document.getElementById('btnLimpiarBusqueda')?.addEventListener('click', () => { busInput.value=''; busClear?.classList.remove('visible'); filtrar(''); });
busInput?.addEventListener('keydown', e => { if (e.key==='Escape') { busInput.value=''; filtrar(''); } });

function filtrar(q) {
    let total = 0;
    document.querySelectorAll('.area-bloque').forEach(bloque => {
        const filas = bloque.querySelectorAll('.colab-fila');
        const areaNombre = bloque.dataset.area || '';
        let vis = 0;
        if (!q) { filas.forEach(f => f.style.display=''); bloque.style.display=''; total+=filas.length; return; }
        filas.forEach(fila => {
            const match = (fila.dataset.nombre||'').includes(q) || (fila.dataset.cargo||'').includes(q);
            fila.style.display = match ? '' : 'none';
            if (match) vis++;
        });
        if (vis > 0 || areaNombre.includes(q)) { bloque.style.display=''; total+=vis; }
        else bloque.style.display = 'none';
    });
    const conteo = document.getElementById('conteoFiltrado');
    if (conteo) conteo.textContent = total;
    const sinRes = document.getElementById('sinResultados');
    const term   = document.getElementById('terminoBusqueda');
    if (sinRes) sinRes.style.display = (q && total===0) ? 'block' : 'none';
    if (term && q) term.textContent = '"' + busInput.value + '"';
}

}); // end DOMContentLoaded
</script>
</body>
</html>