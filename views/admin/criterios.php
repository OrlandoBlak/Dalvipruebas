<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";

$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];
$totalAreas = $conexion->query("SELECT COUNT(*) as t FROM areas")->fetch_assoc()['t'];
$totalCriterios = $conexion->query("SELECT COUNT(*) as t FROM puntos")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criterios de Evaluación – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <link rel="stylesheet" href="../../css/criterios.css">
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
        <a href="homeadmin.php" class="nav-item">
            <span class="nav-icon">⊞</span><span class="nav-text">Resumen General</span>
        </a>
        <a href="departamentos.php" class="nav-item">
            <span class="nav-icon">🏢</span><span class="nav-text">Departamentos</span>
            <span class="nav-badge"><?= $totalAreas ?></span>
        </a>
        <a href="graficas.php" class="nav-item">
            <span class="nav-icon">📊</span><span class="nav-text">Gráficas</span>
        </a>
        <div class="nav-section-label">ANÁLISIS</div>
        <a href="ranking.php" class="nav-item">
            <span class="nav-icon">🏆</span><span class="nav-text">Ranking General</span>
        </a>
        <a href="heatmap.php" class="nav-item">
            <span class="nav-icon">🗺️</span><span class="nav-text">Heatmap</span>
        </a>
        <a href="dashboard.php" class="nav-item">
            <span class="nav-icon">📋</span><span class="nav-text">Dashboard Ejecutivo</span>
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

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">Criterios de Evaluación</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
            <button class="btn-primary" id="btnAgregarCriterio">
                ＋ Agregar Criterio
            </button>
        </div>
    </header>

    <!-- RESUMEN -->
    <div class="criterios-summary">
        <div class="summary-item">
            <span class="summary-num" id="totalCriterios"><?= $totalCriterios ?></span>
            <span class="summary-label">Criterios registrados</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
            <span class="summary-num" id="sumaPuntaje">—</span>
            <span class="summary-label">Puntaje total</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
            <span class="summary-num" id="promPuntaje">—</span>
            <span class="summary-label">Promedio por criterio</span>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="criterios-content">

        <!-- BUSCADOR -->
        <div class="criterios-toolbar">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="buscarCriterio" class="search-global"
                       placeholder="Buscar criterio..." autocomplete="off">
                <button class="search-clear" id="searchClear">✕</button>
            </div>
            <span class="criterios-hint">Haz clic en un criterio para editarlo</span>
        </div>

        <!-- GRID DE CRITERIOS (se llena por JS) -->
        <div class="criterios-grid" id="criteriosGrid">
            <!-- Skeletons mientras carga -->
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="criterio-card skeleton-card">
                <div class="skeleton-line" style="width:70%; height:14px; margin-bottom:10px"></div>
                <div class="skeleton-line" style="width:40%; height:22px; margin-bottom:14px"></div>
                <div class="skeleton-line" style="width:55%; height:10px"></div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Sin criterios -->
        <div class="criterios-empty" id="criteriosEmpty" style="display:none">
            <span class="empty-icon">📝</span>
            <p class="empty-title">Sin criterios registrados</p>
            <p class="empty-sub">Agrega tu primer criterio de evaluación con el botón superior.</p>
            <button class="btn-primary" onclick="document.getElementById('btnAgregarCriterio').click()">
                ＋ Agregar primer criterio
            </button>
        </div>

    </div>

</main>

<!-- ═══════════════════════════════════════════
     MODAL: AGREGAR CRITERIO
════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalBackdropCriterio"></div>
<div class="modal-container" id="modalCriterio" role="dialog" aria-modal="true">

    <div class="modal-header">
        <div class="modal-header-icon">📝</div>
        <div>
            <h3 class="modal-title" id="modalCriterioTitle">Agregar Criterio</h3>
            <p class="modal-subtitle" id="modalCriterioSubtitle">Nuevo criterio de evaluación</p>
        </div>
        <button class="modal-close" id="modalCriterioClose">✕</button>
    </div>

    <form id="formCriterio" novalidate style="padding:22px">

        <input type="hidden" id="criterioId" value="">

        <!-- NOMBRE -->
        <div class="modal-field">
            <label class="modal-label" for="inputNombreCriterio">
                Nombre del criterio <span class="required">*</span>
            </label>
            <div class="input-wrap">
                <span class="input-icon">📋</span>
                <input type="text" id="inputNombreCriterio" name="nombre"
                       class="modal-input" placeholder="Ej. Puntualidad, Trabajo en equipo..."
                       maxlength="120" autocomplete="off" required>
            </div>
            <span class="field-error" id="errorNombreCriterio"></span>
        </div>

        <!-- PUNTAJE -->
        <div class="modal-field">
            <label class="modal-label" for="inputEvaluando">
                Puntaje (Evaluando) <span class="required">*</span>
            </label>
            <div class="input-wrap">
                <span class="input-icon">⭐</span>
                <input type="number" id="inputEvaluando" name="evaluando"
                       class="modal-input" placeholder="Ej. 10.0"
                       min="0" max="100" step="0.1" required>
                <span class="input-suffix">pts</span>
            </div>
            <span class="field-error" id="errorEvaluando"></span>
        </div>

        <!-- Alert general -->
        <div class="modal-alert" id="modalAlertCriterio" style="display:none"></div>

        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="btnCancelarCriterio">Cancelar</button>
            <button type="submit" class="btn-modal-save" id="btnGuardarCriterio">
                <span class="btn-text">Guardar criterio</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: CONFIRMAR ELIMINAR
