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
    const renderPreviews = (containerId, images) => {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.replaceChildren();
        images.forEach(({ label, src }) => {
            if (!src) return;
            const item = document.createElement('div');
            item.className = 'pw-current-image';
            const image = document.createElement('img');
            image.src = src;
            image.alt = `${label} current image`;
            image.loading = 'lazy';
            const caption = document.createElement('span');
            caption.textContent = label;
            item.append(image, caption);
            container.append(item);
        });
        container.hidden = container.childElementCount === 0;
    };
    renderPreviews('editWidgetMainPreview', [{ label: 'Main', src: w.main_image }]);
    [
        ['gallery_image_1', 'Grid 1'],
        ['gallery_main_image', 'Grid 2'],
        ['gallery_image_2', 'Grid 3'],
        ['gallery_image_3', 'Grid 4'],
        ['gallery_image_4', 'Grid 5']
    ].forEach(([field, label], index) => {
        renderPreviews(`editWidgetGridPreview${index + 1}`, [{ label, src: w[field] }]);
    });
    renderPreviews('editWidgetStripPreviews', Array.from({ length: 5 }, (_, index) => ({
        label: `Photo ${index + 1}`,
        src: w[`photo_${index + 1}`]
    })));
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
