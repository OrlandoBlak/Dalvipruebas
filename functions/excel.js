/**
 * functions/excel.js
 * Exportar tabla general de reportes a Excel – Grupo Dalvi
 * Usa SheetJS (xlsx) cargado desde CDN
 */

// Insignias Dalvi — mapeadas por número
const INSIGNIAS_DALVI = [
    'Pasión por el Cliente',
    'Excelencia con Propósito',
    'Talento que Crece, Liderazgos que Inspiran',
    'Decisiones Rápidas, Simples y Efectivas',
    'Comunicación Rápida y Honesta',
    'Cultura de Resultados con Empatía',
    'Cambio Constante, Mejora Continua',
];

/**
 * Convierte un número de insignias en texto descriptivo
 * Ej: 3 → "Pasión por el Cliente, Excelencia con Propósito, Talento que Crece..."
 */
function insigniasTexto(num) {
    const n = parseInt(num) || 0;
    if (n === 0) return '—';
    const lista = INSIGNIAS_DALVI.slice(0, n);
    return lista.join(', ');
}

/**
 * Exporta la tabla #tablaReportes a un archivo .xlsx
 */
function exportarExcel() {
    const btn     = document.getElementById('btnExportExcel');
    const btnText = btn?.querySelector('.btn-text');
    const spinner = btn?.querySelector('.btn-spinner');

    if (btn)     btn.disabled          = true;
    if (btnText) btnText.style.display = 'none';
    if (spinner) spinner.style.display = 'inline';

    try {
        const tabla = document.getElementById('tablaReportes');
        if (!tabla) throw new Error('Tabla no encontrada');

        // ── Construir datos para SheetJS ──────────────────
        const headers = [
            'ID',
            'Colaborador',
            'Área',
            'Promedio (/10)',
            'Nivel',
            'Rango',
            'Criterios Evaluados',
            'Insignias (cantidad)',
            'Insignias Dalvi',
            'Descripción Reporte',
        ];

        const filas = [];
        const rows  = tabla.querySelectorAll('tbody tr');

        rows.forEach(row => {
            if (row.style.display === 'none') return; // respetar filtros

            const cells = row.querySelectorAll('td');

            const id        = cells[0]?.textContent.trim() || '';
            const colaborador = cells[1]?.textContent.trim() || '';
            const area      = cells[2]?.textContent.trim() || '';
            const promRaw   = cells[3]?.textContent.trim() || '';
            const prom      = parseFloat(promRaw) || 0;
            const nivel     = cells[4]?.textContent.trim() || '—';
            const criterios = cells[5]?.textContent.trim().replace(/\s+/g,' ') || '—';
            // Insignias — el badge tiene title y textContent con el nombre de la insignia
            const insBadge  = cells[6]?.querySelector('.ins-badge');
            const insTexto  = insBadge ? (insBadge.getAttribute('title') || insBadge.textContent.replace('🏅','').trim()) : '';
            const insCount  = insTexto ? 1 : 0;
            const desc      = cells[7]?.textContent.trim() || '—';

            // Determinar rango según nivel
            let rango = '';
            const nivelLow = nivel.toLowerCase();
            if (nivelLow.includes('excep'))       rango = '90–100%';
            else if (nivelLow.includes('camino')) rango = '75–89%';
            else if (nivelLow.includes('desarr')) rango = '60–74%';
            else if (nivelLow.includes('requiere') || nivelLow.includes('debajo')) rango = '0–59%';

            filas.push([
                id,
                colaborador,
                area,
                prom,
                nivel,
                rango,
                criterios,
                insCount || '—',
                insTexto || '—',
                desc,
            ]);
        });

        if (filas.length === 0) {
            alert('No hay datos visibles para exportar.');
            return;
        }

        // ── Crear workbook con SheetJS ────────────────────
        const wb  = XLSX.utils.book_new();
        const wsData = [headers, ...filas];
        const ws  = XLSX.utils.aoa_to_sheet(wsData);

        // Anchos de columna
        ws['!cols'] = [
            { wch: 6  },  // ID
            { wch: 28 },  // Colaborador
            { wch: 22 },  // Área
            { wch: 14 },  // Promedio
            { wch: 18 },  // Nivel
            { wch: 12 },  // Rango
            { wch: 60 },  // Criterios
            { wch: 14 },  // Insignias num
            { wch: 70 },  // Insignias texto
            { wch: 35 },  // Descripción
        ];

        // Estilo header (negrita) — SheetJS Community no soporta estilos en xlsx
        // pero sí en xlsx-style; como usamos la versión CDN estándar, dejamos sin estilos

        XLSX.utils.book_append_sheet(wb, ws, 'Reportes');

        // ── Hoja de resumen ───────────────────────────────
        const totalEvals  = filas.length;
        const sumaProms   = filas.reduce((s, f) => s + (f[3] || 0), 0);
        const promGeneral = totalEvals ? (sumaProms / totalEvals).toFixed(1) : 0;

        const conteoNiveles = {};
        filas.forEach(f => {
            const n = f[4] || 'Sin nivel';
            conteoNiveles[n] = (conteoNiveles[n] || 0) + 1;
        });

        const totalInsignias = filas.reduce((s, f) => s + (parseInt(f[7]) || 0), 0);

        const resumenData = [
            ['RESUMEN GENERAL — GRUPO DALVI'],
            ['Generado:', new Date().toLocaleString('es-MX')],
            [],
            ['Total evaluaciones:', totalEvals],
            ['Promedio general:', promGeneral],
            ['Total insignias otorgadas:', totalInsignias],
            [],
            ['DISTRIBUCIÓN POR NIVEL'],
            ['Nivel', 'Cantidad', 'Porcentaje'],
            ...Object.entries(conteoNiveles).map(([n, c]) => [
                n, c, ((c / totalEvals) * 100).toFixed(1) + '%'
            ]),
            [],
            ['INSIGNIAS DALVI DISPONIBLES'],
            ...INSIGNIAS_DALVI.map((ins, i) => [`${i+1}. ${ins}`]),
        ];

        const wsRes = XLSX.utils.aoa_to_sheet(resumenData);
        wsRes['!cols'] = [{ wch: 35 }, { wch: 18 }, { wch: 12 }];
        XLSX.utils.book_append_sheet(wb, wsRes, 'Resumen');

        // ── Descargar ─────────────────────────────────────
        const fecha = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, `Reporte_Dalvi_${fecha}.xlsx`);

    } catch(err) {
        console.error('Error exportando Excel:', err);
        alert('Error al exportar. Intenta de nuevo.');
    } finally {
        if (btn)     btn.disabled          = false;
        if (btnText) btnText.style.display = 'inline';
        if (spinner) spinner.style.display = 'none';
    }
}