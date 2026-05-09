<!-- ================================================
     MODAL: AGREGAR COLABORADOR
     Incluir en homeadmin.php con: <?php include 'modal_agregar_colaborador.php'; ?>
     ================================================ -->

<!-- BACKDROP -->
<div class="modal-backdrop" id="modalBackdrop"></div>

<!-- MODAL -->
<div class="modal-container" id="modalAgregarColab" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

    <div class="modal-header">
        <div class="modal-header-icon">👤</div>
        <div>
            <h3 class="modal-title" id="modalTitle">Agregar Colaborador</h3>
            <p class="modal-subtitle">Nuevo registro en el sistema</p>
        </div>
        <button class="modal-close" id="modalClose" aria-label="Cerrar">✕</button>
    </div>

    <form id="formAgregarColab" novalidate>

        <!-- NOMBRE -->
        <div class="modal-field">
            <label class="modal-label" for="inputNombre">
                Nombre completo <span class="required">*</span>
            </label>
            <div class="input-wrap">
                <span class="input-icon">✏️</span>
                <input
                    type="text"
                    id="inputNombre"
                    name="nombre"
                    class="modal-input"
                    placeholder="Ej. Juan Pérez García"
                    maxlength="120"
                    autocomplete="off"
                    required
                >
            </div>
            <span class="field-error" id="errorNombre"></span>
        </div>

        <!-- ÁREA -->
        <div class="modal-field">
            <label class="modal-label" for="selectArea">
                Departamento / Área <span class="required">*</span>
            </label>
            <div class="input-wrap">
                <span class="input-icon">🏢</span>
                <select id="selectArea" name="id_area" class="modal-select" required>
                    <option value="">— Cargando áreas... —</option>
                </select>
                <span class="select-arrow">▾</span>
            </div>
            <span class="field-error" id="errorArea"></span>
        </div>

        <!-- ALERT de error general -->
        <div class="modal-alert" id="modalAlert" style="display:none"></div>

        <!-- ACCIONES -->
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="btnCancelar">Cancelar</button>
            <button type="submit" class="btn-modal-save" id="btnGuardar">
                <span class="btn-text">Guardar colaborador</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>

    </form>

    <!-- ÉXITO -->
    <div class="modal-success" id="modalSuccess" style="display:none">
        <div class="success-icon">✅</div>
        <p class="success-title">¡Colaborador agregado!</p>
        <p class="success-msg" id="successMsg"></p>
        <div class="modal-actions" style="justify-content:center; gap:10px; margin-top:20px;">
            <button class="btn-modal-cancel" id="btnAgregarOtro">➕ Agregar otro</button>
            <button class="btn-modal-save"   id="btnCerrarExito">Listo</button>
        </div>
    </div>

</div>