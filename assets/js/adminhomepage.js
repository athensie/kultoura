document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    const flash = window.__adminFlash;
    if (flash) showToast(flash);
});
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }
function ktSwitchHomeTab(tab) {
    document.querySelectorAll('.admin-tab-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.admin-tab-panel').forEach((panel) => {
        panel.hidden = panel.id !== `homeTab-${tab}`;
    });
}
function openEditPartner(id) {
    const p = (window.partnersData || []).find((row) => Number(row.id) === id);
    if (!p) return;
    document.getElementById('editPartnerId').value = p.id;
    document.getElementById('editPartnerName').value = p.name || '';
    document.getElementById('editPartnerWebsite').value = p.website_url || '';
    document.getElementById('editPartnerDescription').value = p.description || '';
    document.getElementById('editPartnerExistingLogo').value = p.logo || '';
    openModal('editPartnerModal');
}
function openEditEventPhoto(id) {
    const g = (window.eventPhotosData || []).find((row) => Number(row.id) === id);
    if (!g) return;
    document.getElementById('editEventPhotoId').value = g.id;
    document.getElementById('editEventPhotoCaption').value = g.caption || '';
    updateCaptionWordCount(document.getElementById('editEventPhotoCaption'));
    document.getElementById('editEventPhotoExistingImage').value = g.image || '';
    openModal('editEventPhotoModal');
}
function confirmArchive(entityType, id, label) {
    document.getElementById('archiveIdInput').value = id;
    document.getElementById('archiveFormAction').value = `delete_${entityType}`;
    document.getElementById('archiveTitle').textContent = `Archive "${label}"?`;
    openModal('archiveModal');
}
let _toastTimer;
function showToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
function updateCaptionWordCount(field) {
    if (!field) return;
    const words = field.value.trim().split(/\s+/u).filter(Boolean);
    const limit = Number(field.dataset.wordLimit || 50);
    const count = words.length;
    const counter = field.nextElementSibling;
    field.setCustomValidity(count > limit ? `Event gallery captions can be up to ${limit} words.` : '');
    if (counter && counter.classList.contains('caption-word-count')) {
        counter.textContent = `${count} / ${limit} words`;
        counter.classList.toggle('is-over-limit', count > limit);
    }
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-word-limit="50"]').forEach(field => {
        updateCaptionWordCount(field);
        field.addEventListener('input', () => updateCaptionWordCount(field));
    });
});
