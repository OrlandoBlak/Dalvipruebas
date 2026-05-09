<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";
$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];
$totalAreas = $conexion->query("SELECT COUNT(*) as t FROM areas")->fetch_assoc()['t'];
$totalKps   = $conexion->query("SELECT COUNT(*) as t FROM kps")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPIs y Metas – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <link rel="stylesheet" href="../../css/kpis.css">
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
        <a href="departamentos.php" class="nav-item"><span class="nav-icon">🏢</span><span class="nav-text">Departamentos</span><span class="nav-badge"><?= $totalAreas ?></span></a>
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
        <div class="colab-count">
            <span class="colab-number"><?= $totalColab ?></span>
            <span class="colab-label">COLABORADORES</span>
        </div>
        <div class="sidebar-actions">
            <span class="status-dot"></span>
            <span class="status-text">Activo ahora</span>
            <a href="../../php/logout.php" class="btn-logout">↩ Salir</a>
        </div>
        <button class="btn-tour">▶ Ver Tour de Bienvenida</button>
    </div>
</aside>

<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">KPIs y Metas</h1>
        </div>
        <div class="topbar-right">
            <a href="evaluacion.php" class="btn-evaluar">▶ Empezar Evaluación</a>
            <button class="btn-primary" id="btnAgregarKpi">📊 Nuevo KPI</button>
        </div>
    </header>

    <!-- RESUMEN -->
    <div class="kpis-summary">
        <div class="summary-item">
            <span class="summary-num" id="totalKpis"><?= $totalKps ?></span>
            <span class="summary-label">KPIs configurados</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
            <span class="summary-num"><?= $totalAreas ?></span>
            <span class="summary-label">Áreas totales</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
            <span class="summary-num" id="sinKpi">—</span>
            <span class="summary-label">Áreas con KPI</span>
        </div>
    </div>

    <!-- GRID -->
    <div class="kpis-content">
        <div class="kpis-grid" id="kpisGrid">
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="kpi-card skeleton-card">
                <div class="skeleton-line" style="width:55%;height:13px;margin-bottom:14px"></div>
                <div class="skeleton-line" style="width:35%;height:10px;margin-bottom:10px"></div>
                <div class="skeleton-line" style="width:70%;height:24px;margin-bottom:10px"></div>
                <div class="skeleton-line" style="width:100%;height:7px"></div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="kpis-empty" id="kpisEmpty" style="display:none">
            <span class="empty-icon">📊</span>
            <p class="empty-title">Sin KPIs configurados</p>
            <p class="empty-sub">Agrega el primer KPI para comenzar.</p>
            <button class="btn-primary" onclick="document.getElementById('btnAgregarKpi').click()">📊 Nuevo KPI</button>
        </div>
    </div>

</main>

<!-- ═══ MODAL AGREGAR / EDITAR KPI ═══ -->
<div class="modal-backdrop" id="modalBackdropKpi"></div>
<div class="modal-container" id="modalKpi" role="dialog" aria-modal="true">
    <div class="modal-header">
        <div class="modal-header-icon">📊</div>
        <div>
            <h3 class="modal-title"  id="modalKpiTitle">Nuevo KPI</h3>
            <p class="modal-subtitle" id="modalKpiSubtitle">Define nombre, tipo, meta y área</p>
        </div>
        <button class="modal-close" id="modalKpiClose">✕</button>
    </div>
    <form id="formKpi" novalidate style="padding:22px">
        <input type="hidden" id="kpiId">

        <!-- NOMBRE -->
        <div class="modal-field">
            <label class="modal-label">NOMBRE DEL KPI <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">📋</span>
                <input type="text" id="inputKpiNombre" class="modal-input"
                       placeholder="Ej. Ventas Mensuales, Pedidos Completados..." maxlength="100">
            </div>
            <span class="field-error" id="errKpiNombre"></span>
        </div>

        <!-- TIPO -->
        <div class="modal-field">
            <label class="modal-label">TIPO <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon" id="tipoIcon">💰</span>
                <select id="selectKpiTipo" class="modal-select">
                    <option value="Dinero (MXN)">💰 Dinero (MXN)</option>
                    <option value="Número">🔢 Número</option>
                    <option value="Porcentaje (%)">📊 Porcentaje (%)</option>
                    <option value="Unidades">📦 Unidades</option>
                </select>
                <span class="select-arrow">▾</span>
            </div>
            <span class="field-error" id="errKpiTipo"></span>
        </div>

        <!-- META -->
        <div class="modal-field">
            <label class="modal-label">META <span class="required">*</span>
                <span class="label-hint" id="metaHint">Monto en pesos mexicanos</span>
            </label>
            <div class="input-wrap">
                <span class="input-icon">🎯</span>
                <input type="number" id="inputKpiMeta" class="modal-input"
                       placeholder="Ej. 100000" min="0" step="0.01">
                <span class="input-suffix" id="metaSuffix">$</span>
            </div>
            <span class="field-error" id="errKpiMeta"></span>
        </div>

        <!-- DEPARTAMENTO -->
        <div class="modal-field">
            <label class="modal-label">DEPARTAMENTO <span class="required">*</span></label>
            <div class="input-wrap">
                <span class="input-icon">👥</span>
                <select id="selectKpiArea" class="modal-select">
                    <option value="">Cargando áreas...</option>
                </select>
                <span class="select-arrow">▾</span>
            </div>
            <span class="field-error" id="errKpiArea"></span>
        </div>

        <div class="modal-alert" id="modalAlertKpi" style="display:none"></div>

        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="btnCancelarKpi">Cancelar</button>
            <button type="submit" class="btn-modal-save"   id="btnGuardarKpi">
                <span class="btn-text">Guardar</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </form>
</div>

<!-- ═══ MODAL ELIMINAR ═══ -->
<div class="modal-backdrop" id="modalBackdropElimKpi"></div>
<div class="modal-container modal-sm" id="modalElimKpi" role="dialog">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#7f1d1d,#ef4444)">🗑️</div>
        <div><h3 class="modal-title">Eliminar KPI</h3><p class="modal-subtitle">No se puede deshacer</p></div>
        <button class="modal-close" id="modalElimKpiClose">✕</button>
    </div>
    <div style="padding:22px">
        <p style="font-size:14px;color:#4a5568;line-height:1.6">
            ¿Eliminar el KPI <strong id="elimKpiNombre"></strong>?
        </p>
        <div class="modal-actions" style="margin-top:20px">
            <button class="btn-modal-cancel"   id="btnCancelarElimKpi">Cancelar</button>
            <button class="btn-eliminar-confirm" id="btnConfirmarElimKpi">
                <span class="btn-text">Sí, eliminar</span>
                <span class="btn-spinner" style="display:none">⏳</span>
            </button>
        </div>
    </div>
</div>

<script src="../../functions/admin_dashboard.js"></script>
<script src="../../functions/kpis.js"></script>
</body>
</html>