const KtAddressPicker = (function () {
  const DEFAULT_CENTER = { lat: 14.0086, lng: 121.1567 };
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
          setPosition(parseFloat(r.lat), parseFloat(r.lon), 16, r.display_name);
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
    const marker = L.marker([center.lat, center.lng], { draggable: true }).addTo(map);
    let hasPosition = false;
    function setStatus(text) {
      const statusEl = document.getElementById(opts.statusElId);
      if (statusEl) statusEl.textContent = text;
    }
    function notifyPosition(lat, lng) {
      hasPosition = true;
      if (typeof opts.onPosition === 'function') opts.onPosition(lat, lng);
      setStatus('✓ Pin set at ' + Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5) + ' — drag it to fine-tune.');
    }
    function setPosition(lat, lng, zoom, addressText) {
      map.setView([lat, lng], zoom || map.getZoom());
      marker.setLatLng([lat, lng]);
      if (addressText) input.value = addressText;
      notifyPosition(lat, lng);
    }
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
      notifyPosition(pos.lat, pos.lng);
      reverseGeocode(pos.lat, pos.lng);
    });
    map.on('click', function (e) {
      setPosition(e.latlng.lat, e.latlng.lng, map.getZoom());
      reverseGeocode(e.latlng.lat, e.latlng.lng);
    });
    function locate() {
      if (!navigator.geolocation) {
        setStatus("Your browser doesn't support location access — search or click the map instead.");
        return;
      }
      setStatus('Finding your location…');
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          setPosition(pos.coords.latitude, pos.coords.longitude, 17);
          reverseGeocode(pos.coords.latitude, pos.coords.longitude);
        },
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
    const debouncedSearch = debounce(function () {
      searchAddress(input.value, suggestBox, setPosition);
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
    input.addEventListener('blur', function () {
      setTimeout(async () => {
        if (!hasPosition && input.value.trim().length >= 3) {
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
    return {
      map: map,
      marker: marker,
      setPosition: setPosition,
      setStatus: setStatus,
      locate: locate,
      hasPosition: function () { return hasPosition; },
      reset: function () {
        hasPosition = false;
        map.invalidateSize();
        map.setView([center.lat, center.lng], 13);
        marker.setLatLng([center.lat, center.lng]);
        setStatus('Search an address, or click/drag the pin to set the exact spot.');
      },
    };
  }
  return { setup: setup, DEFAULT_CENTER: DEFAULT_CENTER };
})();
