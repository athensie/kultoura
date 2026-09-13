/* =========================================================
   KULTOURA — ADMIN DASHBOARD (ANNOUNCEMENTS) — SCRIPTS
   ========================================================= */

/* ---------- MODAL HELPERS ---------- */
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

/* ---------- TOAST ---------- */
let toastTimer = null;
function showToast(msg) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

/* ---------- TYPE CHIP SELECTOR ---------- */
// Keeps the hidden #announceTypeInput in sync with whichever chip is active,
// so the composer form posts a real "type" value to the backend.
function selectType(btn, type) {
  document.querySelectorAll('.type-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
  const input = document.getElementById('announceTypeInput');
  if (input) input.value = type;
}

/* ---------- SCHEDULE MODAL ---------- */
function openScheduleModal() {
  const title = document.getElementById('announceTitle').value.trim();
  if (!title) {
    showToast('Add a title before scheduling.');
    return;
  }
  openModal('scheduleModal');
}

document.addEventListener('change', function (e) {
  if (e.target && e.target.id === 'scheduleDateTime') {
    updateSchedulePreview();
  }
});

function updateSchedulePreview() {
  const input = document.getElementById('scheduleDateTime');
  const preview = document.getElementById('schedulePreview');
  if (!input || !preview) return;

  if (!input.value) {
    preview.textContent = 'Select a date to preview.';
    return;
  }

  const date = new Date(input.value);
  const formatted = date.toLocaleString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
    hour: 'numeric', minute: '2-digit'
  });
  preview.textContent = 'This will publish on ' + formatted + '.';
}

// Confirms the schedule choice, marks the composer form's status as
// "scheduled", stamps the chosen date into a hidden field, then submits
// the same composer form that Publish Now / Save Draft use.
function confirmSchedule() {
  const dateInput = document.getElementById('scheduleDateTime');
  if (!dateInput.value) {
    showToast('Pick a publish date and time first.');
    return;
  }

  const statusInput = document.getElementById('announceStatusInput');
  if (statusInput) statusInput.value = 'scheduled';

  let hiddenDate = document.getElementById('announceScheduledAtInput');
  if (!hiddenDate) {
    hiddenDate = document.createElement('input');
    hiddenDate.type = 'hidden';
    hiddenDate.name = 'scheduled_at';
    hiddenDate.id = 'announceScheduledAtInput';
    document.getElementById('announceForm').appendChild(hiddenDate);
  }
  hiddenDate.value = dateInput.value;

  closeModal('scheduleModal');
  document.getElementById('announceForm').submit();
}

/* ---------- EDIT ANNOUNCEMENT ---------- */
// Reads the row's data-* attributes (populated server-side from PHP),
// so this stays correct as soon as real announcements exist.
function openEditAnnouncement(btn) {
  const d = btn.dataset;

  document.getElementById('editAnnSubtitle').textContent = 'Editing: ' + (d.title || '—');
  document.getElementById('editAnnId').value = d.id || '';
  document.getElementById('editAnnTitle').value = d.title || '';
  document.getElementById('editAnnBody').value = d.body || '';

  const typeSelect = document.getElementById('editAnnType');
  if (typeSelect) typeSelect.value = d.type || 'info';

  const statusSelect = document.getElementById('editAnnStatus');
  if (statusSelect) statusSelect.value = d.status || 'live';

  const existingImageInput = document.getElementById('editAnnExistingImage');
  if (existingImageInput) existingImageInput.value = d.image || '';

  const preview = document.getElementById('editAnnImagePreview');
  if (preview) {
    if (d.image) {
      preview.src = d.image;
      preview.style.display = '';
    } else {
      preview.style.display = 'none';
    }
  }

  openModal('editAnnouncementModal');
}

/* ---------- DELETE ANNOUNCEMENT ---------- */
function confirmDeleteAnnouncement(btn) {
  const d = btn.dataset;
  document.getElementById('deleteAnnouncementTitle').textContent = 'Remove "' + (d.title || 'this announcement') + '"?';
  document.getElementById('deleteAnnId').value = d.id || '';
  openModal('deleteAnnouncementModal');
}

/* ---------- MOBILE SIDEBAR TOGGLE ---------- */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ---------- LUCIDE ICONS INIT ---------- */
(function initLucide() {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
})();