/**
 * functions/kpis.js – Grupo Dalvi
 * CRUD KPIs con nueva estructura: Nombre, Tipo, Metas, Id_Area
 */
(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }

    const ICONOS = { 1:'🛒',2:'👥',3:'📢',4:'🚚',5:'🏛️',6:'💻',7:'🛍️',8:'📦',9:'🎨',10:'🧹',11:'👔',12:'🏪',13:'🏗️' };
    const TIPO_CONFIG = {
        'Dinero (MXN)':   { icon:'💰', suffix:'$',   hint:'Monto en pesos mexicanos', prefix:true  },
        'Número':         { icon:'🔢', suffix:'',    hint:'Valor numérico',            prefix:false },
        'Porcentaje (%)': { icon:'📊', suffix:'%',   hint:'Porcentaje de 0 a 100',    prefix:false },
        'Unidades':       { icon:'📦', suffix:'uds', hint:'Cantidad de unidades',      prefix:false },
    };

    function init() {
        const API = '../../php/api_kpis.php';

        // Elementos
        const grid          = document.getElementById('kpisGrid');
        const emptyState    = document.getElementById('kpisEmpty');
        const totalKpisEl   = document.getElementById('totalKpis');
        const sinKpiEl      = document.getElementById('sinKpi');
        const btnAbrir      = document.getElementById('btnAgregarKpi');

        // Modal KPI
        const backdropK     = document.getElementById('modalBackdropKpi');
        const modalK        = document.getElementById('modalKpi');
        const formK         = document.getElementById('formKpi');
        const hiddenId      = document.getElementById('kpiId');
        const inputNombre   = document.getElementById('inputKpiNombre');
        const selectTipo    = document.getElementById('selectKpiTipo');
        const inputMeta     = document.getElementById('inputKpiMeta');
        const selectArea    = document.getElementById('selectKpiArea');
        const tipoIconEl    = document.getElementById('tipoIcon');
        const metaHintEl    = document.getElementById('metaHint');
        const metaSuffixEl  = document.getElementById('metaSuffix');
        const titleEl       = document.getElementById('modalKpiTitle');
        const subEl         = document.getElementById('modalKpiSubtitle');
        const alertEl       = document.getElementById('modalAlertKpi');
        const errNombre     = document.getElementById('errKpiNombre');
        const errTipo       = document.getElementById('errKpiTipo');
        const errMeta       = document.getElementById('errKpiMeta');
        const errArea       = document.getElementById('errKpiArea');
        const btnGuardar    = document.getElementById('btnGuardarKpi');
        const btnCancelar   = document.getElementById('btnCancelarKpi');
        const btnClose      = document.getElementById('modalKpiClose');

        // Modal eliminar
        const backdropE     = document.getElementById('modalBackdropElimKpi');
        const modalE        = document.getElementById('modalElimKpi');
        const elimNombreEl  = document.getElementById('elimKpiNombre');
        const btnConfElim   = document.getElementById('btnConfirmarElimKpi');
        const btnCanElim    = document.getElementById('btnCancelarElimKpi');
        const btnCloseE     = document.getElementById('modalElimKpiClose');

        let kpis       = [];
        let areas      = [];
        let eliminarId = null;
        let modoEditar = false;

        if (!grid || !btnAbrir) return;

        // ── MODALES ──────────────────────────────────────
        const abrirModal  = (b,m) => { b.classList.add('active'); m.classList.add('active'); document.body.style.overflow='hidden'; };
        const cerrarModal = (b,m) => { b.classList.remove('active'); m.classList.remove('active'); document.body.style.overflow=''; };

        [btnCancelar, btnClose, backdropK].forEach(el => el?.addEventListener('click', () => cerrarModal(backdropK, modalK)));
        [btnCanElim, btnCloseE, backdropE].forEach(el => el?.addEventListener('click', () => cerrarModal(backdropE, modalE)));
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (modalK?.classList.contains('active')) cerrarModal(backdropK, modalK);
            if (modalE?.classList.contains('active')) cerrarModal(backdropE, modalE);
        });

        // ── CAMBIO DE TIPO: actualiza icono, hint y suffix ──
        selectTipo?.addEventListener('change', () => actualizarTipoUI(selectTipo.value));

        function actualizarTipoUI(tipo) {
            const cfg = TIPO_CONFIG[tipo] || TIPO_CONFIG['Número'];
            if (tipoIconEl)   tipoIconEl.textContent  = cfg.icon;
            if (metaHintEl)   metaHintEl.textContent  = cfg.hint;
            if (metaSuffixEl) metaSuffixEl.textContent = cfg.suffix;
        }

        // ── CARGAR ÁREAS ─────────────────────────────────
        async function cargarAreas() {
            if (areas.length) { poblarSelectAreas(); return; }
            selectArea.innerHTML = '<option value="">Cargando...</option>';
            selectArea.disabled  = true;
            try {
                const r    = await fetch(API + '?action=areas', {credentials:'include'});
                const data = await r.json();
                if (data.success && data.data.length) {
                    areas = data.data;
                    poblarSelectAreas();
                } else {
                    selectArea.innerHTML = `<option value="">Error: ${data.error || 'Sin áreas'}</option>`;
                    console.error('Areas error:', data);
                }
            } catch(err) {
                selectArea.innerHTML = '<option value="">Error de conexión</option>';
                console.error('cargarAreas error:', err);
            } finally {
                selectArea.disabled = false;
            }
        }

        function poblarSelectAreas() {
            selectArea.innerHTML = '<option value="">— Selecciona un departamento —</option>';
            areas.forEach(a => {
                const icon = ICONOS[a.Id_Area] ?? '🏢';
                const opt  = document.createElement('option');
                opt.value       = a.Id_Area;
                opt.textContent = icon + ' ' + a.Nombre;
                selectArea.appendChild(opt);
            });
        }

        // ── BOTÓN ABRIR CREAR ─────────────────────────────
        btnAbrir.addEventListener('click', async () => {
            modoEditar        = false;
            hiddenId.value    = '';
            formK.reset();
            titleEl.textContent = 'Nuevo KPI';
            subEl.textContent   = 'Define nombre, tipo, meta y área';
            btnGuardar.querySelector('.btn-text').textContent = 'Guardar';
            alertEl.style.display = 'none';
            limpiarErrores();
            actualizarTipoUI('Dinero (MXN)');
            abrirModal(backdropK, modalK);
            await cargarAreas();
            setTimeout(() => inputNombre?.focus(), 200);
        });

        // ── EDITAR ────────────────────────────────────────
        function abrirEditar(id) {
            const k = kpis.find(x => x.Id_KPs == id);
            if (!k) return;
            modoEditar            = true;
            hiddenId.value        = k.Id_KPs;
            inputNombre.value     = k.Nombre;
            selectTipo.value      = k.Tipo;
            inputMeta.value       = parseFloat(k.Metas).toFixed(2);
            titleEl.textContent   = 'Editar KPI';
            subEl.textContent     = `Modificando: ${k.Nombre}`;
            btnGuardar.querySelector('.btn-text').textContent = 'Guardar cambios';
            alertEl.style.display = 'none';
            actualizarTipoUI(k.Tipo);
            limpiarErrores();
            abrirModal(backdropK, modalK);

            // Cargar áreas y seleccionar la actual
            cargarAreas().then(() => { selectArea.value = k.Id_Area; });
            setTimeout(() => inputNombre?.focus(), 200);
        }

        // ── SUBMIT ────────────────────────────────────────
        formK?.addEventListener('submit', function(e) {
            e.preventDefault();
            alertEl.style.display = 'none';
            if (!validar()) return;

            const fd = new FormData();
            fd.append('action',   modoEditar ? 'editar' : 'crear');
            fd.append('nombre',   inputNombre.value.trim());
            fd.append('tipo',     selectTipo.value);
            fd.append('metas',    inputMeta.value);
            fd.append('id_area',  selectArea.value);
            if (modoEditar) fd.append('id', hiddenId.value);

            setLoading(btnGuardar, true);
            fetch(API, {credentials:'include', method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { cerrarModal(backdropK, modalK); cargarKpis(); }
                    else { alertEl.textContent = '⚠️ ' + (data.error || 'Error'); alertEl.style.display='block'; }
                })
                .catch(() => { alertEl.textContent='⚠️ Error de conexión'; alertEl.style.display='block'; })
                .finally(() => setLoading(btnGuardar, false));
        });

        // ── ELIMINAR ─────────────────────────────────────
        function abrirEliminar(id, nombre) {
            eliminarId = id;
            if (elimNombreEl) elimNombreEl.textContent = nombre;
            abrirModal(backdropE, modalE);
        }
        btnConfElim?.addEventListener('click', () => {
            if (!eliminarId) return;
            const fd = new FormData();
            fd.append('action','eliminar'); fd.append('id', eliminarId);
            setLoading(btnConfElim, true);
            fetch(API, {credentials:'include',method:'POST',body:fd}).then(r=>r.json()).then(data => {
                if (data.success) { cerrarModal(backdropE,modalE); eliminarId=null; cargarKpis(); }
            }).finally(() => setLoading(btnConfElim, false));
        });

        // ── CARGAR KPIS ───────────────────────────────────
        function cargarKpis() {
            fetch(API + '?action=list', {credentials:'include'}).then(r=>r.json()).then(data => {
                if (data.success) {
                    kpis = data.data;
                    renderGrid();
                    if (totalKpisEl) totalKpisEl.textContent = kpis.length;
                    // Áreas únicas con KPI
                    const areasConKpi = new Set(kpis.map(k => k.Id_Area)).size;
                    if (sinKpiEl) sinKpiEl.textContent = areasConKpi;
                }
            }).catch(() => {
                grid.innerHTML = '<p style="color:#94a3b8;padding:20px;grid-column:1/-1;text-align:center">Error al cargar.</p>';
            });
        }

        // ── RENDER ────────────────────────────────────────
        function renderGrid() {
            if (!kpis.length) {
                grid.innerHTML = '';
                if (emptyState) emptyState.style.display = 'block';
                return;
            }
            if (emptyState) emptyState.style.display = 'none';

            grid.innerHTML = kpis.map(k => {
                const icon    = ICONOS[k.Id_Area] ?? '🏢';
                const cfg     = TIPO_CONFIG[k.Tipo] || TIPO_CONFIG['Número'];
                const meta    = parseFloat(k.Metas) || 0;
                const dato    = parseFloat(k.Dato_Ingreso) || 0;
                const pct     = meta > 0 ? Math.min((dato / meta) * 100, 100) : 0;
                const metaFmt = cfg.prefix ? `$${fmtNum(meta)}` : `${fmtNum(meta)}${cfg.suffix ? ' '+cfg.suffix : ''}`;
                const datoFmt = cfg.prefix ? `$${fmtNum(dato)}` : `${fmtNum(dato)}${cfg.suffix ? ' '+cfg.suffix : ''}`;

                const pctClass = pct >= 90 ? 'pct-success' : pct >= 60 ? 'pct-warning' : 'pct-danger';

                return `
                <div class="kpi-card" data-id="${k.Id_KPs}">
                    <div class="kpi-card-header">
                        <div class="kpi-area-info">
                            <div class="kpi-area-icon">${icon}</div>
                            <div>
                                <div class="kpi-area-nombre">${esc(k.Nombre)}</div>
                                <div class="kpi-area-id">${esc(k.area_nombre)} · ${cfg.icon} ${esc(k.Tipo)}</div>
                            </div>
                        </div>
                        <div class="kpi-header-btns">
                            <button class="kpi-btn-icon editar"   data-id="${k.Id_KPs}" title="Editar">✏️</button>
                            <button class="kpi-btn-icon eliminar" data-id="${k.Id_KPs}" data-nombre="${esc(k.Nombre)}" title="Eliminar">🗑️</button>
                        </div>
                    </div>
                    <div class="kpi-card-body">
                        <div class="kpi-metrica">
                            <div class="kpi-metrica-header">
                                <span class="kpi-metrica-label">${cfg.icon} Meta</span>

                            </div>
                            <div class="kpi-metrica-valor">
                                <span class="kpi-valor-num">${metaFmt}</span>
                            </div>

                        </div>
                    </div>
                </div>`;
            }).join('');

            grid.querySelectorAll('.kpi-btn-icon.editar').forEach(btn =>
                btn.addEventListener('click', e => { e.stopPropagation(); abrirEditar(btn.dataset.id); })
            );
            grid.querySelectorAll('.kpi-btn-icon.eliminar').forEach(btn =>
                btn.addEventListener('click', e => { e.stopPropagation(); abrirEliminar(btn.dataset.id, btn.dataset.nombre); })
            );
        }

        // ── VALIDACIÓN ────────────────────────────────────
        function validar() {
            let ok = true;
            const n = inputNombre.value.trim();
            const m = inputMeta.value;
            if (!n || n.length < 2)                        { setErr(inputNombre,errNombre,'Mínimo 2 caracteres.'); ok=false; } else clearErr(inputNombre,errNombre);
            if (!selectTipo.value)                         { setErr(selectTipo,errTipo,'Selecciona un tipo.'); ok=false; } else clearErr(selectTipo,errTipo);
            if (!m || isNaN(parseFloat(m)) || parseFloat(m)<=0) { setErr(inputMeta,errMeta,'Ingresa una meta válida.'); ok=false; } else clearErr(inputMeta,errMeta);
            if (!selectArea.value)                         { setErr(selectArea,errArea,'Selecciona un área.'); ok=false; } else clearErr(selectArea,errArea);
            return ok;
        }
        function setErr(el,span,msg)   { el.classList.add('is-error');    if(span) span.textContent=msg; }
        function clearErr(el,span)     { el.classList.remove('is-error'); if(span) span.textContent=''; }
        function limpiarErrores()      { [inputNombre,selectTipo,inputMeta,selectArea].forEach((el,i) => clearErr(el,[errNombre,errTipo,errMeta,errArea][i])); }
        [inputNombre,selectTipo,inputMeta,selectArea].forEach((el,i) =>
            el?.addEventListener('change', () => clearErr(el,[errNombre,errTipo,errMeta,errArea][i]))
        );
        inputNombre?.addEventListener('input', () => clearErr(inputNombre, errNombre));
        inputMeta?.addEventListener('input',   () => clearErr(inputMeta,   errMeta));

        // ── HELPERS ───────────────────────────────────────
        function setLoading(btn, on) {
            btn.disabled = on;
            const t = btn.querySelector('.btn-text'); const s = btn.querySelector('.btn-spinner');
            if(t) t.style.display = on?'none':'inline';
            if(s) s.style.display = on?'inline':'none';
        }
        function esc(str)   { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        function fmtNum(n)  { return Number(n).toLocaleString('es-MX'); }

        cargarKpis();
    }
})();