// KulToura Admin — Events & Fiesta
// eventsData is injected server-side by admineventandfiesta.php via:
//   <script> const eventsData = <?php echo json_encode($events); ?>; </script>
// Add/Edit/Delete forms POST straight back to admineventandfiesta.php —
// there is no separate *_actions.php file.

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

// SIDEBAR
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// MODALS
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function openAddEvent() {
  document.getElementById('addEventForm')?.reset();
  document.getElementById('addLocationInput').value = '';
  const addLat = document.getElementById('addLatInput');
  const addLng = document.getElementById('addLngInput');
  if (addLat) addLat.value = '';
  if (addLng) addLng.value = '';
  const label = document.getElementById('addMapLabel');
  if (label) label.textContent = 'No location selected yet — search or click the map.';
  openModal('addEventModal');
  initMapPicker('add');
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
  document.getElementById('editLocationInput').value = e.location || '';
  const label = document.getElementById('editMapLabel');
  if (label) label.textContent = e.location || 'No location selected yet — search or click the map.';

  const editLat = document.getElementById('editLatInput');
  const editLng = document.getElementById('editLngInput');
  const hasCoords = e.latitude !== null && e.latitude !== undefined && e.latitude !== '' &&
                    e.longitude !== null && e.longitude !== undefined && e.longitude !== '';
  if (editLat) editLat.value = hasCoords ? e.latitude : '';
  if (editLng) editLng.value = hasCoords ? e.longitude : '';

  openModal('editEventModal');
  initMapPicker('edit', hasCoords ? parseFloat(e.latitude) : null, hasCoords ? parseFloat(e.longitude) : null);
  if (hasCoords) {
    setMapMarker('edit', parseFloat(e.latitude), parseFloat(e.longitude));
  }
}

function confirmDelete(id, name) {
  document.getElementById('deleteTitle').textContent = 'Delete "' + name + '"?';
  document.getElementById('deleteDesc').textContent = 'This will permanently remove this event from KulToura.';
  document.getElementById('deleteFiestaId').value = id;
  openModal('deleteModal');
}

// FILTERS
// Both the table rows (list view) and the grid cards (grid view) carry
// matching data-*, so filters apply to whichever exist.
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

// ─────────────────────────────────────────────────────────────
// MAP PICKER — Leaflet + OpenStreetMap tiles + Nominatim search.
// No API key, no billing account: the least-hassle way to let an
// admin pick a location by clicking a map instead of typing one.
// ─────────────────────────────────────────────────────────────
const _maps = {};
const _markers = {};

const MALVAR_CENTER = { lat: 14.0086, lng: 121.1583 };

function initMapPicker(prefix, lat, lng) {
  if (typeof L === 'undefined') return;
  const canvas = document.getElementById(prefix + 'MapCanvas');
  if (!canvas) return;

  if (_maps[prefix]) {
    _maps[prefix].remove();
    delete _maps[prefix];
    delete _markers[prefix];
  }

  const startLat = lat || MALVAR_CENTER.lat;
  const startLng = lng || MALVAR_CENTER.lng;

  const map = L.map(canvas).setView([startLat, startLng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  map.on('click', function (e) {
    setMapMarker(prefix, e.latlng.lat, e.latlng.lng);
    setLatLngInputs(prefix, e.latlng.lat, e.latlng.lng);
    reverseGeocode(prefix, e.latlng.lat, e.latlng.lng);
  });

  _maps[prefix] = map;
  setTimeout(() => map.invalidateSize(), 200);

  // If we were handed existing coordinates (editing an event), drop a pin right away.
  if (lat && lng) {
    setMapMarker(prefix, lat, lng);
    setLatLngInputs(prefix, lat, lng);
  }
}

function setLatLngInputs(prefix, lat, lng) {
  const latInput = document.getElementById(prefix + 'LatInput');
  const lngInput = document.getElementById(prefix + 'LngInput');
  if (latInput) latInput.value = lat;
  if (lngInput) lngInput.value = lng;
}

function setMapMarker(prefix, lat, lng) {
  const map = _maps[prefix];
  if (!map) return;
  if (_markers[prefix]) {
    _markers[prefix].setLatLng([lat, lng]);
  } else {
    _markers[prefix] = L.marker([lat, lng]).addTo(map);
  }
  const label = document.getElementById(prefix + 'MapLabel');
  if (label) label.textContent = '✓ Pin set at ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' — looking up address…';
}

function reverseGeocode(prefix, lat, lng) {
  const label = document.getElementById(prefix + 'MapLabel');
  const input = document.getElementById(prefix + 'LocationInput');
  if (label) label.textContent = 'Looking up address…';
  fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
    .then(res => res.json())
    .then(data => {
      const address = (data && data.display_name) ? data.display_name : (lat.toFixed(5) + ', ' + lng.toFixed(5));
      if (label) label.textContent = address;
      if (input) input.value = address;
    })
    .catch(() => {
      const fallback = lat.toFixed(5) + ', ' + lng.toFixed(5);
      if (label) label.textContent = fallback;
      if (input) input.value = fallback;
    });
}

function searchMapPlace(prefix) {
  const searchInput = document.getElementById(prefix + 'MapSearch');
  const q = searchInput ? searchInput.value.trim() : '';
  if (!q) return;
  fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q + ', Malvar, Batangas'))
    .then(res => res.json())
    .then(results => {
      if (!results || !results.length) { showToast('Place not found — try a different search or click the map.'); return; }
      const lat = parseFloat(results[0].lat);
      const lng = parseFloat(results[0].lon);
      const map = _maps[prefix];
      if (map) map.setView([lat, lng], 16);
      setMapMarker(prefix, lat, lng);
      setLatLngInputs(prefix, lat, lng);
      reverseGeocode(prefix, lat, lng);
    })
    .catch(() => showToast('Search failed — try clicking the map instead.'));
}