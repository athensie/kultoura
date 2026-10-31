function ktFoodCategorySlug(category) {
  const known = Object.values(window.categoriesByType || {}).flat();
  if (!known.includes(category)) return 'other';
  return category.toLowerCase().replace(/&/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
document.addEventListener('DOMContentLoaded', function () {
  if (typeof lucide !== 'undefined') lucide.createIcons();
  updateCategoryOptions('addTypeInput', 'addCategoryInput');
  toggleTypeFields('add');
  toggleCategoryField('add');
  const addTypeInput = document.getElementById('addTypeInput');
  if (addTypeInput) {
    addTypeInput.addEventListener('change', function () { toggleCategoryField('add'); });
  }
  const flash = window.__adminFlash;
  if (flash) showToast(flash);
});
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }
function updateCategoryOptions(typeSelectId, categoryInputId, selected) {
  const categoryInput = document.getElementById(categoryInputId);
  if (!categoryInput) return;
  if (selected !== undefined) {
    categoryInput.value = selected;
  }
}
function buildCategorySelect(selectEl, options) {
  selectEl.innerHTML = options.map(c => `<option value="${c}">${c}</option>`).join('');
}
function toggleCategoryField(prefix) {
  const textInput = document.getElementById(prefix + 'CategoryInput');
  const select = document.getElementById(prefix + 'CategorySelect');
  const typeEl = document.getElementById(prefix + 'TypeInput');
  if (!textInput || !select || !typeEl) return;
  const type = typeEl.value || 'product';
  const options = (window.categoriesByType && window.categoriesByType[type]) || [];
  buildCategorySelect(select, options);
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
}
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
  const locationInput = document.getElementById(prefix + 'LocationInput');
  if (locationInput) locationInput.name = isProduct ? 'location' : 'address';
}
const DEFAULT_CENTER = [14.0570, 121.1610];
const mapInstances = {};
const markerInstances = {};
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
  setTimeout(() => map.invalidateSize(), 200);
}
function dropPin(prefix, lat, lng, reverseGeocode) {
  const { canvasId, latInput, lngInput } = getMapRefs(prefix);
  const map = mapInstances[canvasId];
  if (!map) return;
  if (markerInstances[canvasId]) {
    markerInstances[canvasId].setLatLng([lat, lng]);
  } else {
    const draggable = !!latInput;
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
function reverseGeocodeLatLng(prefix, lat, lng) {
  const { addressInput } = getMapRefs(prefix);
  if (!addressInput) return;
  const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`;
  fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(res => res.json())
    .then(data => {
      if (data && data.display_name) addressInput.value = data.display_name;
    })
    .catch(() => {  });
}
const ktFoodMaps = { add: null, edit: null };
function ktSetupFoodMap(prefix) {
  if (ktFoodMaps[prefix]) return ktFoodMaps[prefix];
  ktFoodMaps[prefix] = KtAddressPicker.setup({
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
  return ktFoodMaps[prefix];
}
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
  setTimeout(() => {
    const picker = ktSetupFoodMap('add');
    if (picker) { picker.map.invalidateSize(); picker.reset(); }
  }, 150);
}
function validateListingForm(mode) {
  const prefix = mode === 'add' ? 'add' : 'edit';
  const name = document.querySelector(`#${prefix}ListingForm [name="name"]`);
  if (!name || !name.value.trim()) {
    showToast('Please enter a listing name.');
    return false;
  }
  return true;
}
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
  const categoryEl = document.getElementById('vlCategory');
  categoryEl.textContent = l.category;
  categoryEl.className = 'category-badge food-cat-' + ktFoodCategorySlug(l.category);
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
  document.getElementById('editLocationInput').value = l.address || '';
  if (l.type === 'product') {
    document.getElementById('editPriceInput').value = l.price ?? '';
  } else {
    document.getElementById('editContactInput').value = l.contact_number || '';
    document.getElementById('editHoursInput').value = l.opening_hours || '';
    document.getElementById('editGoogleMapInput').value = l.google_map || '';
  }
  const hasCoords = l.latitude !== null && l.latitude !== '' && l.longitude !== null && l.longitude !== '';
  document.getElementById('editLatInput').value = hasCoords ? l.latitude : '';
  document.getElementById('editLngInput').value = hasCoords ? l.longitude : '';
  openModal('editListingModal');
  setTimeout(() => {
    const picker = ktSetupFoodMap('edit');
    if (!picker) return;
    picker.map.invalidateSize();
    if (hasCoords) {
      picker.setPosition(parseFloat(l.latitude), parseFloat(l.longitude), 16);
    } else {
      picker.reset();
    }
  }, 150);
}
function confirmDelete(id, type, name) {
  document.getElementById('deleteTitle').textContent = 'Archive "' + name + '"?';
  document.getElementById('deleteDesc').textContent = 'This will permanently remove this listing from KulToura.';
  document.getElementById('deleteIdInput').value = id;
  document.getElementById('deleteTypeInput').value = type;
  openModal('deleteModal');
}
function filterListings() {
  const searchEl = document.getElementById('searchInput');
  const typeEl = document.getElementById('typeFilter');
  const categoryEl = document.getElementById('categoryFilter');
  const q = searchEl ? searchEl.value.toLowerCase().trim() : '';
  const type = typeEl ? typeEl.value : '';
  const category = categoryEl ? categoryEl.value : '';
  document.querySelectorAll('#foodListingsTable tbody tr, #foodListingsGrid .grid-card').forEach(row => {
    const matchesQ = !q || (row.dataset.name || row.textContent.toLowerCase()).includes(q);
    const matchesType = !type || row.dataset.type === type;
    const matchesCategory = !category || row.dataset.category === category;
    row.style.display = (matchesQ && matchesType && matchesCategory) ? '' : 'none';
  });
}
async function exportFoodListingsExcel() {
  const btn = document.getElementById('exportExcelBtn');
  if (!btn) return;
  const rows = Array.from(document.querySelectorAll('#foodListingsTable tbody tr'))
    .filter(r => r.style.display !== 'none');
  if (rows.length === 0) {
    showToast('No listings to export yet.');
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
      ['KulToura — Food & Dining Export'],
      [`Generated: ${dateStr}`],
      [],
      ['#', 'Listing', 'Type', 'Category', 'Location', 'Price / Contact', 'Views'],
    ];
    let products = 0, restaurants = 0;
    rows.forEach((row, i) => {
      const cells = row.querySelectorAll('td');
      const name = cells[0]?.textContent.trim() || '';
      const type = cells[1]?.textContent.trim() || '';
      const category = cells[2]?.textContent.trim() || '';
      const location = cells[3]?.textContent.trim() || '';
      const priceContact = cells[4]?.textContent.trim() || '';
      const views = cells[5]?.textContent.trim() || '';
      sheetRows.push([i + 1, name, type, category, location, priceContact, views]);
      if (type.toLowerCase() === 'product') products++;
      else if (type.toLowerCase() === 'restaurant') restaurants++;
    });
    sheetRows.push([]);
    sheetRows.push(['Total Listings', rows.length]);
    sheetRows.push(['Products', products]);
    sheetRows.push(['Restaurants', restaurants]);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetRows);
    ws['!cols'] = [{ wch: 4 }, { wch: 32 }, { wch: 14 }, { wch: 16 }, { wch: 24 }, { wch: 18 }, { wch: 10 }];
    ws['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 6 } },
      { s: { r: 1, c: 0 }, e: { r: 1, c: 6 } },
    ];
    XLSX.utils.book_append_sheet(wb, ws, 'Food & Dining');
    XLSX.writeFile(wb, `KulToura-FoodAndDining-${now.toISOString().slice(0, 10)}.xlsx`);
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
