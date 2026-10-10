// KulToura Admin — Homepage Content (Partners / Event Gallery / Facebook Videos)
// partnersData / eventPhotosData / fbVideosData are injected server-side by
// adminhomepage.php via window.partnersData = <?php echo json_encode(...); ?>
// — a top-level const/let would NOT become a window property in a classic
// script, so these are assigned onto window explicitly (same gotcha fixed
// elsewhere in this codebase, e.g. adminfoodanddining.js's listingsData).

document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    const flash = window.__adminFlash;
    if (flash) showToast(flash);
});

// SIDEBAR
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// MODALS
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

// TABS
function ktSwitchHomeTab(tab) {
    document.querySelectorAll('.admin-tab-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.admin-tab-panel').forEach((panel) => {
        panel.hidden = panel.id !== `homeTab-${tab}`;
    });
}

// EDIT — populate the edit modal's fields from the matching row in the
// server-injected data array, then open it.
function openEditPartner(id) {
    const p = (window.partnersData || []).find((row) => Number(row.id) === id);
    if (!p) return;
    document.getElementById('editPartnerId').value = p.id;
    document.getElementById('editPartnerName').value = p.name || '';
    document.getElementById('editPartnerWebsite').value = p.website_url || '';
    document.getElementById('editPartnerExistingLogo').value = p.logo || '';
    openModal('editPartnerModal');
}

function openEditEventPhoto(id) {
    const g = (window.eventPhotosData || []).find((row) => Number(row.id) === id);
    if (!g) return;
    document.getElementById('editEventPhotoId').value = g.id;
    document.getElementById('editEventPhotoCaption').value = g.caption || '';
    document.getElementById('editEventPhotoExistingImage').value = g.image || '';
    openModal('editEventPhotoModal');
}

function openEditFbVideo(id) {
    const v = (window.fbVideosData || []).find((row) => Number(row.id) === id);
    if (!v) return;
    document.getElementById('editFbVideoId').value = v.id;
    document.getElementById('editFbVideoUrl').value = v.video_url || '';
    document.getElementById('editFbVideoTitle').value = v.title || '';
    openModal('editFbVideoModal');
}

// ARCHIVE — one shared confirm modal/form for all 3 content types, its
// form_action set to match whichever type triggered it.
function confirmArchive(entityType, id, label) {
    document.getElementById('archiveIdInput').value = id;
    document.getElementById('archiveFormAction').value = `delete_${entityType}`;
    document.getElementById('archiveTitle').textContent = `Archive "${label}"?`;
    openModal('archiveModal');
}

// TOAST
let _toastTimer;
function showToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
