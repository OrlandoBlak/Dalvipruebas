/**
 * functions/evaluacion.js
 * Lógica completa de evaluación de colaboradores – Grupo Dalvi
 */

(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {

        // ── ESTADO ────────────────────────────────────────
        let colaboradores     = [];
        let criterios         = [];
        let criteriosSel      = new Set();
        let sliderValues      = {};
        let colabActual       = null;
        let kpiActual         = [];
        let kpisSeleccionados = new Set();

        // ── ELEMENTOS ─────────────────────────────────────
        const buscarColab    = document.getElementById('buscarColab');
        const selectColab    = document.getElementById('selectColab');
        const colabCard      = document.getElementById('colabCard');
        const colabAvatar    = document.getElementById('colabAvatar');
        const colabNombre    = document.getElementById('colabNombre');
        const colabCargo     = document.getElementById('colabCargo');
        const colabAreaEl    = document.getElementById('colabArea');
        const scoreNum       = document.getElementById('scoreNum');
        const scoreStars     = document.getElementById('scoreStars');
        const criteriosTags  = document.getElementById('criteriosTags');
        const btnTodos       = document.getElementById('btnTodos');
        const btnNinguno     = document.getElementById('btnNinguno');
        const slidersLista   = document.getElementById('slidersLista');
        const sliderResumen  = document.getElementById('sliderResumen');
        const kpsInfo        = document.getElementById('kpsInfo');
        const nivelDeseado   = document.getElementById('nivelDeseadoLista');
        const humanScore     = document.getElementById('humanScore');
        const nivelPct       = document.getElementById('nivelPct');
        const nivelBarraFill = document.getElementById('nivelBarraFill');
        const nivelBadge     = document.getElementById('nivelBadge');
        const nivelDesc      = document.getElementById('nivelDesc');
        const btnGuardar     = document.getElementById('btnGuardarEval');
        const alertSuccess   = document.getElementById('evalAlertSuccess');
        const alertError     = document.getElementById('evalAlertError');
        const alertMsg       = document.getElementById('alertMsg');
        const alertErrMsg    = document.getElementById('alertErrMsg');

        // ── CARGAR DATOS INICIALES ────────────────────────
        const API      = '../../php/api_evaluacion.php';
        const API_KPIS = '../../php/api_eval_kpis.php';

        Promise.all([
            fetch(API + '?action=colaboradores', {credentials:'include'}).then(r => r.json()),
            fetch(API + '?action=criterios', {credentials:'include'}).then(r => r.json()),
        ]).then(([resColab, resCrit]) => {
            if (resColab.success) {
                colaboradores = resColab.data;
                renderSelectColab(colaboradores);
            } else {
                selectColab.innerHTML = `<option>Error: ${resColab.error || 'No se pudo cargar'}</option>`;
                console.error('Error colaboradores:', resColab);
            }
            if (resCrit.success) {
                criterios = resCrit.data;
                renderCriteriosTags();
            } else {
                criteriosTags.innerHTML = `<span style="color:#ef4444;font-size:12px">Error criterios: ${resCrit.error || 'Sin datos'}</span>`;
                console.error('Error criterios:', resCrit);
            }
        }).catch(err => {
            selectColab.innerHTML = '<option>❌ Error de conexión — verifica la ruta del API</option>';
            if (criteriosTags) criteriosTags.innerHTML = '<span style="color:#ef4444;font-size:12px">❌ Error de conexión al servidor</span>';
            console.error('Fetch error:', err);
        });

        // ── RENDER SELECT COLABORADORES ───────────────────
        function renderSelectColab(lista) {
            selectColab.innerHTML = '<option value="">— Selecciona un colaborador —</option>';
            lista.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.Id_Colaborador;
                opt.textContent = `${c.Nombre} · ${c.area_nombre || '—'}`;
                opt.dataset.json = JSON.stringify(c);
                selectColab.appendChild(opt);
            });
        }

        // Filtro de búsqueda
        buscarColab?.addEventListener('input', () => {
            const q = buscarColab.value.toLowerCase().trim();
            const filtrados = q
                ? colaboradores.filter(c =>
                    c.Nombre.toLowerCase().includes(q) ||
                    (c.area_nombre || '').toLowerCase().includes(q) ||
                    (c.Cargo || '').toLowerCase().includes(q)
                  )
                : colaboradores;
            renderSelectColab(filtrados);
        });

        // Selección de colaborador
        selectColab?.addEventListener('change', () => {
            const opt = selectColab.options[selectColab.selectedIndex];
            if (!opt || !opt.dataset.json) { colabCard.style.display = 'none'; colabActual = null; return; }
            colabActual = JSON.parse(opt.dataset.json);
            mostrarColabCard(colabActual);
            // Resetear KPIs al cambiar colaborador
            kpiActual = [];
            kpisSeleccionados = new Set();
            window._lastIdResult = null;
            // Id_Area puede venir como número o string — asegurar que sea entero
            const idAreaColab = parseInt(colabActual.Id_Area) || 0;
            cargarKps(idAreaColab);
        });

        function mostrarColabCard(c) {
            const inicial = c.Nombre ? c.Nombre.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase() : '?';
            colabAvatar.textContent = inicial;
            colabNombre.textContent = c.Nombre || '—';
            colabCargo.textContent  = c.Cargo  || 'Sin cargo';
            colabAreaEl.textContent = c.area_nombre || '—';
            colabCard.style.display = 'flex';
            actualizarScore();
        }

        // ── RENDER CRITERIOS TAGS ─────────────────────────
        function renderCriteriosTags() {
            criteriosTags.innerHTML = '';
            criterios.forEach(c => {
                const tag = document.createElement('span');
                tag.className   = 'criterio-tag selected';
                tag.textContent = c.Nombre_Criterio;
                tag.dataset.id  = c.Id_Criterios;
                tag.dataset.nombre = c.Nombre_Criterio;
                tag.dataset.evaluando = c.Evaluando;
                tag.addEventListener('click', () => toggleCriterio(tag, c));
                criteriosTags.appendChild(tag);
                criteriosSel.add(c.Id_Criterios);
            });
            renderSliders();
        }

        function toggleCriterio(tag, c) {
            if (criteriosSel.has(c.Id_Criterios)) {
                criteriosSel.delete(c.Id_Criterios);
                tag.classList.remove('selected');
            } else {
                criteriosSel.add(c.Id_Criterios);
                tag.classList.add('selected');
            }
            renderSliders();
        }

        btnTodos?.addEventListener('click', () => {
            criterios.forEach(c => criteriosSel.add(c.Id_Criterios));
            criteriosTags.querySelectorAll('.criterio-tag').forEach(t => t.classList.add('selected'));
            renderSliders();
        });
        btnNinguno?.addEventListener('click', () => {
            criteriosSel.clear();
            criteriosTags.querySelectorAll('.criterio-tag').forEach(t => t.classList.remove('selected'));
            renderSliders();
        });

        // ── RENDER SLIDERS ────────────────────────────────
        function renderSliders() {
            const seleccionados = criterios.filter(c => criteriosSel.has(c.Id_Criterios));
            const total = seleccionados.length;
            const pondTotal = seleccionados.reduce((s, c) => s + parseFloat(c.Evaluando || 0), 0);

            if (sliderResumen) {
                sliderResumen.textContent = `${total}/${criterios.length} criterios · Pond. total: ${pondTotal.toFixed(1)}`;
            }

            if (!total) {
                slidersLista.innerHTML = '<p class="eval-hint">Selecciona criterios arriba para evaluarlos aquí.</p>';
                renderNivelDeseado([]);
                actualizarScore();
                return;
            }

            slidersLista.innerHTML = seleccionados.map(c => {
                const val    = sliderValues[c.Nombre_Criterio] ?? 0;
                const maxVal = parseFloat(c.Evaluando) || 10;
                const isStars = maxVal <= 5;
                const pct    = maxVal > 0 ? Math.round((val / maxVal) * 100) : 0;
                const nivelClass = getNivelClass(pct);
                const nivelTxt   = getNivelTxt(pct);

                return `
                <div class="slider-item" data-criterio="${esc(c.Nombre_Criterio)}">
                    <div class="slider-header">
                        <span class="slider-nombre">${esc(c.Nombre_Criterio)}</span>
                        <div class="slider-badges">
                            <span class="slider-nivel-badge ${nivelClass}" id="nivel-${c.Id_Criterios}">${nivelTxt}</span>
                            <span class="slider-pct-badge" id="pct-${c.Id_Criterios}">${pct}%</span>
                            <span class="slider-valor" id="val-${c.Id_Criterios}">${val}/${maxVal}${isStars ? ' ⭐' : ''}</span>
                        </div>
                    </div>
                    <div class="slider-wrap">
                        <input type="range"
                            class="eval-slider${isStars ? ' eval-slider-stars' : ''}"
                            min="0" max="${maxVal}" step="${isStars ? 0.5 : 0.5}"
                            value="${val}"
                            data-id="${c.Id_Criterios}"
                            data-nombre="${esc(c.Nombre_Criterio)}"
                            data-max="${maxVal}"
                            id="slider-${c.Id_Criterios}">
                    </div>
                </div>`;
            }).join('');

            // Eventos sliders
            slidersLista.querySelectorAll('.eval-slider').forEach(slider => {
                slider.addEventListener('input', () => {
                    const id     = slider.dataset.id;
                    const nombre = slider.dataset.nombre;
                    const max    = parseFloat(slider.dataset.max);
                    const val    = parseFloat(slider.value);
                    sliderValues[nombre] = val;

                    const pct  = max > 0 ? Math.round((val / max) * 100) : 0;
                    const nCls = getNivelClass(pct);
                    const nTxt = getNivelTxt(pct);
                    const isS  = max <= 5;

                    const nEl  = document.getElementById(`nivel-${id}`);
                    const pEl  = document.getElementById(`pct-${id}`);
                    const vEl  = document.getElementById(`val-${id}`);
                    if (nEl) { nEl.className = `slider-nivel-badge ${nCls}`; nEl.textContent = nTxt; }
                    if (pEl) pEl.textContent = `${pct}%`;
                    if (vEl) vEl.textContent = `${val}/${max}${isS ? ' ⭐' : ''}`;

                    actualizarScore();
                });
            });

            renderNivelDeseado(seleccionados);
            actualizarScore();
        }

        // ── NIVEL DESEADO ─────────────────────────────────
        function renderNivelDeseado(lista) {
            if (!nivelDeseado) return;
            if (!lista.length) {
                nivelDeseado.innerHTML = '<p class="eval-hint">Selecciona criterios para ver los niveles deseados.</p>';
                return;
            }
            nivelDeseado.innerHTML = lista.map(c => {
                const max     = parseFloat(c.Evaluando) || 10;
                const deseado = max; // nivel deseado = máximo del criterio
                const pct     = 100;
                return `
                <div class="nivel-deseado-item">
                    <span class="nd-nombre">${esc(c.Nombre_Criterio)}</span>
                    <div class="nd-barra-wrap">
                        <div class="nd-barra">
                            <div class="nd-barra-fill" style="width:${pct}%"></div>
                        </div>
                    </div>
                    <span class="nd-valor">${deseado}/${max}</span>
                </div>`;
            }).join('');
        }

        // ── ACTUALIZAR SCORE Y NIVEL ──────────────────────
        function actualizarScore() {
            const seleccionados = criterios.filter(c => criteriosSel.has(c.Id_Criterios));
            if (!seleccionados.length) {
                setScore(0, 0); return;
            }

            const vals = seleccionados.map(c => {
                const max = parseFloat(c.Evaluando) || 10;
                const val = sliderValues[c.Nombre_Criterio] ?? 0;
                return (val / max) * 10; // normalizado a /10
            });
            const promedio = vals.reduce((s, v) => s + v, 0) / vals.length;
            const pct      = (promedio / 10) * 100;
            setScore(promedio, pct);
            actualizarRadar(seleccionados);
        }

        function setScore(promedio, pct) {
            const prom = promedio.toFixed(1);

            // Tarjeta colaborador
            if (scoreNum)  scoreNum.textContent  = prom;
            if (scoreStars) scoreStars.textContent = generarEstrellas(promedio);

            // Figura humana
            if (humanScore) humanScore.innerHTML = `${prom}<span>/10</span>`;

            // Panel nivel
            if (nivelPct)       nivelPct.textContent = `${pct.toFixed(0)}%`;
            if (nivelBarraFill) nivelBarraFill.style.width = `${Math.min(pct, 100)}%`;

            const { clase, texto, desc } = getNivelInfo(pct);
            if (nivelBadge) { nivelBadge.textContent = texto; nivelBadge.className = `nivel-badge ${clase}`; }
            if (nivelDesc)  nivelDesc.textContent = desc;

            // Figura humana: cambiar color según nivel
            const color = clase === 'excepcional' ? '#10b981'
                        : clase === 'encamino'    ? '#2563eb'
                        : clase === 'endesarrollo'? '#f59e0b'
                        : clase === 'requiere'    ? '#ef4444'
                        : '#ef4444';
            document.querySelectorAll('.humano-cabeza,.humano-brazo,.humano-torso,.humano-pierna').forEach(el => {
                el.style.borderColor     = color;
                el.style.backgroundColor = color + '15';
            });
            if (humanScore) humanScore.style.color = color;
        }

        function getNivelInfo(pct) {
            if (pct >= 90) return { clase: 'excepcional', texto: 'Excepcional',       desc: '90–100% · Desempeño sobresaliente' };
            if (pct >= 75) return { clase: 'encamino',    texto: 'En camino',         desc: '75–89% · Buen desempeño general' };
            if (pct >= 60) return { clase: 'endesarrollo',texto: 'En desarrollo',     desc: '60–74% · Necesita refuerzo' };
            return              { clase: 'requiere',    texto: 'Requiere atención', desc: '0–59% · Plan de mejora urgente' };
        }

        function getNivelClass(pct) {
            if (pct >= 90) return 'nivel-normal';
            if (pct >= 75) return 'nivel-bajo';
            if (pct >= 60) return 'nivel-alto';
            return 'nivel-critico';
        }
        function getNivelTxt(pct) {
            if (pct >= 90) return 'Excepcional';
            if (pct >= 75) return 'En camino';
            if (pct >= 60) return 'En desarrollo';
            if (pct >= 40) return 'Alto';
            return 'Crítico';
        }

        // ── RADAR CHART (Canvas nativo) ───────────────────
        function actualizarRadar(lista) {
            const canvas = document.getElementById('radarChart');
            if (!canvas || !lista.length) return;
            const ctx = canvas.getContext('2d');
            const W = canvas.width, H = canvas.height;
            const cx = W/2, cy = H/2, r = Math.min(W,H)/2 - 28;
            const n = lista.length;

            ctx.clearRect(0, 0, W, H);

            // Fondo radial
            const niveles = 5;
            for (let lv = 1; lv <= niveles; lv++) {
                const rLv = (r * lv) / niveles;
                ctx.beginPath();
                for (let i = 0; i < n; i++) {
                    const ang = (Math.PI * 2 * i / n) - Math.PI/2;
                    const x = cx + rLv * Math.cos(ang);
                    const y = cy + rLv * Math.sin(ang);
                    i === 0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
                }
                ctx.closePath();
                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 1;
                ctx.stroke();
                ctx.fillStyle = lv % 2 === 0 ? 'rgba(241,245,249,.5)' : 'transparent';
                ctx.fill();
            }

            // Ejes
            lista.forEach((_, i) => {
                const ang = (Math.PI * 2 * i / n) - Math.PI/2;
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.lineTo(cx + r * Math.cos(ang), cy + r * Math.sin(ang));
                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 1;
                ctx.stroke();
            });

            // Polígono DESEADO (100%)
            ctx.beginPath();
            lista.forEach((_, i) => {
                const ang = (Math.PI * 2 * i / n) - Math.PI/2;
                const x = cx + r * Math.cos(ang);
                const y = cy + r * Math.sin(ang);
                i === 0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
            });
            ctx.closePath();
            ctx.strokeStyle = 'rgba(239,68,68,.5)';
            ctx.lineWidth = 1.5;
            ctx.stroke();
            ctx.fillStyle = 'rgba(239,68,68,.05)';
            ctx.fill();

            // Polígono ACTUAL
            ctx.beginPath();
            lista.forEach((c, i) => {
                const max   = parseFloat(c.Evaluando) || 10;
                const val   = sliderValues[c.Nombre_Criterio] ?? 0;
                const ratio = max > 0 ? val / max : 0;
                const ang   = (Math.PI * 2 * i / n) - Math.PI/2;
                const x     = cx + r * ratio * Math.cos(ang);
                const y     = cy + r * ratio * Math.sin(ang);
                i === 0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
            });
            ctx.closePath();
            ctx.strokeStyle = '#2563eb';
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.fillStyle = 'rgba(37,99,235,.15)';
            ctx.fill();

            // Labels
            ctx.font = '9px Sora, sans-serif';
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            lista.forEach((c, i) => {
                const ang = (Math.PI * 2 * i / n) - Math.PI/2;
                const rL  = r + 18;
                const x   = cx + rL * Math.cos(ang);
                const y   = cy + rL * Math.sin(ang);
                const nombre = c.Nombre_Criterio.length > 10 ? c.Nombre_Criterio.slice(0,9) + '…' : c.Nombre_Criterio;
                ctx.fillText(nombre, x, y);
            });

            // Puntos ACTUAL
            lista.forEach((c, i) => {
                const max   = parseFloat(c.Evaluando) || 10;
                const val   = sliderValues[c.Nombre_Criterio] ?? 0;
                const ratio = max > 0 ? val / max : 0;
                const ang   = (Math.PI * 2 * i / n) - Math.PI/2;
                const x     = cx + r * ratio * Math.cos(ang);
                const y     = cy + r * ratio * Math.sin(ang);
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI*2);
                ctx.fillStyle = '#2563eb';
                ctx.fill();
            });
        }

        // ── KPS: CARGAR Y MOSTRAR RESUMEN ────────────────
        const TIPO_CFG = {
            'Dinero (MXN)':   { icon:'💰', prefix:'$', suffix:''     },
            'Número':         { icon:'🔢', prefix:'',  suffix:''     },
            'Porcentaje (%)': { icon:'📊', prefix:'',  suffix:'%'    },
            'Unidades':       { icon:'📦', prefix:'',  suffix:' uds' },
        };

        const btnAsignarKpi      = document.getElementById('btnAsignarKpi');
        const modalBackdropKpi   = document.getElementById('modalBackdropKpi');
        const modalKpiEval       = document.getElementById('modalKpiEval');
        const modalKpiBody       = document.getElementById('modalKpiBody');
        const modalKpiAreaNombre = document.getElementById('modalKpiAreaNombre');
        const btnCancelarKpi     = document.getElementById('btnCancelarKpi');
        const btnGuardarKpiModal = document.getElementById('btnGuardarKpiModal');
        const modalKpiClose      = document.getElementById('modalKpiClose');

        function abrirModalKpi()  {
            modalBackdropKpi?.classList.add('active');
            modalKpiEval?.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function cerrarModalKpi() {
            modalBackdropKpi?.classList.remove('active');
            modalKpiEval?.classList.remove('active');
            document.body.style.overflow = '';
        }

        [btnCancelarKpi, modalKpiClose, modalBackdropKpi].forEach(el =>
            el?.addEventListener('click', cerrarModalKpi)
        );

        // Abrir modal con los KPIs del área
        btnAsignarKpi?.addEventListener('click', () => {
            if (!kpiActual || !kpiActual.length) return;
            if (modalKpiAreaNombre) modalKpiAreaNombre.textContent = colabActual?.area_nombre || 'Área';
            renderModalKpis();
            abrirModalKpi();
        });

        function cargarKps(idArea) {
            if (!idArea) {
                kpsInfo.innerHTML = '<p class="eval-hint">Sin área asignada.</p>';
                if (btnAsignarKpi) btnAsignarKpi.style.display = 'none';
                kpiActual = null;
                return;
            }
            kpsInfo.innerHTML = '<p class="eval-hint">Cargando KPIs...</p>';

            fetch(`${API_KPIS}?action=kps_area&id_area=${idArea}`, {credentials:'include'})
                .then(r => r.json())
                .then(data => {
                    kpiActual = data.data;

                    if (!kpiActual || !kpiActual.length) {
                        if (btnAsignarKpi) btnAsignarKpi.style.display = 'none';
                        kpsInfo.innerHTML = `
                            <div style="display:flex;align-items:center;gap:12px;padding:8px">
                                <span style="font-size:24px">📭</span>
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--text-primary)">Sin KPIs asignados</div>
                                    <a href="kpis.php" style="font-size:12px;color:var(--accent);font-weight:600">
                                        Ir a configurar KPIs →
                                    </a>
                                </div>
                            </div>`;
                        return;
                    }

                    // Mostrar botón y resumen
                    if (btnAsignarKpi) btnAsignarKpi.style.display = 'inline-flex';
                    renderResumenKpis();
                })
                .catch(() => {
                    kpsInfo.innerHTML = '<p class="eval-hint" style="color:#ef4444">Error al cargar KPIs.</p>';
                });
        }

        // Resumen compacto en la tarjeta (solo lectura)
        function renderResumenKpis() {
            if (!kpiActual || !kpiActual.length) return;
            kpsInfo.innerHTML = `
                <div class="kpi-resumen-lista">
                    ${kpiActual.map(k => {
                        const cfg  = TIPO_CFG[k.Tipo] || TIPO_CFG['Número'];
                        const meta = parseFloat(k.Metas) || 0;
                        const dato = parseFloat(k.Dato_Ingreso) || 0;
                        const pct  = meta > 0 ? Math.round((dato / meta) * 100) : 0;
                        const fmtMeta = cfg.prefix + Number(meta).toLocaleString('es-MX') + cfg.suffix;
                        const fmtDato = cfg.prefix + Number(dato).toLocaleString('es-MX') + cfg.suffix;
                        return `
                        <div class="kpi-resumen-item">
                            <div class="kpi-resumen-header">
                                <span class="kpi-resumen-nombre">${cfg.icon} ${esc(k.Nombre)}</span>
                                <span class="kpi-resumen-pct ${pct>=90?'pct-success':pct>=60?'pct-warning':'pct-danger'}">${pct}%</span>
                            </div>
                            <div class="kpi-resumen-barra">
                                <div class="kpi-resumen-barra-fill ${pct>=90?'success':pct>=60?'warning':''}"
                                     style="width:${pct}%"></div>
                            </div>
                            <div class="kpi-resumen-valores">
                                <span>${fmtDato} ingresado</span>
                                <span>Meta: ${fmtMeta}</span>
                            </div>
                        </div>`;
                    }).join('')}
                </div>`;
        }

        // Render del modal con sliders
        function renderModalKpis() {
            if (!modalKpiBody || !kpiActual) return;

            if (!kpiActual.length) {
                modalKpiBody.innerHTML = '<p style="color:#94a3b8;padding:10px">No hay KPIs disponibles.</p>';
                return;
            }

            modalKpiBody.innerHTML = `<div class="kpi-modal-lista">
                ${kpiActual.map(k => {
                    const cfg     = TIPO_CFG[k.Tipo] || TIPO_CFG['Número'];
                    const meta    = parseFloat(k.Metas) || 0;
                    const dato    = parseFloat(k.Dato_Ingreso) || 0;
                    const pct     = meta > 0 ? Math.round((dato / meta) * 100) : 0;
                    const checked = kpisSeleccionados.has(k.Id_KPs) ? 'checked' : '';
                    const step    = k.Tipo === 'Dinero (MXN)' ? (meta > 10000 ? 100 : 10) : 1;
                    const fmtMeta = cfg.prefix + Number(meta).toLocaleString('es-MX') + cfg.suffix;

                    return `
                    <div class="kpi-modal-row" id="kpiRow-${k.Id_KPs}">
                        <label class="kpi-modal-check-label">
                            <input type="checkbox" class="kpi-chk" data-id="${k.Id_KPs}" ${checked}>
                            <span class="kpi-chk-custom"></span>
                            <div class="kpi-modal-info">
                                <span class="kpi-modal-nombre">${cfg.icon} ${esc(k.Nombre)}</span>
                                <span class="kpi-modal-tipo">${esc(k.Tipo||'—')} · Meta: <strong>${fmtMeta}</strong></span>
                            </div>
                        </label>
                        <div class="kpi-modal-dato-wrap ${checked ? '' : 'hidden'}" id="kpiDato-${k.Id_KPs}">
                            <div class="kpi-modal-input-row">
                                <label class="kpi-modal-label">DATO INGRESADO:</label>
                                <div class="kpi-modal-input-wrap">
                                    ${cfg.prefix ? `<span class="kpi-modal-prefix">${cfg.prefix}</span>` : ''}
                                    <input type="number" class="kpi-modal-input"
                                        id="modalInputKpi-${k.Id_KPs}"
                                        min="0" max="${meta}" step="${step}" value="${dato}" placeholder="0">
                                    ${cfg.suffix.trim() ? `<span class="kpi-modal-suffix">${cfg.suffix.trim()}</span>` : ''}
                                    <span class="kpi-modal-max">/ ${fmtMeta}</span>
                                </div>
                            </div>
                            <div class="kpi-modal-slider-wrap">
                                <input type="range" class="eval-slider"
                                    id="modalSliderKpi-${k.Id_KPs}"
                                    min="0" max="${meta}" step="${step}" value="${dato}">
                                <div class="kpi-modal-barra-row">
                                    <div class="kpi-barra-eval">
                                        <div class="kpi-barra-eval-fill ${pct>=90?'success':pct>=60?'warning':''}"
                                             id="modalBarKpi-${k.Id_KPs}" style="width:${pct}%"></div>
                                    </div>
                                    <span class="kpi-modal-pct" id="modalPctKpi-${k.Id_KPs}">${pct}%</span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                }).join('')}
            </div>`;

            // Eventos checkbox — mostrar/ocultar sección de dato
            modalKpiBody.querySelectorAll('.kpi-chk').forEach(chk => {
                const id    = parseInt(chk.dataset.id);
                const datoW = document.getElementById(`kpiDato-${id}`);
                chk.addEventListener('change', () => {
                    if (chk.checked) {
                        kpisSeleccionados.add(id);
                        datoW?.classList.remove('hidden');
                    } else {
                        kpisSeleccionados.delete(id);
                        datoW?.classList.add('hidden');
                    }
                });
            });

            // Vincular slider ↔ input
            kpiActual.forEach(k => {
                const meta   = parseFloat(k.Metas) || 0;
                const slider = document.getElementById(`modalSliderKpi-${k.Id_KPs}`);
                const input  = document.getElementById(`modalInputKpi-${k.Id_KPs}`);
                const bar    = document.getElementById(`modalBarKpi-${k.Id_KPs}`);
                const pctEl  = document.getElementById(`modalPctKpi-${k.Id_KPs}`);

                const sync = (val) => {
                    const v   = Math.min(Math.max(0, parseFloat(val) || 0), meta);
                    const pct = meta > 0 ? Math.round((v / meta) * 100) : 0;
                    if (slider) slider.value = v;
                    if (input)  input.value  = v;
                    if (bar)    { bar.style.width = pct + '%'; bar.className = `kpi-barra-eval-fill${pct>=90?' success':pct>=60?' warning':''}`; }
                    if (pctEl)  pctEl.textContent = pct + '%';
                };

                slider?.addEventListener('input', () => sync(slider.value));
                input?.addEventListener('input',  () => sync(input.value));
            });
        }

        // Guardar KPIs desde el modal
        btnGuardarKpiModal?.addEventListener('click', async () => {
            if (!kpiActual || !kpiActual.length) return;

            const btn = btnGuardarKpiModal;
            btn.disabled = true;
            btn.querySelector('.btn-text').style.display   = 'none';
            btn.querySelector('.btn-spinner').style.display = 'inline';

            try {
                // Guardar resultado en tabla resultados (nueva estructura)
                const promesas = kpiActual
                    .filter(k => kpisSeleccionados.has(k.Id_KPs))
                    .map(k => {
                        const input = document.getElementById(`modalInputKpi-${k.Id_KPs}`);
                        const dato  = Math.min(parseFloat(input?.value||0), parseFloat(k.Metas)||0);
                        k.Dato_Ingreso = dato;
                        const fd = new FormData();
                        fd.append('action',  'guardar_resultado');
                        fd.append('id_kps',  k.Id_KPs);
                        fd.append('dato',    dato);
                        if (k.Id_Result) fd.append('id_result', k.Id_Result);
                        return fetch(API_KPIS, {method:'POST', body:fd, credentials:'include'})
                               .then(r => r.json())
                               .then(res => { if (res.success && res.id_result) k.Id_Result = res.id_result; return res; });
                    });

                const results = await Promise.all(promesas);
                // Guardar Id_Result del primer KPI seleccionado en variable global
                const primerRes = results.find(r => r && r.id_result);
                if (primerRes) window._lastIdResult = primerRes.id_result;

                cerrarModalKpi();
                renderResumenKpis();
            } catch(err) {
                console.error('Error guardando KPIs:', err);
            } finally {
                btn.disabled = false;
                btn.querySelector('.btn-text').style.display   = 'inline';
                btn.querySelector('.btn-spinner').style.display = 'none';
            }
        });

                // ── INSIGNIAS ─────────────────────────────────────
        document.querySelectorAll('.insignia-item').forEach(item => {
            item.addEventListener('click', () => {
                const check = item.querySelector('.insignia-check');
                const custom = item.querySelector('.insignia-custom-check');
                check.checked = !check.checked;
                item.classList.toggle('checked', check.checked);
                custom.textContent = check.checked ? '●' : '○';
            });
        });

        function obtenerInsignias() {
            // Returns array of {id, nombre} for checked insignias
            const checked = document.querySelectorAll('.insignia-check:checked');
            return Array.from(checked).map(ch => ({
                id:     ch.value,
                nombre: ch.dataset.nombre || ch.value
            }));
        }
        function obtenerInsigniasIds() {
            return obtenerInsignias().map(i => i.id).join(',');
        }
        function obtenerInsigniasNombres() {
            return obtenerInsignias().map(i => i.nombre).join(',');
        }

        // ── GUARDAR EVALUACIÓN ────────────────────────────
        btnGuardar?.addEventListener('click', async () => {
            alertSuccess.style.display = 'none';
            alertError.style.display   = 'none';

            if (!colabActual) {
                mostrarError('Selecciona un colaborador antes de guardar.');
                return;
            }
            if (!criteriosSel.size) {
                mostrarError('Selecciona al menos un criterio de evaluación.');
                return;
            }

            // Armar datos
            const seleccionados = criterios.filter(c => criteriosSel.has(c.Id_Criterios));
            const criteriosTxt  = seleccionados.map(c => c.Nombre_Criterio).join(',');

            // Slider principal = promedio normalizado a /10
            const vals    = seleccionados.map(c => {
                const max = parseFloat(c.Evaluando) || 10;
                const val = sliderValues[c.Nombre_Criterio] ?? 0;
                return (val / max) * 10;
            });
            const promedio = vals.length ? vals.reduce((s,v) => s+v, 0) / vals.length : 0;

            // Sliders JSON
            const slidersJSON = JSON.stringify(
                Object.fromEntries(seleccionados.map(c => [
                    c.Nombre_Criterio,
                    ((sliderValues[c.Nombre_Criterio] ?? 0) / (parseFloat(c.Evaluando)||10)) * 10
                ]))
            );

            // Obtener Id_Result del primer KPI guardado
            const idResult = window._lastIdResult || (kpiActual?.[0]?.Id_Result) || '';

            const fd = new FormData();
            fd.append('action',            'guardar');
            fd.append('id_colaborador',    colabActual.Id_Colaborador);
            fd.append('criterios',         criteriosTxt);
            fd.append('evaluacion',        promedio.toFixed(2));
            fd.append('id_result',         idResult);
            fd.append('insignias_ids',     obtenerInsigniasIds());
            fd.append('insignias_nombres', obtenerInsigniasNombres());
            fd.append('sliders',        slidersJSON);
            fd.append('observacion',    document.getElementById('txtObservacion').value);
            fd.append('puntos',         document.getElementById('txtPuntos').value);
            fd.append('pendientes',     document.getElementById('txtPendientes').value);
            fd.append('comentarios',    document.getElementById('txtComentarios').value);

            setLoading(btnGuardar, true);
            try {
                const res  = await fetch(API, {credentials:'include', method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alertMsg.textContent = `Evaluación #${data.id_evaluacion} guardada — Nivel: ${data.porcentaje}% — ${getNivelInfo(data.porcentaje).texto}`;
                    alertSuccess.style.display = 'flex';
                    alertSuccess.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Redirigir al menú general después de 2 segundos
                    setTimeout(() => {
                        const esUsuario = window.location.pathname.includes('/usuarios/');
                        window.location.href = esUsuario ? 'homeuser.php' : 'homeadmin.php';
                    }, 2000);
                } else {
                    mostrarError(data.error || 'Error al guardar.');
                }
            } catch {
                mostrarError('Error de conexión. Intenta de nuevo.');
            } finally {
                setLoading(btnGuardar, false);
            }
        });

        function mostrarError(msg) {
            alertErrMsg.textContent    = msg;
            alertError.style.display   = 'flex';
            alertError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // ── HELPERS ───────────────────────────────────────
        function generarEstrellas(val, max = 5) {
            const llenas = Math.round((val / 10) * max);
            let s = '';
            for (let i = 1; i <= max; i++) s += i <= llenas ? '★' : '☆';
            return s;
        }
        function setLoading(btn, on) {
            btn.disabled = on;
            const t = btn.querySelector('.btn-text');
            const s = btn.querySelector('.btn-spinner');
            if (t) t.style.display = on ? 'none' : 'inline';
            if (s) s.style.display = on ? 'inline' : 'none';
        }
        function esc(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    }

})();