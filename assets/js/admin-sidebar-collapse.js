// Collapsible admin sidebar (icon-only mode). Call once per page, after
// the sidebar markup and lucide.js have loaded:
//   initSidebarCollapse();
// The chosen state persists across every admin page via localStorage.
function toggleSidebarCollapse() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('collapsed');
    localStorage.setItem('kt-admin-sidebar-collapsed', collapsed ? '1' : '0');
}

function initSidebarCollapse() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    if (localStorage.getItem('kt-admin-sidebar-collapsed') === '1') {
        sidebar.classList.add('collapsed');
    }
}
