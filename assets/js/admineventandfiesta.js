document.addEventListener('DOMContentLoaded', function () {
  if (typeof lucide !== 'undefined') lucide.createIcons();
  const veEditBtn = document.getElementById('veEditBtn');
  if (veEditBtn) {
    veEditBtn.addEventListener('click', function () {
      const id = document.getElementById('viewEventModal').dataset.editId;
      closeModal('viewEventModal');
      setTimeout(() => openEditEvent(id), 200);
    });
  }
});
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }
function openAddEvent() {
  document.getElementById('addEventForm')?.reset();
  document.getElementById('addLatInput').value = '';
  document.getElementById('addLngInput').value = '';
  openModal('addEventModal');
  setTimeout(() => {
    const picker = ktSetupEventMap('add');
    if (picker) { picker.map.invalidateSize(); picker.reset(); }
  }, 150);
}
function openViewEvent(id) {
  const e = (window.eventsData || []).find(x => String(x.fiesta_id) === String(id));
  if (!e) return;
  document.getElementById('veName').textContent = e.fiesta_name;
  document.getElementById('veLocation').textContent = e.location || '—';
  document.getElementById('veDate').textContent = e.celebration_date || '—';
  document.getElementById('veDesc').textContent = e.description || 'No description available.';
  const type = document.getElementById('veType');
  type.className = 'type-pill ' + (e.type || 'fiesta').toLowerCase();
  type.textContent = e.type || 'Fiesta';
  document.getElementById('viewEventModal').dataset.editId = id;
  openModal('viewEventModal');
}
function openEditEvent(id) {
  const e = (window.eventsData || []).find(x => String(x.fiesta_id) === String(id));
  if (!e) return;
  document.getElementById('editEventName').textContent = 'Editing: ' + e.fiesta_name;
  document.getElementById('editFiestaId').value = e.fiesta_id;
  document.getElementById('editNameInput').value = e.fiesta_name;
  const typeSel = document.getElementById('editTypeInput');
  if (typeSel) [...typeSel.options].forEach(o => { o.selected = o.value === e.type; });
  document.getElementById('editDateInput').value = e.celebration_date || '';
  document.getElementById('editDescInput').value = e.description || '';
  document.getElementById('editExistingImage').value = e.image || '';
  document.getElementById('editLocationInput').value = e.location || '';
  const hasCoords = e.latitude !== null && e.latitude !== undefined && e.latitude !== '' &&
                    e.longitude !== null && e.longitude !== undefined && e.longitude !== '';
  document.getElementById('editLatInput').value = hasCoords ? e.latitude : '';
  document.getElementById('editLngInput').value = hasCoords ? e.longitude : '';
  openModal('editEventModal');
  setTimeout(() => {
    const picker = ktSetupEventMap('edit');
    if (!picker) return;
    picker.map.invalidateSize();
    if (hasCoords) {
      picker.setPosition(parseFloat(e.latitude), parseFloat(e.longitude), 16);
    } else {
      picker.reset();
    }
  }, 150);
}
function confirmDelete(id, name) {
  document.getElementById('deleteTitle').textContent = 'Archive "' + name + '"?';
  document.getElementById('deleteFiestaId').value = id;
  openModal('deleteModal');
}
function filterEvents(val) {
  const rows = document.querySelectorAll('#eventsTable tbody tr, #eventsGrid .grid-card');
  rows.forEach(row => {
    if (row.classList.contains('grid-empty')) return;
    row.style.display = row.textContent.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}
function filterEventsByType(val) {
  const rows = document.querySelectorAll('#eventsTable tbody tr, #eventsGrid .grid-card');
  rows.forEach(row => {
    if (row.classList.contains('grid-empty')) return;
    if (!val || val === 'All Types') { row.style.display = ''; return; }
    row.style.display = row.dataset.type === val ? '' : 'none';
  });
}
async function exportEventsExcel() {
  const btn = document.getElementById('exportExcelBtn');
  if (!btn) return;
  const rows = Array.from(document.querySelectorAll('#eventsTable tbody tr'))
    .filter(r => r.style.display !== 'none');
  if (rows.length === 0) {
    showToast('No events to export yet.');
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
      ['KulToura — Events & Fiesta Export'],
      [`Generated: ${dateStr}`],
      [],
      ['#', 'Event', 'Type', 'Location', 'Date', 'Views'],
    ];
    rows.forEach((row, i) => {
      const cells = row.querySelectorAll('td');
      const name = cells[0]?.textContent.trim() || '';
      const type = cells[1]?.textContent.trim() || '';
      const location = cells[2]?.textContent.trim() || '';
      const date = cells[3]?.textContent.trim() || '';
      const views = cells[4]?.textContent.trim() || '';
      sheetRows.push([i + 1, name, type, location, date, views]);
    });
    sheetRows.push([]);
    sheetRows.push(['Total Events', rows.length]);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetRows);
    ws['!cols'] = [{ wch: 4 }, { wch: 32 }, { wch: 14 }, { wch: 24 }, { wch: 16 }, { wch: 10 }];
    ws['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 5 } },
      { s: { r: 1, c: 0 }, e: { r: 1, c: 5 } },
    ];
    XLSX.utils.book_append_sheet(wb, ws, 'Events & Fiesta');
    XLSX.writeFile(wb, `KulToura-Events-${now.toISOString().slice(0, 10)}.xlsx`);
    showToast('Export ready — check your downloads.');
  } catch (err) {
    console.error(err);
    showToast('Something went wrong generating the export.');
  } finally {
    btn.classList.remove('loading');
    label.textContent = originalLabel;
  }
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
const ktEventMaps = { add: null, edit: null };
function ktSetupEventMap(prefix) {
  if (ktEventMaps[prefix]) return ktEventMaps[prefix];
  ktEventMaps[prefix] = KtAddressPicker.setup({
    mapElId: prefix + 'MapCanvas',
    inputElId: prefix + 'LocationInput',
    statusElId: prefix + 'MapStatus',
    onPosition: function (lat, lng) {
      const latInput = document.getElementById(prefix + 'LatInput');
      const lngInput = document.getElementById(prefix + 'LngInput');
      if (latInput) latInput.value = lat;
      if (lngInput) lngInput.value = lng;
    },
  });
  return ktEventMaps[prefix];
}
