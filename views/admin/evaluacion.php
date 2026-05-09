<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../../index.php?error=acceso");
    exit();
}
require_once "../../config/conexion.php";
$totalColab = $conexion->query("SELECT COUNT(*) as t FROM colaboradores")->fetch_assoc()['t'];
$totalAreas = $conexion->query("SELECT COUNT(*) as t FROM areas")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación de Colaborador – Grupo Dalvi</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <link rel="stylesheet" href="../../css/evaluacion.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
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
    </div>
</aside>

<!-- MAIN -->
<main class="main-content" id="mainContent">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
            <h1 class="page-title">📋 Evaluación de Colaborador</h1>
        </div>
        <div class="topbar-right">
            <button class="btn-secondary" onclick="window.location.href='homeadmin.php'">← Volver</button>
        </div>
    </header>

    <div class="eval-wrapper">

        <!-- ══════════════════════════════════
             COLUMNA IZQUIERDA
        ══════════════════════════════════ -->
        <div class="eval-left">

            <!-- STEP 1: SELECCIÓN DE COLABORADOR -->
            <div class="eval-card" id="cardColab">
                <div class="eval-card-title">👤 Seleccionar Colaborador</div>

                <!-- Buscador + select -->
                <div class="colab-selector">
                    <div class="input-wrap" style="margin-bottom:10px">
                        <span class="input-icon">🔍</span>
                        <input type="text" id="buscarColab" class="modal-input"
                               placeholder="Buscar por nombre o área..." autocomplete="off">
                    </div>
                    <select id="selectColab" class="modal-select colab-select" size="5">
                        <option value="">Cargando colaboradores...</option>
                    </select>
                </div>

                <!-- Tarjeta del colaborador seleccionado -->
                <div class="colab-card" id="colabCard" style="display:none">
                    <div class="colab-card-avatar" id="colabAvatar">?</div>
                    <div class="colab-card-info">
                        <div class="colab-card-nombre" id="colabNombre">—</div>
                        <div class="colab-card-cargo"  id="colabCargo">—</div>
                        <span class="colab-card-area"  id="colabArea">—</span>
                    </div>
                    <div class="colab-card-score" id="colabScore">
                        <span class="score-num" id="scoreNum">0.0</span>
                        <span class="score-den">/10</span>
                        <div class="score-stars" id="scoreStars">★★★★★</div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: CRITERIOS (etiquetas) -->
            <div class="eval-card" id="cardCriterios">
                <div class="eval-card-title-row">
                    <span class="eval-card-title">☑️ Criterios a Evaluar</span>
                    <div class="criterios-btns">
                        <button class="btn-tag-action" id="btnTodos">Todos</button>
                        <button class="btn-tag-action" id="btnNinguno">Ninguno</button>
                    </div>
                </div>
                <div class="criterios-tags" id="criteriosTags">
                    <span class="tag-loading">Cargando criterios...</span>
                </div>
            </div>

            <!-- STEP 3: SLIDERS DE EVALUACIÓN -->
            <div class="eval-card" id="cardSliders">
                <div class="eval-card-title-row">
                    <span class="eval-card-title">🎯 Criterios de Evaluación</span>
                    <span class="slider-resumen" id="sliderResumen">0/0 criterios · Pond. total: 0</span>
                </div>
                <div class="sliders-lista" id="slidersLista">
                    <p class="eval-hint">Selecciona criterios arriba para evaluarlos aquí.</p>
                </div>
            </div>

            <!-- STEP 4: KPS -->
            <div class="eval-card" id="cardKps">
                <div class="eval-card-title-row">
                    <span class="eval-card-title">🏷️ KPIs del Colaborador</span>
                    <button class="btn-asignar-kpi" id="btnAsignarKpi" style="display:none">
                        📊 Asignar / Ingresar KPI
                    </button>
                </div>
                <div id="kpsInfo" class="kps-info">
                    <p class="eval-hint">Selecciona un colaborador para ver sus KPIs.</p>
                </div>
            </div>

            <!-- STEP 5: OBSERVACIONES -->
            <div class="eval-card">
                <div class="eval-card-title">💬 Observaciones y Recomendaciones</div>
                <textarea id="txtObservacion" class="eval-textarea"
                    placeholder="Logros destacados, fortalezas, reconocimientos..."></textarea>
            </div>

            <div class="eval-card">
                <div class="eval-card-title">📌 Puntos de Mejora</div>
                <textarea id="txtPuntos" class="eval-textarea"
                    placeholder="Áreas de oportunidad, habilidades a desarrollar..."></textarea>
            </div>

            <div class="eval-card">
                <div class="eval-card-title">📋 Pendientes por Entregar</div>
                <textarea id="txtPendientes" class="eval-textarea"
                    placeholder="Tareas pendientes, entregables, compromisos, fechas límite..."></textarea>
            </div>

            <div class="eval-card">
                <div class="eval-card-title">💡 Comentarios Adicionales</div>
                <textarea id="txtComentarios" class="eval-textarea"
                    placeholder="Cualquier otro comentario relevante para el expediente del colaborador..."></textarea>
            </div>

            <!-- STEP 6: INSIGNIAS -->
            <div class="eval-card">
                <div class="eval-card-title">🏅 Insignias de Valores Dalvi</div>
                <div class="insignias-lista" id="insigniasList">
                    <?php
                    $insIconos = ['❤️','💎','🚀','⚡','💬','🤝','🔄'];
                    $insRes    = $conexion->query("SELECT Id_Insignia, Descripcion FROM insignias ORDER BY Id_Insignia ASC");
                    if ($insRes && $insRes->num_rows > 0):
                        $iIdx = 0;
                        while ($ins = $insRes->fetch_assoc()):
                            $icon = $insIconos[$iIdx % count($insIconos)];
                    ?>
                    <label class="insignia-item">
                        <div class="insignia-info">
                            <span class="insignia-icon"><?= $icon ?></span>
                            <div>
                                <div class="insignia-nombre"><?= htmlspecialchars($ins['Descripcion']) ?></div>
                                <div class="insignia-tipo">Valor Dalvi</div>
                            </div>
                        </div>
                        <input type="checkbox" class="insignia-check"
                               value="<?= $ins['Id_Insignia'] ?>"
                               data-nombre="<?= htmlspecialchars($ins['Descripcion'], ENT_QUOTES) ?>">
                        <span class="insignia-custom-check">○</span>
                    </label>
                    <?php $iIdx++; endwhile;
                    else: ?>
                    <p class="eval-hint">No hay insignias configuradas en la base de datos.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NIVEL DESEADO -->
            <div class="eval-card">
                <div class="eval-card-title">🎯 Nivel Deseado por Criterio</div>
                <div class="nivel-deseado-lista" id="nivelDeseadoLista">
                    <p class="eval-hint">Selecciona criterios para ver los niveles deseados.</p>
                </div>
            </div>

            <!-- ACCIONES FINALES -->
            <div class="eval-acciones">
                <button class="btn-modal-cancel" onclick="window.location.href='homeadmin.php'">Cancelar</button>
                <button class="btn-evaluar" id="btnGuardarEval">
                    <span class="btn-text">💾 Guardar Evaluación</span>
                    <span class="btn-spinner" style="display:none">⏳ Guardando...</span>
                </button>
            </div>

        </div>

        <!-- ══════════════════════════════════
             COLUMNA DERECHA
        ══════════════════════════════════ -->
        <div class="eval-right">

            <!-- PERFIL VISUAL -->
            <div class="eval-card panel-sticky">
                <div class="eval-card-title">👤 Perfil: Actual vs Deseado</div>
                <div class="perfil-humano" id="perfilHumano">
                    <div class="humano-figura">
                        <div class="humano-cabeza"></div>
                        <div class="humano-cuerpo">
                            <div class="humano-brazo izq"></div>
                            <div class="humano-torso">
                                <div class="humano-score" id="humanScore">0.0<span>/10</span></div>
                            </div>
                            <div class="humano-brazo der"></div>
                        </div>
                        <div class="humano-piernas">
                            <div class="humano-pierna izq"></div>
                            <div class="humano-pierna der"></div>
                        </div>
                    </div>
                    <div class="perfil-legend">
                        <span class="legend-item deseado">— Deseado</span>
                        <span class="legend-item actual">□ Actual</span>
                    </div>
                </div>

                <!-- RADAR por criterio -->
                <div class="eval-card-title" style="margin-top:16px">📡 Radar por Criterio</div>
                <canvas id="radarChart" width="260" height="260"></canvas>
            </div>

            <!-- RESUMEN NIVEL -->
            <div class="eval-card nivel-resumen-card" id="nivelResumenCard">
                <div class="eval-card-title">📊 Nivel de Desempeño</div>
                <div class="nivel-display" id="nivelDisplay">
                    <div class="nivel-pct" id="nivelPct">0%</div>
                    <div class="nivel-barra-wrap">
                        <div class="nivel-barra">
                            <div class="nivel-barra-fill" id="nivelBarraFill" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="nivel-badge" id="nivelBadge">Sin evaluar</div>
                    <div class="nivel-desc"  id="nivelDesc">Selecciona criterios y mueve los sliders</div>
                </div>
            </div>

            <!-- ALERTA GUARDADO -->
            <div class="eval-alert-success" id="evalAlertSuccess" style="display:none">
                <span class="alert-icon">✅</span>
                <div>
                    <strong>¡Evaluación guardada!</strong>
                    <p id="alertMsg"></p>
                </div>
            </div>
            <div class="eval-alert-error" id="evalAlertError" style="display:none">
                <span class="alert-icon">⚠️</span>
                <div id="alertErrMsg"></div>
            </div>

        </div>
    </div>

