/**
 * functions/dashboard.js
 * Dashboard Ejecutivo – Grupo Dalvi
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── ABRIR PRIMER ÁREA ─────────────────────────
    const primero = document.querySelector('.dash-area-bloque');
    if (primero) primero.classList.add('open');

    // ── TOGGLE ACORDEÓN ───────────────────────────
    window.toggleDashArea = function (header) {
        const bloque = header.closest('.dash-area-bloque');
        if (bloque) bloque.classList.toggle('open');
    };

    // ── BUSCADOR ──────────────────────────────────
    const input    = document.getElementById('buscarEvaluado');
    const clearBtn = document.getElementById('searchClear');
    const noRes    = document.getElementById('dashNoResults');
    const termEl   = document.getElementById('dashTermino');

    input?.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        clearBtn?.classList.toggle('visible', q.length > 0);
        filtrar(q);
    });

    clearBtn?.addEventListener('click', () => {
        input.value = '';
        clearBtn.classList.remove('visible');
        filtrar('');
        input.focus();
    });

    input?.addEventListener('keydown', e => {
        if (e.key === 'Escape') { input.value=''; clearBtn?.classList.remove('visible'); filtrar(''); }
    });

    function filtrar(q) {
        const bloques = document.querySelectorAll('.dash-area-bloque');
        let totalVisible = 0;

        bloques.forEach(bloque => {
            const filas = bloque.querySelectorAll('.dash-colab-row');
            const areaNombre = bloque.dataset.area || '';
            let visibles = 0;

            if (!q) {
                filas.forEach(f => f.style.display = '');
                bloque.style.display = '';
                totalVisible += filas.length;
                return;
            }

            filas.forEach(fila => {
                const nombre = fila.dataset.nombre || '';
                const match  = nombre.includes(q) || areaNombre.includes(q);
                fila.style.display = match ? '' : 'none';
                if (match) { visibles++; totalVisible++; }
            });

            if (visibles > 0 || areaNombre.includes(q)) {
                bloque.style.display = '';
                bloque.classList.add('open');
            } else {
                bloque.style.display = 'none';
            }
        });

        if (noRes) {
            noRes.style.display = (q && totalVisible === 0) ? 'block' : 'none';
            if (termEl) termEl.textContent = `"${input.value}"`;
        }
    }

});