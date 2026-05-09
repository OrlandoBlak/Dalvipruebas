/**
 * functions/admin_dashboard.js
 * Grupo Dalvi – carga rápida con fetch diferido
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── SIDEBAR MOBILE ────────────────────────────────────
    const sidebar       = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarToggle = document.getElementById('sidebarToggle');

    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    const openSidebar  = () => { sidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; };
    const closeSidebar = () => { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; };

    mobileMenuBtn?.addEventListener('click', openSidebar);
    sidebarToggle?.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => e.key === 'Escape' && closeSidebar());
    window.matchMedia('(min-width: 901px)').addEventListener('change', e => e.matches && closeSidebar());

    // ── BÚSQUEDA DEPARTAMENTOS ────────────────────────────
    const buscarInput = document.getElementById('buscarDepto');
    const deptosGrid  = document.getElementById('deptosGrid');

    buscarInput?.addEventListener('input', () => {
        const q = buscarInput.value.toLowerCase().trim();
        let visible = 0;
        deptosGrid?.querySelectorAll('.depto-card').forEach(card => {
            const match = (card.dataset.nombre || '').includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        let sinRes = deptosGrid?.querySelector('.no-results-msg');
        if (visible === 0 && q) {
            if (!sinRes) {
                sinRes = document.createElement('p');
                sinRes.className = 'no-data no-results-msg';
                sinRes.style.gridColumn = '1 / -1';
                deptosGrid.appendChild(sinRes);
            }
            sinRes.textContent = `Sin resultados para "${buscarInput.value}"`;
            sinRes.style.display = '';
        } else if (sinRes) {
            sinRes.style.display = 'none';
        }
    });
    buscarInput?.addEventListener('keydown', e => {
        if (e.key === 'Escape') { buscarInput.value = ''; buscarInput.dispatchEvent(new Event('input')); }
    });

    // ── NOTIFICACIONES DROPDOWN ───────────────────────────
    const btnNotif      = document.getElementById('btnNotif');
    const notifDropdown = document.getElementById('notifDropdown');

    btnNotif?.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = notifDropdown?.style.display === 'block';
        if (notifDropdown) notifDropdown.style.display = isOpen ? 'none' : 'block';
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && notifDropdown)
            notifDropdown.style.display = 'none';
    });

    // Datos de deptos y top colaboradores vienen directo del PHP

});