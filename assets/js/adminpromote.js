(function () {
    document.querySelectorAll('[data-promote-sort]').forEach(select => {
        select.addEventListener('change', () => {
            const list = document.getElementById(select.dataset.promoteSort);
            if (!list) return;
            const rows = Array.from(list.querySelectorAll('.promote-row'));
            const direction = select.value === 'greatest' ? -1 : 1;
            rows.sort((a, b) => (Number(a.dataset.views) - Number(b.dataset.views)) * direction);
            rows.forEach(row => list.appendChild(row));
        });
    });
    if (window.lucide) window.lucide.createIcons();
})();
