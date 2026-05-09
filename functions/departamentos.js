/**
 * functions/departamentos.js
 * Lógica de la página Departamentos – Grupo Dalvi
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── ABRIR PRIMER ÁREA POR DEFECTO ─────────────────────
    const primero = document.querySelector('.area-bloque');
    if (primero) abrirBloque(primero);

    // ── TOGGLE COLAPSAR / EXPANDIR ────────────────────────
    window.toggleArea = function (slug) {
        const bloque = document.getElementById(slug);
        if (!bloque) return;
        if (bloque.classList.contains('open')) {
            cerrarBloque(bloque);
        } else {
            abrirBloque(bloque);
        }
    };

    function abrirBloque(bloque) {
        bloque.classList.add('open');
    }
    function cerrarBloque(bloque) {
        bloque.classList.remove('open');
    }

    // ── BUSCADOR GLOBAL ───────────────────────────────────
    const input     = document.getElementById('buscadorGlobal');
    const clearBtn  = document.getElementById('searchClear');
    const sinRes    = document.getElementById('sinResultados');
    const termino   = document.getElementById('terminoBusqueda');
    const conteo    = document.getElementById('conteoFiltrado');

    input?.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        clearBtn.classList.toggle('visible', q.length > 0);
        buscar(q);
    });

    clearBtn?.addEventListener('click', limpiarBusqueda);

    window.limpiarBusqueda = function () {
        input.value = '';
        clearBtn.classList.remove('visible');
        buscar('');
        input.focus();
    };

    function buscar(q) {
        const bloques  = document.querySelectorAll('.area-bloque');
        let totalVisible = 0;

        bloques.forEach(bloque => {
            const filas = bloque.querySelectorAll('.colab-fila');
            const areaNombre = bloque.dataset.area || '';
            let filasVisibles = 0;

            if (!q) {
                // Sin búsqueda: restaurar todo
                filas.forEach(f => {
                    f.style.display = '';
                    quitarHighlight(f);
                });
                bloque.style.display = '';
                totalVisible += filas.length;
                return;
            }

            filas.forEach(fila => {
                const nombre = fila.dataset.nombre || '';
                const cargo  = fila.dataset.cargo  || '';
                const match  = nombre.includes(q) || cargo.includes(q);

                fila.style.display = match ? '' : 'none';

                if (match) {
                    aplicarHighlight(fila, q);
                    filasVisibles++;
                    totalVisible++;
                } else {
                    quitarHighlight(fila);
                }
            });

            // Mostrar el bloque si hay coincidencias en colaboradores
            // o si el nombre del área coincide
            const areaMatch = areaNombre.includes(q);
            if (filasVisibles > 0 || areaMatch) {
                bloque.style.display = '';
                abrirBloque(bloque);
                // Si el área coincide, mostrar todas sus filas
                if (areaMatch && filasVisibles === 0) {
                    filas.forEach(f => {
                        f.style.display = '';
                        totalVisible++;
                    });
                }
            } else {
                bloque.style.display = 'none';
            }
        });

        // Actualizar contador
        if (conteo) conteo.textContent = totalVisible;

        // Mostrar/ocultar mensaje sin resultados
        if (q && totalVisible === 0) {
            sinRes.style.display = 'block';
            if (termino) termino.textContent = `"${input.value}"`;
        } else {
            sinRes.style.display = 'none';
        }
    }

    // ── HIGHLIGHT ─────────────────────────────────────────
    function aplicarHighlight(fila, q) {
        const nombreEl = fila.querySelector('.colab-nombre-txt');
        const cargoEl  = fila.querySelector('.cargo-tag');

        if (nombreEl) {
            nombreEl.innerHTML = resaltar(nombreEl.textContent, q);
        }
        if (cargoEl) {
            cargoEl.innerHTML = resaltar(cargoEl.textContent, q);
        }
    }

    function quitarHighlight(fila) {
        const nombreEl = fila.querySelector('.colab-nombre-txt');
        const cargoEl  = fila.querySelector('.cargo-tag');
        if (nombreEl) nombreEl.textContent = nombreEl.textContent; // limpia HTML
        if (cargoEl)  cargoEl.textContent  = cargoEl.textContent;
    }

    function resaltar(texto, q) {
        if (!q) return escHtml(texto);
        const re = new RegExp(`(${escapeRe(q)})`, 'gi');
        return escHtml(texto).replace(re, '<span class="highlight">$1</span>');
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function escapeRe(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // ── ATAJO: CTRL+F abre el buscador ───────────────────
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            if (document.activeElement !== input) {
                e.preventDefault();
                input?.focus();
            }
        }
    });

});