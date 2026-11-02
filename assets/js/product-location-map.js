window.ktProductLocationMap = {
    icon() {
        return L.divIcon({
            className: 'kt-product-pin-icon',
            html: '<svg viewBox="0 0 40 48" aria-hidden="true"><path class="kt-pin-shape" d="M20 47S2 27 2 18a18 18 0 1 1 36 0c0 9-18 29-18 29Z"/><path class="kt-pin-bag" d="M13 17h14v12H13zM16 17v-2a4 4 0 0 1 8 0v2"/></svg>',
            iconSize: [40, 48],
            iconAnchor: [20, 46],
            popupAnchor: [0, -42],
        });
    },
    addMarker(layer, location, index) {
        const lat = Number(location.lat ?? location.latitude);
        const lng = Number(location.lng ?? location.longitude);
        const marker = L.marker([lat, lng], { icon: this.icon() }).addTo(layer);
        const popup = document.createElement('div');
        popup.className = 'kt-pin-popup';
        const title = document.createElement('strong');
        title.textContent = `Location ${index + 1}`;
        const address = document.createElement('span');
        address.textContent = location.address || 'Address unavailable';
        const distance = document.createElement('span');
        distance.className = 'kt-pin-distance';
        distance.textContent = 'Checking distance from you…';
        const directions = document.createElement('a');
        directions.textContent = 'Get Directions';
        directions.target = '_blank';
        directions.rel = 'noopener';
        directions.href = '#';
        directions.setAttribute('aria-disabled', 'true');
        directions.addEventListener('click', event => {
            if (directions.getAttribute('aria-disabled') === 'true') event.preventDefault();
        });
        popup.append(title, address, distance, directions);
        marker.bindPopup(popup);
        marker.on('popupopen', () => {
            directions.textContent = 'Finding your location…';
            directions.href = '#';
            directions.setAttribute('aria-disabled', 'true');
            if (!navigator.geolocation) {
                distance.textContent = 'Your browser cannot share your current location.';
                directions.textContent = 'Get Directions';
                directions.href = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                directions.setAttribute('aria-disabled', 'false');
                return;
            }
            distance.textContent = 'Checking distance from you…';
            navigator.geolocation.getCurrentPosition(position => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                const radians = Math.PI / 180;
                const dLat = (lat - userLat) * radians;
                const dLng = (lng - userLng) * radians;
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(userLat * radians) * Math.cos(lat * radians) * Math.sin(dLng / 2) ** 2;
                const km = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                distance.textContent = km < 1
                    ? `${Math.round(km * 1000)} m from your location`
                    : `${km.toFixed(1)} km from your location`;
                directions.href = `https://www.google.com/maps/dir/?api=1&origin=${userLat},${userLng}&destination=${lat},${lng}`;
                directions.textContent = 'Get Directions';
                directions.setAttribute('aria-disabled', 'false');
            }, () => {
                distance.textContent = 'Allow location access to see your distance. Directions can still open in Google Maps.';
                directions.textContent = 'Get Directions';
                directions.href = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                directions.setAttribute('aria-disabled', 'false');
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        });
        return marker;
    },
};
