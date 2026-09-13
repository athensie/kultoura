/* =========================================================
   KULTOURA — ADMIN DASHBOARD (SITE CONTENT) — SCRIPTS
   ========================================================= */

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ---------- MODAL HELPERS ---------- */
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function openAddSection() {
  openModal('addSectionModal');
}

function openEditSection(section) {
  document.getElementById('editSectionId').value = section.id;
  document.getElementById('editSectionTitle').value = section.title;
  document.getElementById('editSectionIcon').value = section.icon_key;
  document.getElementById('editSectionBody').value = section.body;

  const removeRow = document.getElementById('editSectionRemoveImageRow');
  const removeCheckbox = removeRow.querySelector('input[type="checkbox"]');
  removeCheckbox.checked = false;
  removeRow.hidden = !section.hasImage;

  openModal('editSectionModal');
}

/* ---------- TOAST (flash message support, same pattern as other admin pages) ---------- */
let toastTimer = null;
function showToast(msg) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}