════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalBackdropEliminar"></div>
<div class="modal-container modal-sm" id="modalEliminar" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#7f1d1d,#ef4444)">🗑️</div>
        <div>
            <h3 class="modal-title">Eliminar criterio</h3>
            <p class="modal-subtitle">Esta acción no se puede deshacer</p>
        </div>
        <button class="modal-close" id="modalEliminarClose">✕</button>
    </div>
    <div style="padding:22px">
        <p class="eliminar-msg">¿Seguro que deseas eliminar el criterio <strong id="eliminarNombre"></strong>?</p>
        <div class="modal-actions" style="margin-top:20px">
            <button class="btn-modal-cancel" id="btnCancelarEliminar">Cancelar</button>
            <button class="btn-eliminar-confirm" id="btnConfirmarEliminar">
                <span class="btn-text">Sí, eliminar</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </div>
</div>

<script src="../../functions/admin_dashboard.js"></script>
<script type="text/javascript">
/**
 * functions/criterios.js
 * CRUD criterios de evaluación – Grupo Dalvi
 * Auto-contenido, sin dependencias externas
 */

(function () {
    'use strict';

    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {

        // ── ELEMENTOS ────────────────────────────────────
        const grid           = document.getElementById('criteriosGrid');
        const emptyState     = document.getElementById('criteriosEmpty');
        const totalEl        = document.getElementById('totalCriterios');
        const sumaEl         = document.getElementById('sumaPuntaje');
        const promEl         = document.getElementById('promPuntaje');
        const buscarInput    = document.getElementById('buscarCriterio');
        const searchClear    = document.getElementById('searchClear');

        // Modal criterio
        const backdropC      = document.getElementById('modalBackdropCriterio');
        const modalC         = document.getElementById('modalCriterio');
        const formC          = document.getElementById('formCriterio');
        const inputNombre    = document.getElementById('inputNombreCriterio');
        const inputEval      = document.getElementById('inputEvaluando');
        const hiddenId       = document.getElementById('criterioId');
        const modalTitleEl   = document.getElementById('modalCriterioTitle');
        const modalSubEl     = document.getElementById('modalCriterioSubtitle');
        const alertEl        = document.getElementById('modalAlertCriterio');
        const errorNombreEl  = document.getElementById('errorNombreCriterio');
        const errorEvalEl    = document.getElementById('errorEvaluando');
        const btnGuardar     = document.getElementById('btnGuardarCriterio');
        const btnCancelarC   = document.getElementById('btnCancelarCriterio');
        const btnCloseC      = document.getElementById('modalCriterioClose');
        const btnAbrir       = document.getElementById('btnAgregarCriterio');

        // Modal eliminar
        const backdropE      = document.getElementById('modalBackdropEliminar');
        const modalE         = document.getElementById('modalEliminar');
        const eliminarNombreEl = document.getElementById('eliminarNombre');
        const btnConfElim    = document.getElementById('btnConfirmarEliminar');
        const btnCancelElim  = document.getElementById('btnCancelarEliminar');
        const btnCloseE      = document.getElementById('modalEliminarClose');

        // Verificar elementos críticos
        if (!grid || !backdropC || !modalC || !btnAbrir) {
            console.error('Criterios: faltan elementos del DOM');
            return;
        }

        let criterios  = [];
        let eliminarId = null;

        // ── ABRIR / CERRAR MODALES ────────────────────────
        function abrirModal(backdrop, modal) {
            backdrop.classList.add('active');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(backdrop, modal) {
            backdrop.classList.remove('active');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // ── BOTÓN AGREGAR ─────────────────────────────────
        btnAbrir.addEventListener('click', () => {
            hiddenId.value = '';
            formC.reset();
            modalTitleEl.textContent = 'Agregar Criterio';
            modalSubEl.textContent   = 'Nuevo criterio de evaluación';
            btnGuardar.querySelector('.btn-text').textContent = 'Guardar criterio';
            alertEl.style.display = 'none';
            limpiarErrores();
            abrirModal(backdropC, modalC);
            setTimeout(() => inputNombre.focus(), 200);
        });

        // Cerrar modal criterio
        btnCancelarC?.addEventListener('click', () => cerrarModal(backdropC, modalC));
        btnCloseC?.addEventListener('click',    () => cerrarModal(backdropC, modalC));
        backdropC?.addEventListener('click',    () => cerrarModal(backdropC, modalC));

        // Cerrar modal eliminar
        btnCancelElim?.addEventListener('click', () => cerrarModal(backdropE, modalE));
        btnCloseE?.addEventListener('click',     () => cerrarModal(backdropE, modalE));
        backdropE?.addEventListener('click',     () => cerrarModal(backdropE, modalE));

        // ESC cierra cualquier modal abierto
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (modalC?.classList.contains('active')) cerrarModal(backdropC, modalC);
            if (modalE?.classList.contains('active')) cerrarModal(backdropE, modalE);
        });

        // ── CARGAR CRITERIOS ──────────────────────────────
        function cargarCriterios() {
            fetch('../../php/api_criterios.php?action=list')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        criterios = data.data;
                        renderGrid(criterios);
                        actualizarStats();
                    } else {
                        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#94a3b8;padding:20px">Error al cargar criterios.</p>';
                    }
                })
                .catch(() => {
                    grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#94a3b8;padding:20px">Error de conexión.</p>';
                });
        }

        // ── RENDER ────────────────────────────────────────
        function renderGrid(lista) {
            if (!lista || !lista.length) {
                grid.innerHTML = '';
                if (emptyState) emptyState.style.display = 'block';
                return;
            }
            if (emptyState) emptyState.style.display = 'none';

            grid.innerHTML = lista.map((c, i) => {
                const val = parseFloat(c.Evaluando) || 0;
                const pct = Math.min((val / 100) * 100, 100).toFixed(0);
                return `
                <div class="criterio-card" data-id="${c.Id_Criterios}">
                    <span class="criterio-num">#${String(i + 1).padStart(2, '0')}</span>
                    <div class="criterio-nombre">${esc(c.Nombre_Criterio)}</div>
                    <div class="criterio-puntaje">
                        <span class="puntaje-valor">${val.toFixed(1)}</span>
                        <span class="puntaje-label">pts</span>
                    </div>
                    <div class="criterio-barra">
                        <div class="criterio-barra-fill" style="width:${pct}%"></div>
                    </div>
                    <div class="criterio-acciones">
                        <button class="btn-criterio editar"   data-id="${c.Id_Criterios}">✏️ Editar</button>
                        <button class="btn-criterio eliminar" data-id="${c.Id_Criterios}" data-nombre="${esc(c.Nombre_Criterio)}">🗑️ Eliminar</button>
                    </div>
                </div>`;
            }).join('');

            // Eventos de las tarjetas (delegación)
            grid.querySelectorAll('.btn-criterio.editar').forEach(btn => {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    abrirEditar(parseInt(btn.dataset.id));
                });
            });
            grid.querySelectorAll('.btn-criterio.eliminar').forEach(btn => {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    abrirEliminar(parseInt(btn.dataset.id), btn.dataset.nombre);
                });
            });
        }

        // ── EDITAR ────────────────────────────────────────
        function abrirEditar(id) {
            const c = criterios.find(x => parseInt(x.Id_Criterios) === id);
            if (!c) return;
            hiddenId.value    = c.Id_Criterios;
            inputNombre.value = c.Nombre_Criterio;
            inputEval.value   = parseFloat(c.Evaluando).toFixed(1);
            modalTitleEl.textContent = 'Editar Criterio';
            modalSubEl.textContent   = `Modificando: ${c.Nombre_Criterio}`;
            btnGuardar.querySelector('.btn-text').textContent = 'Guardar cambios';
            alertEl.style.display = 'none';
            limpiarErrores();
            abrirModal(backdropC, modalC);
            setTimeout(() => inputNombre.focus(), 200);
        }

        // ── GUARDAR (crear / editar) ──────────────────────
        formC?.addEventListener('submit', function (e) {
            e.preventDefault();
            alertEl.style.display = 'none';
            if (!validar()) return;

            const isEdit = !!hiddenId.value;
            const fd = new FormData();
            fd.append('action',    isEdit ? 'editar' : 'crear');
            fd.append('nombre',    inputNombre.value.trim());
            fd.append('evaluando', inputEval.value);
            if (isEdit) fd.append('id', hiddenId.value);

            setLoading(btnGuardar, true);

            fetch('../../php/api_criterios.php', {credentials:'include', method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cerrarModal(backdropC, modalC);
                        cargarCriterios();
                    } else {
                        alertEl.textContent   = '⚠️ ' + (data.error || 'Error al guardar.');
                        alertEl.style.display = 'block';
                    }
                })
                .catch(() => {
                    alertEl.textContent   = '⚠️ Error de conexión.';
                    alertEl.style.display = 'block';
                })
                .finally(() => setLoading(btnGuardar, false));
        });

        // ── ELIMINAR ──────────────────────────────────────
        function abrirEliminar(id, nombre) {
            eliminarId = id;
            if (eliminarNombreEl) eliminarNombreEl.textContent = nombre;
            abrirModal(backdropE, modalE);
        }

        btnConfElim?.addEventListener('click', () => {
            if (!eliminarId) return;
            const fd = new FormData();
            fd.append('action', 'eliminar');
            fd.append('id', eliminarId);
            setLoading(btnConfElim, true);

            fetch('../../php/api_criterios.php', {credentials:'include', method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cerrarModal(backdropE, modalE);
                        eliminarId = null;
                        cargarCriterios();
                    }
                })
                .catch(() => console.error('Error al eliminar'))
                .finally(() => setLoading(btnConfElim, false));
        });

        // ── STATS ─────────────────────────────────────────
        function actualizarStats() {
            const total = criterios.length;
            const suma  = criterios.reduce((s, c) => s + (parseFloat(c.Evaluando) || 0), 0);
            const prom  = total ? suma / total : 0;
            if (totalEl) totalEl.textContent = total;
            if (sumaEl)  sumaEl.textContent  = suma.toFixed(1);
            if (promEl)  promEl.textContent  = prom.toFixed(1);
        }

        // ── BUSCADOR ──────────────────────────────────────
        buscarInput?.addEventListener('input', () => {
            const q = buscarInput.value.trim().toLowerCase();
            if (searchClear) searchClear.classList.toggle('visible', q.length > 0);
            if (!q) { renderGrid(criterios); return; }
            renderGrid(criterios.filter(c => c.Nombre_Criterio.toLowerCase().includes(q)));
        });
        searchClear?.addEventListener('click', () => {
            buscarInput.value = '';
            searchClear.classList.remove('visible');
            renderGrid(criterios);
            buscarInput.focus();
        });

        // ── VALIDACIÓN ────────────────────────────────────
        function validar() {
            let ok = true;
            const nombre = inputNombre.value.trim();
            const val    = inputEval.value;
            if (!nombre || nombre.length < 2) {
                setErr(inputNombre, errorNombreEl, 'Mínimo 2 caracteres.'); ok = false;
            } else clearErr(inputNombre, errorNombreEl);
            if (!val || isNaN(parseFloat(val)) || parseFloat(val) < 0) {
                setErr(inputEval, errorEvalEl, 'Ingresa un puntaje válido (ej. 10.0).'); ok = false;
            } else clearErr(inputEval, errorEvalEl);
            return ok;
        }
        function setErr(el, span, msg)  { el.classList.add('is-error');    if(span) span.textContent = msg; }
        function clearErr(el, span)     { el.classList.remove('is-error'); if(span) span.textContent = ''; }
        function limpiarErrores()       { clearErr(inputNombre, errorNombreEl); clearErr(inputEval, errorEvalEl); }
        inputNombre?.addEventListener('input', () => clearErr(inputNombre, errorNombreEl));
        inputEval?.addEventListener('input',   () => clearErr(inputEval,   errorEvalEl));

        // ── HELPERS ───────────────────────────────────────
        function setLoading(btn, on) {
            btn.disabled = on;
            const t = btn.querySelector('.btn-text');
            const s = btn.querySelector('.btn-spinner');
            if (t) t.style.display = on ? 'none'   : 'inline';
            if (s) s.style.display = on ? 'inline' : 'none';
        }
        function esc(str) {
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── ARRANQUE ──────────────────────────────────────
        cargarCriterios();
    }

})();

</script>
</body>
</html>