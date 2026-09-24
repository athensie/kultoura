/* =========================================================
   KULTOURA — ADMIN DASHBOARD (DESTINATIONS) — SCRIPTS
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

/* ---------- FREE ADDRESS PICKER (OpenStreetMap/Nominatim search + Google Maps preview) ---------- */
const KT_DEFAULT_CENTER = { lat: 14.0086, lng: 121.1567 }; // Malvar, Batangas

let ktAddMap = null;
let ktEditMap = null;

function ktDebounce(fn, wait) {
  let t = null;
  return function (...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), wait);
  };
}

async function ktSearchAddress(query, suggestBox, input, setPosition) {
  if (query.trim().length < 3) {
    suggestBox.style.display = 'none';
    suggestBox.innerHTML = '';
    return;
  }
  try {
    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&countrycodes=ph&q=' + encodeURIComponent(query);
    const res = await fetch(url);
    const results = await res.json();

    suggestBox.innerHTML = '';
    if (!results.length) {
      const empty = document.createElement('div');
      empty.className = 'kt-suggest-empty';
      empty.textContent = 'No matches — try a more specific address.';
      suggestBox.appendChild(empty);
      suggestBox.style.display = 'block';
      return;
    }

    results.forEach(function (r) {
      const item = document.createElement('div');
      item.className = 'kt-suggest-item';
      item.textContent = r.display_name;
      item.addEventListener('click', function () {
        input.value = r.display_name;
        setPosition(parseFloat(r.lat), parseFloat(r.lon), 16);
        suggestBox.innerHTML = '';
        suggestBox.style.display = 'none';
      });
      suggestBox.appendChild(item);
    });
    suggestBox.style.display = 'block';
  } catch (err) {
    console.error('Address search failed:', err);
  }
}

function ktSetupMapPicker(mapElId, inputElId, hiddenElId, statusElId) {
  const mapEl = document.getElementById(mapElId);
  const input = document.getElementById(inputElId);
  if (!mapEl || !input || typeof L === 'undefined') return null;

  const map = L.map(mapEl).setView([KT_DEFAULT_CENTER.lat, KT_DEFAULT_CENTER.lng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map);

  const marker = L.marker([KT_DEFAULT_CENTER.lat, KT_DEFAULT_CENTER.lng], { draggable: true }).addTo(map);

  function setStatus(text) {
    const statusEl = document.getElementById(statusElId);
    if (statusEl) statusEl.textContent = text;
  }

  function setHidden(lat, lng) {
    const hidden = document.getElementById(hiddenElId);
    if (hidden) hidden.value = Number(lat).toFixed(6) + ',' + Number(lng).toFixed(6);
    setStatus('✓ Pin set at ' + Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5) + ' — drag it to fine-tune.');
  }

  function setPosition(lat, lng, zoom) {
    map.setView([lat, lng], zoom || map.getZoom());
    marker.setLatLng([lat, lng]);
    setHidden(lat, lng);
  }

  // Reverse geocode: turn clicked/dragged coordinates into a
  // human-readable address and drop it into the input field.
  async function reverseGeocode(lat, lng) {
    try {
      const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng;
      const res = await fetch(url);
      const result = await res.json();
      if (result && result.display_name) {
        input.value = result.display_name;
      }
    } catch (err) {
      console.error('Reverse geocode failed:', err);
    }
  }

  marker.on('dragend', function () {
    const pos = marker.getLatLng();
    setHidden(pos.lat, pos.lng);
    reverseGeocode(pos.lat, pos.lng);
  });

  // Click anywhere on the map to drop the pin there.
  map.on('click', function (e) {
    setPosition(e.latlng.lat, e.latlng.lng, map.getZoom());
    reverseGeocode(e.latlng.lat, e.latlng.lng);
  });

  const suggestBox = document.createElement('div');
  suggestBox.className = 'kt-suggest-box';
  input.parentNode.appendChild(suggestBox);

  const debouncedSearch = ktDebounce(function () {
    ktSearchAddress(input.value, suggestBox, input, setPosition);
  }, 450);

  input.addEventListener('input', debouncedSearch);
  input.addEventListener('focus', function () {
    if (suggestBox.innerHTML) suggestBox.style.display = 'block';
  });
  document.addEventListener('click', function (e) {
    if (e.target !== input && !suggestBox.contains(e.target)) {
      suggestBox.style.display = 'none';
    }
  });

  // Fallback: if the admin types an address and tabs/clicks away
  // WITHOUT picking a suggestion, dragging, or clicking the map,
  // geocode whatever they typed so google_maps still gets saved.
  input.addEventListener('blur', function () {
    setTimeout(async () => {
      const hidden = document.getElementById(hiddenElId);
      if (hidden && !hidden.value && input.value.trim().length >= 3) {
        try {
          const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=' + encodeURIComponent(input.value);
          const res = await fetch(url);
          const results = await res.json();
          if (results.length) {
            setPosition(parseFloat(results[0].lat), parseFloat(results[0].lon), 16);
          }
        } catch (err) {
          console.error('Fallback geocode failed:', err);
        }
      }
    }, 200);
  });

  return { map: map, marker: marker, setPosition: setPosition, setStatus: setStatus };
}

