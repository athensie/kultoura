// Shared List/Grid view toggle for the admin Content pages.
// Call once per page, e.g.:
//   initViewToggle('destinations', '.data-table-wrap', '#destinationsGrid');
// The chosen view is remembered per page (by pageKey) via localStorage.
function initViewToggle(pageKey, listSelector, gridSelector) {
    const storageKey = 'kt-admin-view-' + pageKey;
    const listEl = document.querySelector(listSelector);
    const gridEl = document.querySelector(gridSelector);
    const listBtn = document.querySelector('.view-btn[data-view="list"]');
    const gridBtn = document.querySelector('.view-btn[data-view="grid"]');

    function apply(view) {
        if (listEl) listEl.style.display = view === 'grid' ? 'none' : '';
        if (gridEl) gridEl.style.display = view === 'grid' ? 'grid' : 'none';
        if (listBtn) listBtn.classList.toggle('active', view !== 'grid');
        if (gridBtn) gridBtn.classList.toggle('active', view === 'grid');
        localStorage.setItem(storageKey, view);
    }

    window.setView = apply;
    apply(localStorage.getItem(storageKey) === 'grid' ? 'grid' : 'list');
}
