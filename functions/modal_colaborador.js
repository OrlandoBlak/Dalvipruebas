/**
 * functions/modal_colaborador.js
 * Lógica del modal "Agregar Colaborador" – Grupo Dalvi
 */

(function () {
    'use strict';

    // ── Elementos ────────────────────────────────────────
    const backdrop     = document.getElementById('modalBackdrop');
    const modal        = document.getElementById('modalAgregarColab');
    const btnAbrir     = document.querySelector('[data-modal="agregar-colab"]');
    const btnClose     = document.getElementById('modalClose');
    const btnCancelar  = document.getElementById('btnCancelar');
    const form         = document.getElementById('formAgregarColab');
    const inputNombre  = document.getElementById('inputNombre');
    const selectArea   = document.getElementById('selectArea');
    const errorNombre  = document.getElementById('errorNombre');
    const errorArea    = document.getElementById('errorArea');
    const modalAlert   = document.getElementById('modalAlert');
    const btnGuardar   = document.getElementById('btnGuardar');
    const btnText      = btnGuardar?.querySelector('.btn-text');
    const btnSpinner   = btnGuardar?.querySelector('.btn-spinner');
    const modalSuccess = document.getElementById('modalSuccess');
    const successMsg   = document.getElementById('successMsg');
    const btnAgregarOtro = document.getElementById('btnAgregarOtro');
    const btnCerrarExito = document.getElementById('btnCerrarExito');

    let areasLoaded = false;

    // ── Abrir / cerrar ───────────────────────────────────
    function abrirModal() {
        backdrop.classList.add('active');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Cargar áreas solo la primera vez
        if (!areasLoaded) cargarAreas();

        // Focus en nombre después de la animación
        setTimeout(() => inputNombre?.focus(), 260);
    }

    function cerrarModal() {
        backdrop.classList.remove('active');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        resetForm();
    }

    // ── Cargar áreas desde el servidor ───────────────────
    function cargarAreas() {
        selectArea.innerHTML = '<option value="">Cargando...</option>';
        selectArea.disabled  = true;

        fetch('../../php/colaboradores.php?action=areas')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.areas.length > 0) {
                    selectArea.innerHTML = '<option value="">— Selecciona un área —</option>';
                    data.areas.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value       = a.Id_Area;
                        opt.textContent = a.Nombre;
                        selectArea.appendChild(opt);
                    });
                    areasLoaded = true;
                } else {
                    selectArea.innerHTML = '<option value="">No hay áreas disponibles</option>';
                }
            })
            .catch(() => {
                selectArea.innerHTML = '<option value="">Error al cargar áreas</option>';
            })
            .finally(() => {
                selectArea.disabled = false;
            });
    }

    // ── Validación ───────────────────────────────────────
    function validar() {
        let ok = true;

        // Nombre
        const nombre = inputNombre.value.trim();
        if (!nombre) {
            setError(inputNombre, errorNombre, 'El nombre es obligatorio.');
            ok = false;
        } else if (nombre.length < 3) {
            setError(inputNombre, errorNombre, 'Mínimo 3 caracteres.');
            ok = false;
        } else {
            clearError(inputNombre, errorNombre);
        }

        // Área
        if (!selectArea.value) {
            setError(selectArea, errorArea, 'Selecciona un departamento.');
            ok = false;
        } else {
            clearError(selectArea, errorArea);
        }

        return ok;
    }

    function setError(input, span, msg) {
        input.classList.add('is-error');
        span.textContent = msg;
    }
    function clearError(input, span) {
        input.classList.remove('is-error');
        span.textContent = '';
    }

    // ── Envío del formulario ─────────────────────────────
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        modalAlert.style.display = 'none';

        if (!validar()) return;

        // Estado cargando
        btnGuardar.disabled  = true;
        btnText.style.display    = 'none';
        btnSpinner.style.display = 'inline';

        const formData = new FormData(form);

        try {
            const res  = await fetch('../../php/colaboradores.php', {credentials:'include',
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                mostrarExito(formData.get('nombre'), selectArea.options[selectArea.selectedIndex].text);
            } else {
                mostrarAlerta(data.error || 'Error al guardar. Intenta de nuevo.');
            }
        } catch {
            mostrarAlerta('Error de conexión. Verifica tu red.');
        } finally {
            btnGuardar.disabled     = false;
            btnText.style.display   = 'inline';
            btnSpinner.style.display = 'none';
        }
    });

    // ── Mostrar éxito ────────────────────────────────────
    function mostrarExito(nombre, area) {
        form.style.display         = 'none';
        modalSuccess.style.display = 'block';
        successMsg.textContent     = `"${nombre}" fue agregado al área de ${area}.`;
    }

    // ── Mostrar alerta de error ──────────────────────────
    function mostrarAlerta(msg) {
        modalAlert.textContent     = '⚠️ ' + msg;
        modalAlert.style.display   = 'block';
    }

    // ── Reset ────────────────────────────────────────────
    function resetForm() {
        form.reset();
        form.style.display         = 'block';
        modalSuccess.style.display = 'none';
        modalAlert.style.display   = 'none';
        clearError(inputNombre, errorNombre);
        clearError(selectArea,  errorArea);
    }

    // ── Eventos ──────────────────────────────────────────
    btnAbrir?.addEventListener('click', abrirModal);
    btnClose?.addEventListener('click', cerrarModal);
    btnCancelar?.addEventListener('click', cerrarModal);
    backdrop?.addEventListener('click', cerrarModal);

    // Limpiar error al escribir
    inputNombre?.addEventListener('input', () => clearError(inputNombre, errorNombre));
    selectArea?.addEventListener('change', () => clearError(selectArea, errorArea));

    // Tecla ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal?.classList.contains('active')) cerrarModal();
    });

    // Agregar otro
    btnAgregarOtro?.addEventListener('click', resetForm);

    // Cerrar desde éxito → recargar tabla de departamentos
    btnCerrarExito?.addEventListener('click', () => {
        cerrarModal();
        // Recargar la página para reflejar el nuevo colaborador en los conteos
        setTimeout(() => window.location.reload(), 300);
    });

})();