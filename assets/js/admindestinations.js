function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }
let toastTimer = null;
function showToast(msg) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}
const KT_DEFAULT_CENTER = KtAddressPicker.DEFAULT_CENTER;
let ktAddMap = null;
let ktEditMap = null;
function ktSetHiddenLatLng(hiddenElId, lat, lng) {
  const hidden = document.getElementById(hiddenElId);
  if (hidden) hidden.value = Number(lat).toFixed(6) + ',' + Number(lng).toFixed(6);
}
function ktRefreshAddMap() {
  setTimeout(() => {
    if (!ktAddMap) {
      ktAddMap = KtAddressPicker.setup({
        mapElId: 'addMapPicker',
        inputElId: 'addLocationInput',
        statusElId: 'addMapStatus',
        onPosition: (lat, lng) => ktSetHiddenLatLng('addGoogleMaps', lat, lng),
      });
    } else {
      ktAddMap.reset();
    }
    const hidden = document.getElementById('addGoogleMaps');
    if (hidden) hidden.value = '';
  }, 150);
}
function ktRefreshEditMap(savedGoogleMaps) {
  setTimeout(() => {
    if (!ktEditMap) {
      ktEditMap = KtAddressPicker.setup({
        mapElId: 'editMapPicker',
        inputElId: 'editDestinationLocationInput',
        statusElId: 'editMapStatus',
        onPosition: (lat, lng) => ktSetHiddenLatLng('editGoogleMaps', lat, lng),
      });
    } else {
      ktEditMap.map.invalidateSize();
    }
    if (!ktEditMap) return;
    const parts = (savedGoogleMaps || '').split(',').map(Number);
    if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
      ktEditMap.setPosition(parts[0], parts[1], 16);
    } else {
      ktEditMap.reset();
      const hidden = document.getElementById('editGoogleMaps');
      if (hidden) hidden.value = '';
    }
  }, 150);
}
function ktToggleSubcategoryField(which) {
  const categorySelect = document.getElementById(which === 'add' ? 'addCategoryInput' : 'editDestinationCategoryInput');
  const group = document.getElementById(which === 'add' ? 'addSubcategoryGroup' : 'editSubcategoryGroup');
  const select = document.getElementById(which === 'add' ? 'addSubcategoryInput' : 'editDestinationSubcategoryInput');
  if (!categorySelect || !group) return;
  const isNature = categorySelect.value === 'nature';
  group.hidden = !isNature;
  if (!isNature && select) select.value = '';
  const scheduleGroup = document.getElementById(which === 'add' ? 'addChurchScheduleGroup' : 'editChurchScheduleGroup');
  if (scheduleGroup) scheduleGroup.hidden = categorySelect.value !== 'church';
}
function ktScheduleRow(which, entry = {}) {
  const wrap = document.getElementById(which + 'ChurchScheduleEntries');
  if (!wrap) return;
  const row = document.createElement('div');
  row.className = 'church-schedule-row';
  row.innerHTML = '<input class="form-input" type="date" data-schedule-field="date" aria-label="Schedule date">' +
    '<input class="form-input" type="time" data-schedule-field="time" aria-label="Start time">' +
    '<select class="filter-select" data-schedule-field="duration" aria-label="Duration"><option value="30">30 minutes</option><option value="60">1 hour</option><option value="90">1 hour 30 minutes</option><option value="120">2 hours</option><option value="180">3 hours</option></select>' +
    '<input class="form-input church-schedule-title" type="text" maxlength="120" data-schedule-field="title" placeholder="Schedule title" aria-label="Schedule title">' +
    '<button type="button" class="btn-ghost church-schedule-remove" aria-label="Remove schedule entry">Remove</button>';
  row.querySelector('[data-schedule-field="date"]').value = entry.date || '';
  row.querySelector('[data-schedule-field="time"]').value = entry.time || '';
  row.querySelector('[data-schedule-field="duration"]').value = String(entry.duration || 60);
  row.querySelector('[data-schedule-field="title"]').value = entry.title || '';
  row.querySelector('.church-schedule-remove').addEventListener('click', () => row.remove());
  wrap.appendChild(row);
}
function ktAddChurchScheduleEntry(which) { ktScheduleRow(which); }
function ktLoadChurchSchedule(which, raw) {
  const wrap = document.getElementById(which + 'ChurchScheduleEntries');
  if (wrap) wrap.replaceChildren();
  let entries = [];
  try { entries = JSON.parse(raw || '[]'); } catch (_) {  }
  if (Array.isArray(entries)) entries.forEach(entry => ktScheduleRow(which, entry));
}
function ktScheduleRowsHtml(raw) {
  let entries;
  try { entries = JSON.parse(raw || '[]'); } catch (_) { entries = null; }
  if (!Array.isArray(entries)) return String(raw || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  return entries.map(entry => {
    const date = new Date(entry.date + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const [hour, minute] = (entry.time || '00:00').split(':').map(Number);
    const time = new Date(2000, 0, 1, hour, minute).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    const duration = Number(entry.duration) < 60 ? entry.duration + ' min' : (Number(entry.duration) % 60 ? Math.floor(entry.duration / 60) + ' hr ' + entry.duration % 60 + ' min' : entry.duration / 60 + ' hr');
    return `<div class="church-schedule-event"><strong>${date} · ${time}</strong><span>${duration}</span><div>${String(entry.title || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</div></div>`;
  }).join('');
}
document.addEventListener('DOMContentLoaded', function () {
  [['add', 'addDestinationForm'], ['edit', 'editDestinationForm']].forEach(([which, formId]) => {
    const form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', function () {
      const rows = [...document.querySelectorAll('#' + which + 'ChurchScheduleEntries .church-schedule-row')];
      const entries = rows.map(row => Object.fromEntries([...row.querySelectorAll('[data-schedule-field]')].map(input => [input.dataset.scheduleField, input.value.trim()])));
      document.getElementById(which + 'ChurchScheduleData').value = JSON.stringify(entries);
    });
  });
});
/* ---------- ADD ---------- */
function openAddDestination() {
  document.getElementById('addDestinationForm').reset();
  ktLoadChurchSchedule('add', '[]');
  const addGoogleMaps = document.getElementById('addGoogleMaps');
  if (addGoogleMaps) addGoogleMaps.value = '';
  ktToggleSubcategoryField('add');
  openModal('addDestinationModal');
  ktRefreshAddMap();
}
/* ---------- VIEW ---------- */
function openViewDestination(btn) {
  const d = btn.dataset;
  const categoryLabels = { nature: 'Nature', industry: 'Industry Zone', resort: 'Resort', accommodation: 'Accommodation', bank: 'Banks', service: 'Other Services', church: 'Churches' };
  const imgEl = document.getElementById('vdImage');
  if (imgEl) {
    if (d.image) {
      imgEl.src = d.image;
      imgEl.style.display = 'block';
    } else {
      imgEl.style.display = 'none';
    }
  }
  document.getElementById('vdIcon').textContent = d.icon || '📍';
  document.getElementById('vdName').textContent = d.name || '—';
  document.getElementById('vdLocation').textContent = d.location || '—';
  document.getElementById('vdDesc').textContent = d.desc || 'No description available.';
  document.getElementById('vdReviews').textContent = d.reviews || '0';
  document.getElementById('vdViews').textContent = d.views || '0';
  const scheduleWrap = document.getElementById('vdScheduleWrap');
  const scheduleText = d.schedule || '';
  scheduleWrap.hidden = d.category !== 'church' || !scheduleText;
  document.getElementById('vdScheduleMonth').textContent = d.scheduleMonth || '';
  document.getElementById('vdScheduleText').innerHTML = ktScheduleRowsHtml(scheduleText);
  const categoryEl = document.getElementById('vdCategory');
  categoryEl.textContent = categoryLabels[d.category] || d.category || '—';
  categoryEl.className = 'category-badge ' + (d.category || '');
  const mapLink = document.getElementById('vdMapLink');
  if (mapLink) {
    const saved = d.googleMaps || '';
    if (saved.includes(',')) {
      mapLink.href = 'https:
      mapLink.style.display = 'inline-block';
    } else {
      mapLink.style.display = 'none';
    }
  }
  const editBtn = document.getElementById('vdEditBtn');
  editBtn.onclick = () => {
    closeModal('viewDestinationModal');
    setTimeout(() => openEditDestination(btn), 200);
  };
  openModal('viewDestinationModal');
}
function openEditDestination(btn) {
  const d = btn.dataset;
  document.getElementById('editDestinationName').textContent = 'Editing: ' + (d.name || '—');
  document.getElementById('editDestinationId').value = d.id || '';
  document.getElementById('editDestinationNameInput').value = d.name || '';
  document.getElementById('editDestinationLocationInput').value = d.location || '';
  document.getElementById('editDestinationDescInput').value = d.desc || '';
  document.getElementById('editChurchScheduleMonth').value = d.scheduleMonth || '';
  ktLoadChurchSchedule('edit', d.schedule || '');
  const categorySelect = document.getElementById('editDestinationCategoryInput');
  if (categorySelect) categorySelect.value = d.category || 'nature';
  ktToggleSubcategoryField('edit');
  const subcategorySelect = document.getElementById('editDestinationSubcategoryInput');
  if (subcategorySelect) subcategorySelect.value = d.subcategory || '';
  openModal('editDestinationModal');
  ktRefreshEditMap(d.googleMaps || '');
}
function confirmDeleteDestination(btn) {
  const d = btn.dataset;
  document.getElementById('deleteDestinationTitle').textContent = 'Archive "' + (d.name || 'this destination') + '"?';
  document.getElementById('deleteDestinationId').value = d.id || '';
  openModal('deleteDestinationModal');
}
function filterDestinations(value) {
  const term = value.trim().toLowerCase();
  document.querySelectorAll('#destinationsTable tbody tr, #destinationsGrid .grid-card').forEach(item => {
    if (item.id === 'destEmptyRow' || item.classList.contains('grid-empty')) return;
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(term) ? '' : 'none';
  });
}
function filterByCategory(value) {
  document.querySelectorAll('#destinationsTable tbody tr, #destinationsGrid .grid-card').forEach(item => {
    if (item.id === 'destEmptyRow' || item.classList.contains('grid-empty')) return;
    const rowCategory = item.dataset.category || '';
    item.style.display = (value === 'all' || rowCategory === value) ? '' : 'none';
  });
}
async function exportDestinationsExcel() {
  const btn = document.getElementById('exportExcelBtn');
  if (!btn) return;
  const rows = Array.from(document.querySelectorAll('#destinationsTable tbody tr'))
    .filter(r => r.id !== 'destEmptyRow' && r.style.display !== 'none');
  if (rows.length === 0) {
    showToast('No destinations to export yet.');
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
      ['KulToura — Destinations Export'],
      [`Generated: ${dateStr}`],
      [],
      ['#', 'Destination', 'Category', 'Address', 'Views'],
    ];
    rows.forEach((row, i) => {
      const cells = row.querySelectorAll('td');
      const name = cells[0]?.textContent.trim() || '';
      const category = cells[1]?.textContent.trim() || '';
      const address = cells[2]?.textContent.trim() || '';
      const views = cells[3]?.textContent.trim() || '';
      sheetRows.push([i + 1, name, category, address, views]);
    });
    sheetRows.push([]);
    sheetRows.push(['Total Destinations', rows.length]);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetRows);
    ws['!cols'] = [{ wch: 4 }, { wch: 32 }, { wch: 16 }, { wch: 28 }, { wch: 10 }];
    ws['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 4 } },
      { s: { r: 1, c: 0 }, e: { r: 1, c: 4 } },
    ];
    XLSX.utils.book_append_sheet(wb, ws, 'Destinations');
    XLSX.writeFile(wb, `KulToura-Destinations-${now.toISOString().slice(0, 10)}.xlsx`);
    showToast('Export ready — check your downloads.');
  } catch (err) {
    console.error(err);
    showToast('Something went wrong generating the export.');
  } finally {
    btn.classList.remove('loading');
    label.textContent = originalLabel;
  }
}
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
(function initLucide() {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
})();
