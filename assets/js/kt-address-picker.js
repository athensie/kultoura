const KtAddressPicker = (function () {
  const DEFAULT_CENTER = { lat: 14.0086, lng: 121.1567 };
  // Malvar municipality envelope; reverse-geocoded municipality/province checks
  // below reject neighboring towns that happen to fall inside this envelope.
  const MALVAR_BOUNDS = { south: 13.997231, west: 121.103757, north: 14.066481, east: 121.183872 };
  const MALVAR_BARANGAYS = [
    'bagong pook', 'bilucao', 'san isidro west', 'bulihan', 'luta del norte', 'luta del sur',
    'poblacion', 'san andres', 'san fernando', 'san isidro east', 'san gregorio', 'san juan',
    'san pedro i', 'san pedro ii', 'san pioquinto', 'santiago',
  ];
  function isInsideMalvarBounds(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng)
      && lat >= MALVAR_BOUNDS.south && lat <= MALVAR_BOUNDS.north
      && lng >= MALVAR_BOUNDS.west && lng <= MALVAR_BOUNDS.east;
  }
  function isMalvarAddress(address) {
    if (!address) return false;
    const localities = [address.municipality, address.city, address.town, address.village, address.suburb, address.neighbourhood, address.city_district, address.quarter, address.county]
      .filter(Boolean).map(v => String(v).toLowerCase());
    const provinces = [address.province, address.state_district, address.county].filter(Boolean).map(v => String(v).toLowerCase());
    const inBatangas = provinces.some(v => v.includes('batangas'));
    const isBarangay = localities.some(value => MALVAR_BARANGAYS.some(barangay =>
      value === barangay || value.startsWith(barangay + ' (') || value.startsWith(barangay + ' ')));
    const isAlias = localities.some(value => value.startsWith('san isidro west')
      || value.startsWith('san pedro i east') || value.startsWith('san pedro ii west'));
    return inBatangas && (localities.includes('malvar') || isBarangay || isAlias);
  }
  function debounce(fn, wait) {
    let t = null;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }
  async function searchAddress(query, suggestBox, setPosition) {
    if (query.trim().length < 3) {
      suggestBox.style.display = 'none';
      suggestBox.innerHTML = '';
      return;
    }
    try {
      const scopedQuery = /malvar/i.test(query) ? query : query + ', Malvar, Batangas';
      const url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&countrycodes=ph&viewbox=121.103757,14.066481,121.183872,13.997231&bounded=1&q=' + encodeURIComponent(scopedQuery);
      const res = await fetch(url);
      const results = await res.json();
      suggestBox.innerHTML = '';
      results.filter(r => isMalvarAddress(r.address)).forEach(function (r) {
        const item = document.createElement('div');
        item.className = 'kt-suggest-item';
        item.textContent = r.display_name;
        item.addEventListener('click', function () {
          setPosition(parseFloat(r.lat), parseFloat(r.lon), 16, r.display_name, r.address);
          suggestBox.innerHTML = '';
          suggestBox.style.display = 'none';
        });
        suggestBox.appendChild(item);
      });
      if (!suggestBox.children.length) {
        const empty = document.createElement('div');
        empty.className = 'kt-suggest-empty';
        empty.textContent = 'No matching address in Malvar, Batangas. Try a barangay or street name.';
        suggestBox.appendChild(empty);
      }
      suggestBox.style.display = 'block';
    } catch (err) {
      console.error('Address search failed:', err);
    }
  }
  async function reverseGeocode(lat, lng) {
    const url = 'https://nominatim.openstreetmap.org/reverse?format=json&addressdetails=1&lat=' + lat + '&lon=' + lng;
    const res = await fetch(url);
    return await res.json();
  }
  function setup(opts) {
    const mapEl = document.getElementById(opts.mapElId);
    const input = document.getElementById(opts.inputElId);
    if (!mapEl || !input || typeof L === 'undefined') return null;
    const center = opts.defaultCenter || DEFAULT_CENTER;
    const map = L.map(mapEl).setView([center.lat, center.lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map);
    const marker = L.marker([center.lat, center.lng], { draggable: true, opacity: 0 }).addTo(map);
    let hasPosition = false;
    let lastValidPosition = null;
    function setStatus(text) {
      const statusEl = document.getElementById(opts.statusElId);
      if (statusEl) statusEl.textContent = text;
    }
    function notifyPosition(lat, lng, address) {
      hasPosition = true;
      lastValidPosition = [lat, lng];
      if (typeof opts.onPosition === 'function') opts.onPosition(lat, lng, address);
      setStatus('✓ Pin set at ' + address + ' — drag it to fine-tune.');
    }
    async function setPosition(lat, lng, zoom, addressText, addressDetails) {
      lat = Number(lat); lng = Number(lng);
      if (!isInsideMalvarBounds(lat, lng)) {
        setStatus('Choose a pin inside Malvar, Batangas.');
        if (lastValidPosition) marker.setLatLng(lastValidPosition);
        return false;
      }
      setStatus('Checking the address is in Malvar…');
      let geocoded;
      try {
        geocoded = addressDetails ? { address: addressDetails, display_name: addressText } : await reverseGeocode(lat, lng);
      } catch (err) {
        setStatus('Could not verify this address. Choose a location inside Malvar and try again.');
        if (lastValidPosition) marker.setLatLng(lastValidPosition);
        return false;
      }
      if (!isMalvarAddress(geocoded.address)) {
        setStatus('That pin is outside Malvar, Batangas. Choose a location inside Malvar.');
        if (lastValidPosition) marker.setLatLng(lastValidPosition);
        return false;
      }
      map.setView([lat, lng], zoom || map.getZoom());
      marker.setLatLng([lat, lng]);
      marker.setOpacity(1);
      const detailedAddress = geocoded.display_name || addressText || '';
      if (detailedAddress) input.value = detailedAddress;
      notifyPosition(lat, lng, detailedAddress || 'Malvar, Batangas');
      return true;
    }
    marker.on('dragend', function () {
      const pos = marker.getLatLng();
      setPosition(pos.lat, pos.lng, map.getZoom());
    });
    map.on('click', function (e) {
      setPosition(e.latlng.lat, e.latlng.lng, map.getZoom());
    });
    function locate() {
      if (!navigator.geolocation) {
        setStatus("Your browser doesn't support location access — search or click the map instead.");
        return;
      }
      setStatus('Finding your location…');
      navigator.geolocation.getCurrentPosition(
        function (pos) { setPosition(pos.coords.latitude, pos.coords.longitude, 17); },
        function (err) {
          setStatus(err.code === err.PERMISSION_DENIED
            ? 'Location access was denied — search or click the map instead.'
            : "Couldn't get your location — search or click the map instead.");
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
      );
    }
    const suggestBox = document.createElement('div');
    suggestBox.className = 'kt-suggest-box';
    input.parentNode.appendChild(suggestBox);
    const debouncedSearch = debounce(function () { searchAddress(input.value, suggestBox, setPosition); }, 450);
    input.addEventListener('input', debouncedSearch);
    input.addEventListener('focus', function () {
      if (suggestBox.innerHTML) suggestBox.style.display = 'block';
    });
    document.addEventListener('click', function (e) {
      if (e.target !== input && !suggestBox.contains(e.target)) suggestBox.style.display = 'none';
    });
    input.addEventListener('blur', function () {
      setTimeout(async () => {
        if (!hasPosition && input.value.trim().length >= 3) {
          try {
            const query = /malvar/i.test(input.value) ? input.value : input.value + ', Malvar, Batangas';
            const url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=1&countrycodes=ph&viewbox=121.103757,14.066481,121.183872,13.997231&bounded=1&q=' + encodeURIComponent(query);
            const res = await fetch(url);
            const results = await res.json();
            if (results.length && isMalvarAddress(results[0].address)) {
              setPosition(parseFloat(results[0].lat), parseFloat(results[0].lon), 16, results[0].display_name, results[0].address);
            } else {
              setStatus('Choose an address inside Malvar, Batangas.');
            }
          } catch (err) {
            console.error('Fallback geocode failed:', err);
          }
        }
      }, 200);
    });
    return {
      map,
      marker,
      setPosition,
      setStatus,
      locate,
      hasPosition: function () { return hasPosition; },
      clearPosition: function () {
        hasPosition = false;
        lastValidPosition = null;
        marker.setOpacity(0);
        setStatus('Search an address or click the map to place a pin.');
      },
      reset: function () {
        hasPosition = false;
        lastValidPosition = null;
        map.invalidateSize();
        map.setView([center.lat, center.lng], 13);
        marker.setLatLng([center.lat, center.lng]);
        marker.setOpacity(0);
        input.value = '';
        setStatus('Search an address, or click/drag the pin to set the exact spot.');
      },
    };
  }
  return { setup, DEFAULT_CENTER };
})();