// Lazily creates each picker the first time its modal opens, and just
// re-centers/invalidates size on later opens (Leaflet renders wrong in
// a container that was hidden with display:none at init time).
function ktRefreshAddMap() {
  setTimeout(() => {
    if (!ktAddMap) {
      ktAddMap = ktSetupMapPicker('addMapPicker', 'addLocationInput', 'addGoogleMaps', 'addMapStatus');
    } else {
      ktAddMap.map.invalidateSize();
      ktAddMap.setPosition(KT_DEFAULT_CENTER.lat, KT_DEFAULT_CENTER.lng, 13);
    }
    if (ktAddMap) ktAddMap.setStatus('Search an address, or click/drag the pin to set the exact spot.');
  }, 150);
}

function ktRefreshEditMap(savedGoogleMaps) {
  setTimeout(() => {
    if (!ktEditMap) {
      ktEditMap = ktSetupMapPicker('editMapPicker', 'editDestinationLocationInput', 'editGoogleMaps', 'editMapStatus');
    } else {
      ktEditMap.map.invalidateSize();
    }
    if (!ktEditMap) return;

    const parts = (savedGoogleMaps || '').split(',').map(Number);
    if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
      ktEditMap.setPosition(parts[0], parts[1], 16);
    } else {
      ktEditMap.setPosition(KT_DEFAULT_CENTER.lat, KT_DEFAULT_CENTER.lng, 13);
      const hidden = document.getElementById('editGoogleMaps');
      if (hidden) hidden.value = '';
      ktEditMap.setStatus('Search an address, or click/drag the pin to set the exact spot.');
    }
  }, 150);
}

/* ---------- ADD ---------- */
function openAddDestination() {
  document.getElementById('addDestinationForm').reset();
  const addGoogleMaps = document.getElementById('addGoogleMaps');
  if (addGoogleMaps) addGoogleMaps.value = '';
  openModal('addDestinationModal');
  ktRefreshAddMap();
}

