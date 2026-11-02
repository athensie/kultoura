window.ktProductLocationMap = {
    directionsModal: null,
    showDirections(location, origin) {
        if (!this.directionsModal) {
            const overlay = document.createElement('div');
            overlay.className = 'kt-directions-overlay';
            overlay.innerHTML = '<section class="kt-directions-modal" role="dialog" aria-modal="true" aria-labelledby="ktDirectionsTitle"><button type="button" class="kt-directions-close" aria-label="Close directions">×</button><h2 id="ktDirectionsTitle">Directions</h2><p class="kt-directions-address"></p><p class="kt-directions-status"></p><iframe title="Map directions" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe></section>';
            overlay.addEventListener('click', event => {
                if (event.target === overlay || event.target.closest('.kt-directions-close')) this.hideDirections();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') this.hideDirections();
            });
            document.body.appendChild(overlay);
            this.directionsModal = overlay;
        }
        const title = this.directionsModal.querySelector('#ktDirectionsTitle');
        const address = this.directionsModal.querySelector('.kt-directions-address');
        const status = this.directionsModal.querySelector('.kt-directions-status');
        const frame = this.directionsModal.querySelector('iframe');
        title.textContent = `Directions to ${location.title || 'pinned location'}`;
        address.textContent = location.address || 'Pinned product location';
        if (origin) {
            status.textContent = 'Route starts from your current location.';
            frame.src = `https://www.google.com/maps?saddr=${origin.lat},${origin.lng}&daddr=${location.lat},${location.lng}&output=embed`;
        } else {
            status.textContent = 'Your current location was not available. The map is centered on this destination.';
            frame.src = `https://www.google.com/maps?q=${location.lat},${location.lng}&z=15&output=embed`;
        }
        this.directionsModal.classList.add('is-open');
        document.body.classList.add('kt-directions-open');
    },
    hideDirections() {
        if (!this.directionsModal) return;
        this.directionsModal.classList.remove('is-open');
        this.directionsModal.querySelector('iframe').src = '';
        document.body.classList.remove('kt-directions-open');
    },
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
        const directions = document.createElement('button');
        directions.type = 'button';
        directions.textContent = 'Get Directions';
        directions.disabled = true;
        popup.append(title, address, distance, directions);
        marker.bindPopup(popup);
        marker.on('popupopen', () => {
            let origin = null;
            directions.onclick = () => this.showDirections({
                lat,
                lng,
                address: location.address,
                title: `Location ${index + 1}`,
            }, origin);
            directions.textContent = 'Finding your location…';
            directions.disabled = true;
            if (!navigator.geolocation) {
                distance.textContent = 'Your browser cannot share your current location.';
                directions.textContent = 'Get Directions';
                directions.disabled = false;
                return;
            }
            distance.textContent = 'Checking distance from you…';
            navigator.geolocation.getCurrentPosition(position => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                origin = { lat: userLat, lng: userLng };
                const radians = Math.PI / 180;
                const dLat = (lat - userLat) * radians;
                const dLng = (lng - userLng) * radians;
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(userLat * radians) * Math.cos(lat * radians) * Math.sin(dLng / 2) ** 2;
                const km = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                distance.textContent = km < 1
                    ? `${Math.round(km * 1000)} m from your location`
                    : `${km.toFixed(1)} km from your location`;
                directions.textContent = 'Get Directions';
                directions.disabled = false;
            }, () => {
                distance.textContent = 'Allow location access to see your distance and route from your current location.';
                directions.textContent = 'Get Directions';
                directions.disabled = false;
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        });
        return marker;
    },
};
