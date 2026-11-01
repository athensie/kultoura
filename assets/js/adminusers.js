document.addEventListener('DOMContentLoaded', function () {
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }
function openViewAccount(btn) {
  const d = btn.dataset;
  document.getElementById('vaName').textContent = d.name;
  document.getElementById('vaEmail').textContent = d.email;
  document.getElementById('vaUsername').textContent = d.username;
  document.getElementById('vaJoined').textContent = d.joined || '—';
  const roleEl = document.getElementById('vaRole');
  roleEl.textContent = d.role;
  roleEl.className = 'role-badge ' + (d.source === 'admin' ? (d.role === 'Super Admin' ? 'super-admin' : 'admin') : 'user');
  const onlineEl = document.getElementById('vaOnline');
  onlineEl.textContent = d.online;
  onlineEl.className = 'status ' + (d.online === 'Active' ? 'active' : 'inactive');
  const promoWrap = document.getElementById('vaPromoWrap');
  if (d.promo === '') {
    promoWrap.style.display = 'none';
  } else {
    promoWrap.style.display = '';
    document.getElementById('vaPromo').textContent = d.promo === '1' ? 'Subscribed' : 'Not Subscribed';
  }
  document.getElementById('vaEditBtn').onclick = function () {
    closeModal('viewAccountModal');
    setTimeout(() => openEditAccount(btn), 200);
  };
  openModal('viewAccountModal');
}
function openEditAccount(btn) {
  const d = btn.dataset;
  const isAdmin = d.source === 'admin';
  document.getElementById('editAccountSubtitle').textContent = 'Editing: ' + (isAdmin ? (d.firstname + ' ' + d.lastname) : d.fullname);
  document.getElementById('editSource').value = d.source;
  document.getElementById('editId').value = d.id;
  document.getElementById('editUsername').value = d.username;
  document.getElementById('editEmail').value = d.email;
  document.getElementById('editAdminFields').style.display = isAdmin ? '' : 'none';
  document.getElementById('editUserFields').style.display = isAdmin ? 'none' : '';
  if (isAdmin) {
    document.getElementById('editFirstName').value = d.firstname || '';
    document.getElementById('editLastName').value = d.lastname || '';
    const roleSel = document.getElementById('editAdminRole');
    [...roleSel.options].forEach(o => { o.selected = o.value === d.adminrole; });
  } else {
    document.getElementById('editFullname').value = d.fullname || '';
    const promoSel = document.getElementById('editPromo');
    [...promoSel.options].forEach(o => { o.selected = o.value === d.promo; });
  }
  openModal('editAccountModal');
}
function confirmDeleteAccount(btn) {
  const d = btn.dataset;
  const willDeactivate = d.active === '1';
  document.getElementById('deleteTitle').textContent = (willDeactivate ? 'Deactivate "' : 'Reactivate "') + d.name + '"?';
  document.querySelector('#deleteModal .modal-sub').textContent = willDeactivate
    ? "It won't be able to sign in until reactivated. Nothing is deleted."
    : 'It will be able to sign in again.';
  document.querySelector('#deleteAccountForm button[type="submit"]').textContent = willDeactivate ? 'Yes, Deactivate' : 'Yes, Reactivate';
  document.getElementById('deleteSource').value = d.source;
  document.getElementById('deleteId').value = d.id;
  openModal('deleteModal');
}
function filterUsers(val) {
  applyAccountFilters();
}
function filterByRole(val) {
  applyAccountFilters();
}
function filterByOnlineStatus(val) {
  applyAccountFilters();
}
function applyAccountFilters() {
  const table = document.getElementById('usersTable');
  if (!table) return;
  const tab = window.ktAccountsTab || 'all';
  const term = (document.querySelector('.search-input')?.value || '').trim().toLowerCase();
  const role = document.getElementById('roleFilter')?.value || 'all';
  const status = document.getElementById('statusFilter')?.value || 'all';
  table.querySelectorAll('tbody tr').forEach(row => {
    const matchesTab = tab === 'all' || row.dataset.tab === tab;
    const matchesSearch = !term || row.textContent.toLowerCase().includes(term);
    const matchesRole = role === 'all' || row.dataset.role === role;
    const matchesStatus = status === 'all' || row.dataset.status === status;
    row.style.display = matchesTab && matchesSearch && matchesRole && matchesStatus ? '' : 'none';
  });
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
