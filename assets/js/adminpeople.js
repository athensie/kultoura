// KulToura Admin — People of Malvar
// peopleData is injected server-side by adminpeople.php via:
//   <script> const peopleData = <?php echo json_encode($people); ?>; </script>
// Add/Edit/Delete forms POST straight back to adminpeople.php —
// there is no separate *_actions.php file.

document.addEventListener('DOMContentLoaded', function () {
  if (typeof lucide !== 'undefined') lucide.createIcons();

  const vpEditBtn = document.getElementById('vpEditBtn');
  if (vpEditBtn) {
    vpEditBtn.addEventListener('click', function () {
      const id = document.getElementById('viewPersonModal').dataset.editId;
      closeModal('viewPersonModal');
      setTimeout(() => openEditPerson(id), 200);
    });
  }
});

// SIDEBAR
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// MODALS
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function openAddPerson() {
  document.getElementById('addPersonForm')?.reset();
  openModal('addPersonModal');
}

function openViewPerson(id) {
  const p = (window.peopleData || []).find(x => String(x.person_id) === String(id));
  if (!p) return;
  document.getElementById('vpName').textContent = p.fullname;
  document.getElementById('vpTitle').textContent = p.title || '—';
  document.getElementById('vpAchievement').textContent = p.achievement || '—';
  document.getElementById('vpDesc').textContent = p.description || 'No description available.';
  document.getElementById('viewPersonModal').dataset.editId = id;
  openModal('viewPersonModal');
}

function openEditPerson(id) {
  const p = (window.peopleData || []).find(x => String(x.person_id) === String(id));
  if (!p) return;
  document.getElementById('editPersonName').textContent = 'Editing: ' + p.fullname;
  document.getElementById('editPersonId').value = p.person_id;
  document.getElementById('editFullnameInput').value = p.fullname;
  document.getElementById('editTitleInput').value = p.title || '';
  document.getElementById('editAchievementInput').value = p.achievement || '';
  document.getElementById('editDescInput').value = p.description || '';
  openModal('editPersonModal');
}

function confirmDelete(id, name) {
  document.getElementById('deleteTitle').textContent = 'Delete "' + name + '"?';
  document.getElementById('deleteDesc').textContent = 'This will permanently remove this profile from KulToura.';
  document.getElementById('deletePersonId').value = id;
  openModal('deleteModal');
}

// FILTERS
// Both the table rows (list view) and the grid cards (grid view) carry
// the same underlying text, so this filters whichever exist.
function filterPeople(val) {
  const rows = document.querySelectorAll('#peopleTable tbody tr, #peopleGrid .grid-card');
  rows.forEach(row => {
    if (row.classList.contains('grid-empty')) return;
    row.style.display = row.textContent.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}

// EXPORT AS EXCEL
async function exportPeopleExcel() {
  const btn = document.getElementById('exportExcelBtn');
  if (!btn) return;

  const rows = Array.from(document.querySelectorAll('#peopleTable tbody tr'))
    .filter(r => r.style.display !== 'none');

  if (rows.length === 0) {
    showToast('No profiles to export yet.');
    return;
  }

  btn.classList.add('loading');
  const label = btn.querySelector('span');
  const originalLabel = label.textContent;
  label.textContent = 'Generating…';

  if (!window.XLSX) {
    try {
      await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('Failed to load SheetJS'));
        document.head.appendChild(script);
      });
    } catch (err) {
      console.error(err);
      showToast('Could not load the export library. Check your connection.');
      btn.classList.remove('loading');
      label.textContent = originalLabel;
      return;
    }
  }

  try {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });

    const sheetRows = [
      ['KulToura — People of Malvar Export'],
      [`Generated: ${dateStr}`],
      [],
      ['#', 'Name', 'Title', 'Achievement', 'Views'],
    ];

    rows.forEach((row, i) => {
      const cells = row.querySelectorAll('td');
      const name = cells[0]?.textContent.trim() || '';
      const title = cells[1]?.textContent.trim() || '';
      const achievement = cells[2]?.textContent.trim() || '';
      const views = cells[3]?.textContent.trim() || '';

      sheetRows.push([i + 1, name, title, achievement, views]);
    });

    sheetRows.push([]);
    sheetRows.push(['Total Profiles', rows.length]);

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetRows);
    ws['!cols'] = [{ wch: 4 }, { wch: 28 }, { wch: 20 }, { wch: 32 }, { wch: 10 }];
    ws['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 4 } },
      { s: { r: 1, c: 0 }, e: { r: 1, c: 4 } },
    ];
    XLSX.utils.book_append_sheet(wb, ws, 'People of Malvar');

    XLSX.writeFile(wb, `KulToura-People-${now.toISOString().slice(0, 10)}.xlsx`);
    showToast('Export ready — check your downloads.');
  } catch (err) {
    console.error(err);
    showToast('Something went wrong generating the export.');
  } finally {
    btn.classList.remove('loading');
    label.textContent = originalLabel;
  }
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