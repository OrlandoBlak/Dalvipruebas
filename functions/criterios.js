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

            fetch('../../php/api_criterios.php', { method: 'POST', body: fd })
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

            fetch('../../php/api_criterios.php', { method: 'POST', body: fd })
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