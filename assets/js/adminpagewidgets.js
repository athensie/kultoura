// KulToura Admin — Page Widgets
// pageWidgetsData / pageWidgetLabels are injected server-side by
// adminpagewidgets.php via window.pageWidgetsData = <?php echo json_encode(...); ?>
// (openModal/closeModal/closeModalOutside/showToast come from adminhomepage.js,
// loaded on this page before this file).

function openAddWidget(pageSlug, pageLabel) {
    document.getElementById('addWidgetPageSlug').value = pageSlug;
    document.getElementById('addWidgetPageLabel').textContent = pageLabel;
    openModal('addWidgetModal');
}

function openEditWidget(id) {
    const w = (window.pageWidgetsData || []).find((row) => Number(row.id) === id);
    if (!w) return;
    document.getElementById('editWidgetId').value = w.id;
    document.getElementById('editWidgetPageSlug').value = w.page_slug;
    document.getElementById('editWidgetPageLabel').textContent = (window.pageWidgetLabels || {})[w.page_slug] || w.page_slug;
    document.getElementById('editWidgetBgColor').value = w.bg_color || '#1877f2';
    document.getElementById('editWidgetCard1Text').value = w.card1_text || '';
    for (let n = 1; n <= 5; n++) {
        const captionField = document.getElementById(`editWidgetPhoto${n}Caption`);
        if (captionField) captionField.value = w[`photo_${n}_caption`] || '';
        const descField = document.getElementById(`editWidgetPhoto${n}Description`);
        if (descField) descField.value = w[`photo_${n}_description`] || '';
    }
    openModal('editWidgetModal');
}

function confirmArchiveWidget(id, label) {
    document.getElementById('archiveIdInput').value = id;
    document.getElementById('archiveTitle').textContent = `Archive "${label}" widget?`;
    openModal('archiveModal');
}