/* ---------- VIEW ---------- */
function openViewDestination(btn) {
  const d = btn.dataset;
  const categoryLabels = { nature: 'Nature', industry: 'Industry Zone', resort: 'Resort', accommodation: 'Accommodation', bank: 'Banks', service: 'Other Services' };

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

  const categoryEl = document.getElementById('vdCategory');
  categoryEl.textContent = categoryLabels[d.category] || d.category || '—';
  categoryEl.className = 'category-badge ' + (d.category || '');

  const statusEl = document.getElementById('vdStatus');
  statusEl.textContent = (d.status || 'active').charAt(0).toUpperCase() + (d.status || 'active').slice(1);
  statusEl.className = 'status ' + (d.status || 'active');

  const mapLink = document.getElementById('vdMapLink');
  if (mapLink) {
    const saved = d.googleMaps || '';
    if (saved.includes(',')) {
      mapLink.href = 'https://www.google.com/maps?q=' + encodeURIComponent(saved);
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

/* ---------- EDIT ---------- */
function openEditDestination(btn) {
  const d = btn.dataset;

  document.getElementById('editDestinationName').textContent = 'Editing: ' + (d.name || '—');
  document.getElementById('editDestinationId').value = d.id || '';
  document.getElementById('editDestinationNameInput').value = d.name || '';
  document.getElementById('editDestinationLocationInput').value = d.location || '';
  document.getElementById('editDestinationDescInput').value = d.desc || '';

  const categorySelect = document.getElementById('editDestinationCategoryInput');
  if (categorySelect) categorySelect.value = d.category || 'nature';

  const statusSelect = document.getElementById('editDestinationStatusInput');
  if (statusSelect) statusSelect.value = d.status || 'active';

  openModal('editDestinationModal');
  ktRefreshEditMap(d.googleMaps || '');
}

/* ---------- DELETE ---------- */
function confirmDeleteDestination(btn) {
  const d = btn.dataset;
  document.getElementById('deleteDestinationTitle').textContent = 'Delete "' + (d.name || 'this destination') + '"?';
  document.getElementById('deleteDestinationId').value = d.id || '';
  openModal('deleteDestinationModal');
}

/* ---------- SEARCH / FILTER ----------
   Both the table rows (list view) and the grid cards (grid view) carry
   matching data, so filters just apply to whichever elements exist —
   whichever view is currently hidden stays correctly filtered for when
   the user switches to it. */
function filterDestinations(value) {
  const term = value.trim().toLowerCase();
  document.querySelectorAll('#destinationsTable tbody tr, #destinationsGrid .grid-card').forEach(item => {
    if (item.id === 'destEmptyRow' || item.classList.contains('grid-empty')) return;
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(term) ? '' : 'none';
  });
}

function filterByStatus(value) {
  applyDestinationFilters();
}

function filterByCategory(value) {
  applyDestinationFilters();
}

// Status and category filters both need to pass for a row to show,
// so they're applied together rather than overwriting each other.
function applyDestinationFilters() {
  const statusVal = document.getElementById('destStatusFilter')?.value || 'all';
  const categoryVal = document.getElementById('destCategoryFilter')?.value || 'all';

  document.querySelectorAll('#destinationsTable tbody tr, #destinationsGrid .grid-card').forEach(item => {
    if (item.id === 'destEmptyRow' || item.classList.contains('grid-empty')) return;

    const statusEl = item.querySelector('.status');
    const rowStatus = statusEl ? statusEl.classList[1] : '';
    const rowCategory = item.dataset.category || '';

    const statusMatch = statusVal === 'all' || rowStatus === statusVal;
    const categoryMatch = categoryVal === 'all' || rowCategory === categoryVal;

    item.style.display = (statusMatch && categoryMatch) ? '' : 'none';
  });
}

/* ---------- EXPORT AS EXCEL ---------- */
// Reads straight from the rendered table (not a JS data mirror), so the
// export always matches whatever PHP actually rendered on the page —
// no risk of the export drifting out of sync with real data later.
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

  // Lazily load SheetJS if not already loaded
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
      ['#', 'Destination', 'Category', 'Location', 'Rating', 'Status'],
    ];

    let active = 0, pending = 0, inactive = 0;

    rows.forEach((row, i) => {
      const cells = row.querySelectorAll('td');
      const name = cells[0]?.textContent.trim() || '';
      const category = cells[1]?.textContent.trim() || '';
      const location = cells[2]?.textContent.trim() || '';
      const rating = cells[3]?.textContent.trim() || '';
      const status = cells[4]?.textContent.trim() || '';

      sheetRows.push([i + 1, name, category, location, rating, status]);

      const s = status.toLowerCase();
      if (s === 'active') active++;
      else if (s === 'pending') pending++;
      else if (s === 'inactive') inactive++;
    });

    sheetRows.push([]);
    sheetRows.push(['Total Destinations', rows.length]);
    sheetRows.push(['Active', active]);
    sheetRows.push(['Pending', pending]);
    sheetRows.push(['Inactive', inactive]);

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetRows);
    ws['!cols'] = [{ wch: 4 }, { wch: 32 }, { wch: 16 }, { wch: 24 }, { wch: 10 }, { wch: 12 }];
    ws['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 5 } },
      { s: { r: 1, c: 0 }, e: { r: 1, c: 5 } },
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