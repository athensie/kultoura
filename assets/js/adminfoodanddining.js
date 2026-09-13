// KulToura Admin — Food & Dining
// listingsData + categoriesByType are injected server-side by adminfoodanddining.php via:
//   <script> const listingsData = <?php echo json_encode($foodListings); ?>;
//            const categoriesByType = <?php echo json_encode($categoriesByType); ?>; </script>
//
// Schema note: there is no `status` column and no single `location_address`
// column. Products use `price` + `location`; restaurants use `address` +
// `contact_number` + `opening_hours` + `google_map`. Add/Edit forms show
// the relevant field group based on listing type (see toggleTypeFields).

document.addEventListener('DOMContentLoaded', function () {
  if (typeof lucide !== 'undefined') lucide.createIcons();
  // Default category + field set for the Add modal's initial "product" type.
  updateCategoryOptions('addTypeInput', 'addCategoryInput');
  toggleTypeFields('add');

  // Restaurant category dropdown setup (product keeps the free-text field).
  const addSelect = document.getElementById('addCategorySelect');
  if (addSelect) buildRestaurantCategorySelect(addSelect);
  const editSelect = document.getElementById('editCategorySelect');
  if (editSelect) buildRestaurantCategorySelect(editSelect);
  toggleCategoryField('add');

  const addTypeInput = document.getElementById('addTypeInput');
  if (addTypeInput) {
    addTypeInput.addEventListener('change', function () { toggleCategoryField('add'); });
  }

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

/* ------------------------------------------------------------------ *
 * CATEGORY <-> TYPE
 * ------------------------------------------------------------------ *
 * Category is a free-text input (not a locked-down select) so admins
 * can type their own category, e.g. "Local at Malvar". The datalist
 * tied to it via the input's `list` attribute just offers suggestions
 * — presets plus whatever custom categories have been used before for
 * that listing type (see $categoriesByType in adminfoodanddining.php).
 */
function updateCategoryOptions(typeSelectId, categoryInputId, selected) {
  const typeSelect = document.getElementById(typeSelectId);
  const categoryInput = document.getElementById(categoryInputId);
  if (!typeSelect || !categoryInput) return;
  const type = typeSelect.value || 'product';
  const options = (window.categoriesByType && window.categoriesByType[type]) || [];

  const datalist = document.getElementById(categoryInput.getAttribute('list'));
  if (datalist) {
    datalist.innerHTML = options.map(c => `<option value="${c}"></option>`).join('');
  }

  if (selected !== undefined) {
    categoryInput.value = selected;
  }
}

/* ------------------------------------------------------------------ *
 * RESTAURANT CATEGORY DROPDOWN
 * ------------------------------------------------------------------ *
 * Restaurants use a fixed dropdown instead of the free-text + datalist
 * combo products use. Both the text input and the select share
 * name="category" in the HTML — only one is ever enabled at a time
 * (the disabled one is excluded from the form submission), toggled by
 * whichever listing type is selected.
 */
const RESTAURANT_CATEGORIES = ['Restaurant', 'Karinderya', 'Cafe', 'Fast Food', 'Bulalo & Lomi House', 'Bakery', 'Milk Tea & Beverage Shop', 'Dessert Shop'];

function buildRestaurantCategorySelect(selectEl) {
  selectEl.innerHTML = RESTAURANT_CATEGORIES.map(c => `<option value="${c}">${c}</option>`).join('');
}

function toggleCategoryField(prefix) {
  const textInput = document.getElementById(prefix + 'CategoryInput');
  const select = document.getElementById(prefix + 'CategorySelect');
  const typeEl = document.getElementById(prefix + 'TypeInput');
  if (!textInput || !select || !typeEl) return;

  const isRestaurant = typeEl.value === 'restaurant';

  if (isRestaurant) {
    // Carry over whatever value is already in the text field (e.g. a
    // legacy/custom category set by openEditListing()) so nothing is lost.
    const current = textInput.value;
    if (current && ![...select.options].some(o => o.value === current)) {
      const opt = document.createElement('option');
      opt.value = current;
      opt.textContent = current + ' (existing)';
      select.insertBefore(opt, select.firstChild);
    }
    if (current) select.value = current;

    textInput.style.display = 'none';
    textInput.disabled = true;
    select.style.display = '';
    select.disabled = false;
  } else {
    textInput.style.display = '';
    textInput.disabled = false;
    select.style.display = 'none';
    select.disabled = true;
  }
}

// Shows the product-only fields (price/location) or restaurant-only fields
// (address/contact/hours/google map) depending on the selected listing type.
// Relies on the .{prefix}-product-fields / .{prefix}-restaurant-fields
// classes on the form markup.
function toggleTypeFields(prefix) {
  const typeInput = document.getElementById(prefix + 'TypeInput');
  const type = typeInput ? typeInput.value : 'product';
  const isProduct = type === 'product';
  document.querySelectorAll('.' + prefix + '-product-fields').forEach(el => {
    el.style.display = isProduct ? '' : 'none';
  });
  document.querySelectorAll('.' + prefix + '-restaurant-fields').forEach(el => {
    el.style.display = isProduct ? 'none' : '';
  });
}

/* ------------------------------------------------------------------ *
 * MAP PICKER (Leaflet + OpenStreetMap tiles + Nominatim geocoding)
 * No API key needed — this is the "easier, less hassle" option
 * compared to wiring up a billed Google Maps API key.
 *
 * Each prefix ('add' / 'edit' / 'view') has its own canvas + hidden
 * latitude/longitude inputs. The search box (e.g. addMapSearchInput)
 * is independent of the real product `location` / restaurant `address`
 * text field — it's just for finding a spot on the map — so picking a
 * pin never overwrites what the admin typed into those fields.
 * ------------------------------------------------------------------ */
const DEFAULT_CENTER = [14.0570, 121.1610]; // Malvar, Batangas
const mapInstances = {};    // canvasId -> Leaflet map
const markerInstances = {}; // canvasId -> Leaflet marker

function getMapRefs(prefix) {
  return {
    canvasId: prefix + 'MapCanvas',
    addressInput: document.getElementById(prefix + 'MapSearchInput'),
    latInput: document.getElementById(prefix + 'LatInput'),
    lngInput: document.getElementById(prefix + 'LngInput'),
    hintEl: document.getElementById(prefix + 'MapHint'),
  };
}

function setMapHint(prefix, text) {
  const { hintEl } = getMapRefs(prefix);
  if (hintEl) hintEl.textContent = text;
}

function initMapPicker(prefix, lat, lng, readOnly) {
  const { canvasId, latInput } = getMapRefs(prefix);
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof L === 'undefined') return;

  // Re-create the map fresh each time the modal opens so Leaflet always
  // renders correctly inside a container that was just made visible.
  if (mapInstances[canvasId]) {
    mapInstances[canvasId].remove();
    delete mapInstances[canvasId];
    delete markerInstances[canvasId];
  }

  const center = (lat && lng) ? [lat, lng] : DEFAULT_CENTER;
  const map = L.map(canvasId, { dragging: true, scrollWheelZoom: true }).setView(center, lat && lng ? 16 : 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map);

  mapInstances[canvasId] = map;

  if (lat && lng) {
    dropPin(prefix, lat, lng, false);
  } else if (!readOnly) {
    setMapHint(prefix, 'Search an address, or click/drag the pin to set the exact spot.');
  }

  if (!readOnly) {
    map.on('click', function (e) {
      dropPin(prefix, e.latlng.lat, e.latlng.lng, true);
    });
  }

  // Leaflet needs a resize nudge once its container becomes visible.
  setTimeout(() => map.invalidateSize(), 200);
}

function dropPin(prefix, lat, lng, reverseGeocode) {
  const { canvasId, latInput, lngInput } = getMapRefs(prefix);
  const map = mapInstances[canvasId];
  if (!map) return;

  if (markerInstances[canvasId]) {
    markerInstances[canvasId].setLatLng([lat, lng]);
  } else {
    const draggable = !!latInput; // read-only view map has no lat input, so no dragging
    const marker = L.marker([lat, lng], { draggable }).addTo(map);
    marker.on('dragend', function () {
      const pos = marker.getLatLng();
      dropPin(prefix, pos.lat, pos.lng, true);
    });
    markerInstances[canvasId] = marker;
  }

  map.setView([lat, lng], Math.max(map.getZoom(), 16));

  if (latInput) latInput.value = lat.toFixed(7);
  if (lngInput) lngInput.value = lng.toFixed(7);
  setMapHint(prefix, '✓ Pin set at ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' — drag it to fine-tune.');

  if (reverseGeocode) reverseGeocodeLatLng(prefix, lat, lng);
}

function searchAddress(prefix) {
  const { addressInput } = getMapRefs(prefix);
  const query = addressInput ? addressInput.value.trim() : '';
  if (!query) { showToast('Type an address to search first.'); return; }

  const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=' + encodeURIComponent(query);
  fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(res => res.json())
    .then(results => {
      if (!results || !results.length) { showToast('No matching location found — try a more specific address.'); return; }
      const lat = parseFloat(results[0].lat);
      const lng = parseFloat(results[0].lon);
      dropPin(prefix, lat, lng, false);
      if (addressInput && results[0].display_name) addressInput.value = results[0].display_name;
    })
    .catch(() => showToast('Could not reach the map search service. Try again.'));
}

function reverseGeocodeLatLng(prefix, lat, lng) {
  const { addressInput } = getMapRefs(prefix);
  if (!addressInput) return;
  const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`;
  fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(res => res.json())
    .then(data => {
      if (data && data.display_name) addressInput.value = data.display_name;
    })
    .catch(() => { /* silent — the pin/coords are already saved either way */ });
}

/* ------------------------------------------------------------------ *
 * ADD LISTING
 * ------------------------------------------------------------------ */
function openAddListing() {
  const form = document.getElementById('addListingForm');
  if (form) form.reset();
  document.getElementById('addLatInput').value = '';
  document.getElementById('addLngInput').value = '';
  document.getElementById('addTypeInput').value = 'product';
  updateCategoryOptions('addTypeInput', 'addCategoryInput');
  toggleTypeFields('add');
  toggleCategoryField('add');
  openModal('addListingModal');
  setTimeout(() => initMapPicker('add', null, null, false), 150);
}

function validateListingForm(mode) {
  const prefix = mode === 'add' ? 'add' : 'edit';
  const name = document.querySelector(`#${prefix}ListingForm [name="name"]`);
  if (!name || !name.value.trim()) {
    showToast('Please enter a listing name.');
    return false;
  }
  return true; // real form submit -> PHP saves it -> page reloads with fresh data
}

/* ------------------------------------------------------------------ *
 * VIEW / EDIT / DELETE — matched by id + type
 * ------------------------------------------------------------------ */
function findListing(id, type) {
  return (window.listingsData || []).find(x => String(x.id) === String(id) && x.type === type);
}

let _lastViewed = null;

function buildDetailsHtml(l) {
  if (l.type === 'product') {
    const price = (l.price !== null && l.price !== '') ? '₱' + Number(l.price).toFixed(2) : '—';
    return `<strong>Price:</strong> ${price}<br><strong>Location:</strong> ${l.address || '—'}`;
  }
  let html = `<strong>Address:</strong> ${l.address || '—'}<br>`
           + `<strong>Contact:</strong> ${l.contact_number || '—'}<br>`
           + `<strong>Hours:</strong> ${l.opening_hours || '—'}`;
  if (l.google_map) {
    html += `<br><strong>Map:</strong> <a href="${l.google_map}" target="_blank" rel="noopener" style="color:#C8A96E;">Open in Google Maps</a>`;
  }
  return html;
}

function openViewListing(id, type) {
  const l = findListing(id, type) || { name: '—', type, category: '—', desc: 'No description available.' };
  _lastViewed = l;
  document.getElementById('vlName').textContent = l.name;
  document.getElementById('vlType').textContent = l.type === 'product' ? 'Product' : 'Restaurant';
  document.getElementById('vlCategory').textContent = l.category;
  document.getElementById('vlDetails').innerHTML = buildDetailsHtml(l);
  document.getElementById('vlDesc').textContent = l.desc || 'No description available.';

  const viewCanvas = document.getElementById('viewMapCanvas');
  const hasCoords = l.latitude !== null && l.latitude !== '' && l.longitude !== null && l.longitude !== '';
  if (viewCanvas) viewCanvas.style.display = hasCoords ? '' : 'none';

  openModal('viewListingModal');
  if (hasCoords) {
    setTimeout(() => initMapPicker('view', parseFloat(l.latitude), parseFloat(l.longitude), true), 150);
  }
}

function reopenAsEdit() {
  closeModal('viewListingModal');
  if (_lastViewed) setTimeout(() => openEditListing(_lastViewed.id, _lastViewed.type), 200);
}

function openEditListing(id, type) {
  const l = findListing(id, type);
  if (!l) return;

  document.getElementById('editListingName').textContent = 'Editing: ' + l.name;
  document.getElementById('editIdInput').value = l.id;
  document.getElementById('editTypeInput').value = l.type;
  document.getElementById('editNameInput').value = l.name || '';
  document.getElementById('editDescInput').value = l.desc || '';
  document.getElementById('editImageInput').value = l.image || '';

  updateCategoryOptions('editTypeInput', 'editCategoryInput', l.category);
  toggleTypeFields('edit');
  toggleCategoryField('edit');

  if (l.type === 'product') {
    document.getElementById('editPriceInput').value = l.price ?? '';
    document.getElementById('editLocationInput').value = l.address || '';
  } else {
    document.getElementById('editAddressInput').value = l.address || '';
    document.getElementById('editContactInput').value = l.contact_number || '';
    document.getElementById('editHoursInput').value = l.opening_hours || '';
    document.getElementById('editGoogleMapInput').value = l.google_map || '';
  }

  const hasCoords = l.latitude !== null && l.latitude !== '' && l.longitude !== null && l.longitude !== '';
  document.getElementById('editLatInput').value = hasCoords ? l.latitude : '';
  document.getElementById('editLngInput').value = hasCoords ? l.longitude : '';

  openModal('editListingModal');
  const lat = hasCoords ? parseFloat(l.latitude) : null;
  const lng = hasCoords ? parseFloat(l.longitude) : null;
  setTimeout(() => initMapPicker('edit', lat, lng, false), 150);
}

function confirmDelete(id, type, name) {
  document.getElementById('deleteTitle').textContent = 'Delete "' + name + '"?';
  document.getElementById('deleteDesc').textContent = 'This will permanently remove this listing from KulToura.';
  document.getElementById('deleteIdInput').value = id;
  document.getElementById('deleteTypeInput').value = type;
  openModal('deleteModal');
}

/* ------------------------------------------------------------------ *
 * FILTERS
 * Search text, listing type, and category all apply together (a row
 * only shows if it passes all three), read straight off the filter
 * controls in the DOM rather than being passed a single value.
 * ------------------------------------------------------------------ */
function filterListings() {
  const searchEl = document.getElementById('searchInput');
  const typeEl = document.getElementById('typeFilter');
  const categoryEl = document.getElementById('categoryFilter');

  const q = searchEl ? searchEl.value.toLowerCase().trim() : '';
  const type = typeEl ? typeEl.value : '';
  const category = categoryEl ? categoryEl.value : '';

  // Both the table rows (list view) and the grid cards (grid view) carry
  // the same data-* attributes, so this filters whichever exist.
  document.querySelectorAll('#foodListingsTable tbody tr, #foodListingsGrid .grid-card').forEach(row => {
    const matchesQ = !q || (row.dataset.name || row.textContent.toLowerCase()).includes(q);
    const matchesType = !type || row.dataset.type === type;
    const matchesCategory = !category || row.dataset.category === category;
    row.style.display = (matchesQ && matchesType && matchesCategory) ? '' : 'none';
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