</main>

<!-- ═══════════════════════════════════
     MODAL: ASIGNAR / INGRESAR KPI
════════════════════════════════════ -->
<div class="modal-backdrop" id="modalBackdropKpi"></div>
<div class="modal-container modal-kpi-eval" id="modalKpiEval" role="dialog" aria-modal="true">
    <div class="modal-header">
        <div class="modal-header-icon" style="background:linear-gradient(135deg,#065f46,#10b981)">📊</div>
        <div>
            <h3 class="modal-title">KPIs del Colaborador</h3>
            <p class="modal-subtitle" id="modalKpiAreaNombre">Selecciona e ingresa el avance</p>
        </div>
        <button class="modal-close" id="modalKpiClose">✕</button>
    </div>
    <div id="modalKpiBody" style="padding:20px">
        <p style="color:#94a3b8;font-size:13px">Cargando KPIs...</p>
    </div>
    <div style="padding:0 20px 20px;display:flex;justify-content:flex-end;gap:10px">
        <button class="btn-modal-cancel" id="btnCancelarKpi">Cancelar</button>
        <button class="btn-modal-save"   id="btnGuardarKpiModal"
                style="background:linear-gradient(135deg,#065f46,#10b981);box-shadow:0 4px 12px rgba(16,185,129,.28)">
            <span class="btn-text">💾 Guardar KPIs</span>
            <span class="btn-spinner" style="display:none">⏳</span>
        </button>
    </div>
</div>

<script src="../../functions/admin_dashboard.js"></script>
<script src="../../functions/evaluacion.js"></script>
</body>
</html>