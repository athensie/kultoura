// KULTOURA — For You page: "Near You" geolocation logic, favorite toggling,
// sharing, filter pills, sort, and double-tap-to-like.
// Expects KULTOURA_PLACES to be defined inline in foryou.php

// Global so it can be called from onclick="" on both the PHP-rendered
// "Because You Explored" cards and the JS-rendered "Near You" cards.
async function fyToggleFavorite(btn) {
    const itemType = btn.dataset.itemType;
    const itemId = btn.dataset.itemId;
    if (!itemType || !itemId) return;

    btn.disabled = true;
    try {
        const res = await fetch('favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'toggle', item_type: itemType, item_id: itemId })
        });
        const data = await res.json();

        if (data.needsLogin) {
            window.location.href = '../auth/login.php';
            return;
        }
        if (data.success) {
            document.querySelectorAll(
                `.fy-fav-btn[data-item-type="${itemType}"][data-item-id="${itemId}"]`
            ).forEach((b) => {
                b.classList.toggle('is-favorited', data.favorited);
                const icon = b.querySelector('.fy-action-icon');
                if (icon) icon.innerHTML = data.favorited ? '&#9829;' : '&#9825;';
            });
        }
    } catch (err) {
        console.error('Favorite toggle failed:', err);
    } finally {
        btn.disabled = false;
    }
}

// Global so it can be called from onclick="" on both the PHP-rendered
// cards and the JS-rendered "Near You" cards. Uses the native share
// sheet where available (mobile browsers, some desktop ones); falls
// back to copying the link so it can still be pasted to a friend.
async function fyShare(btn, link) {
    const url = new URL(link, window.location.href).href;
    const card = btn.closest('.fy-card');
    const nameEl = card && card.querySelector('.fy-card-name');
    const name = nameEl ? nameEl.textContent : document.title;

    if (navigator.share) {
        try {
            await navigator.share({ title: name, text: `Check out ${name} on KulToura!`, url });
        } catch (err) {
            // User cancelled the share sheet — nothing to do.
        }
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        fyShowToast(btn, 'Link copied!');
    } catch (err) {
        window.prompt('Copy this link to share it:', url);
    }
}

function fyShowToast(anchorEl, message) {
    const toast = document.createElement('span');
    toast.className = 'fy-share-toast';
    toast.textContent = message;
    anchorEl.appendChild(toast);
    setTimeout(() => toast.remove(), 1400);
}

function fyEscapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Instagram-style double-tap-to-like on the card content area.
// A plain single click still navigates (via link.href) after a short
// window — if a second click lands on the same link within that window,
// navigation is cancelled and the item is favorited instead (a heart
// burst plays either way, same as Instagram double-tapping an already
// liked photo).
(function () {
    const DOUBLE_TAP_MS = 300;
    let lastTapLink = null;
    let lastTapTime = 0;
    let pendingNav = null;

    function fyHeartBurst(link) {
        const target = link.querySelector('.fy-gcard-media') || link;
        const burst = document.createElement('span');
        burst.className = 'fy-heart-burst';
        burst.innerHTML = '&#9829;';
        target.appendChild(burst);
        burst.addEventListener('animationend', () => burst.remove());
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('.fy-card-link');
        if (!link) return;

        // Let modified/middle clicks behave natively (open in new tab, etc).
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        e.preventDefault();

        const now = Date.now();
        const isDoubleTap = link === lastTapLink && (now - lastTapTime) < DOUBLE_TAP_MS;

        if (isDoubleTap) {
            lastTapLink = null;
            lastTapTime = 0;
            if (pendingNav) {
                clearTimeout(pendingNav);
                pendingNav = null;
            }

            fyHeartBurst(link);
            const card = link.closest('.fy-card');
            const favBtn = card && card.querySelector('.fy-fav-btn');
            if (favBtn && !favBtn.classList.contains('is-favorited')) {
                fyToggleFavorite(favBtn);
            }
        } else {
            lastTapLink = link;
            lastTapTime = now;
            const href = link.href;
            pendingNav = setTimeout(() => {
                pendingNav = null;
                window.location.href = href;
            }, DOUBLE_TAP_MS);
        }
    });
})();

// ── Filter pills (Recommended grid) ──
(function () {
    const filterBar = document.getElementById('fyFilterBar');
    const grid = document.getElementById('fyRecommendedGrid');
    if (!filterBar || !grid) return;

    filterBar.addEventListener('click', function (e) {
        const pill = e.target.closest('.fy-filter-pill');
        if (!pill) return;

        filterBar.querySelectorAll('.fy-filter-pill').forEach(p => p.classList.toggle('is-active', p === pill));

        const group = pill.dataset.group;
        grid.querySelectorAll('.fy-card').forEach(card => {
            card.style.display = (group === 'all' || card.dataset.group === group) ? '' : 'none';
        });
    });
})();

// ── Sort dropdown (Recommended grid) ──
(function () {
    const sortSelect = document.getElementById('fySortSelect');
    const grid = document.getElementById('fyRecommendedGrid');
    if (!sortSelect || !grid) return;

    const originalOrder = Array.from(grid.children);

    sortSelect.addEventListener('change', function () {
        if (sortSelect.value === 'recent') {
            Array.from(grid.children)
                .sort((a, b) => parseInt(b.dataset.created, 10) - parseInt(a.dataset.created, 10))
                .forEach(card => grid.appendChild(card));
        } else {
            originalOrder.forEach(card => grid.appendChild(card));
        }
    });
})();

// ── Near You (full section + sidebar widget) ──
(function () {
    const gate = document.getElementById('fy-location-gate');
    const enableBtn = document.getElementById('fy-enable-location');
    const statusEl = document.getElementById('fy-location-status');
    const nearbyGrid = document.getElementById('fy-nearby-grid');
    const widgetList = document.getElementById('fy-near-widget-list');
    const widgetStatus = document.getElementById('fy-near-widget-status');

    if (!enableBtn) return;

    const NEARBY_RADIUS_KM = 0.1; // 100 meters

    // Haversine formula — distance in km between two lat/lng points
    function distanceKm(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function toRad(deg) {
        return deg * (Math.PI / 180);
    }

    function formatDistance(km) {
        return Math.round(km * 1000) + ' m away';
    }

    function nearbyList(userLat, userLng) {
        return KULTOURA_PLACES
            .filter(p => typeof p.lat === 'number' && typeof p.lng === 'number')
            .map(p => ({ ...p, distance: distanceKm(userLat, userLng, p.lat, p.lng) }))
            .filter(p => p.distance <= NEARBY_RADIUS_KM)
            .sort((a, b) => a.distance - b.distance);
    }

    function renderNearbyGrid(list) {
        if (list.length === 0) {
            nearbyGrid.innerHTML = '';
            statusEl.textContent = "Nothing within 100 m of you right now — try again once you're closer to a listed spot.";
            gate.hidden = false;
            nearbyGrid.hidden = true;
            return;
        }

        nearbyGrid.innerHTML = list.map(p => `
            <div class="fy-card">
                <a class="fy-card-link" href="${p.link}">
                    <div class="fy-gcard-media">
                        <span class="fy-gcard-badge fy-badge-${p.category}">${fyEscapeHtml(p.badgeText)}</span>
                        ${p.image
                            ? `<img src="${p.image}" alt="" loading="lazy">`
                            : `<span class="fy-gcard-initial">${fyEscapeHtml(p.name.charAt(0))}</span>`}
                    </div>
                    <div class="fy-gcard-body">
                        <h3 class="fy-card-name">${fyEscapeHtml(p.name)}</h3>
                        ${p.desc ? `<p class="fy-card-desc">${fyEscapeHtml(p.desc)}</p>` : ''}
                        <p class="fy-gcard-meta">Malvar, Batangas · ${formatDistance(p.distance)}</p>
                    </div>
                </a>
                <div class="fy-card-actions">
                    <button type="button" class="fy-action-btn fy-fav-btn${p.favorited ? ' is-favorited' : ''}"
                            data-item-type="${p.itemType}" data-item-id="${p.itemId}"
                            onclick="fyToggleFavorite(this)">
                        <span class="fy-action-icon">${p.favorited ? '&#9829;' : '&#9825;'}</span> Save
                    </button>
                    <a class="fy-action-btn" href="${p.link}">View</a>
                    <button type="button" class="fy-action-btn" onclick="fyShare(this, '${p.link}')">
                        <span class="fy-action-icon">↗</span> Share
                    </button>
                </div>
            </div>
        `).join('');

        gate.hidden = true;
        nearbyGrid.hidden = false;
    }

    function renderNearWidget(list) {
        if (!widgetList) return;

        if (list.length === 0) {
            widgetList.innerHTML = '';
            widgetStatus.textContent = 'Nothing within 100 m of you right now.';
            widgetStatus.hidden = false;
            return;
        }

        widgetStatus.hidden = true;
        widgetList.innerHTML = list.slice(0, 3).map(p => `
            <a class="fy-near-widget-item" href="${p.link}">
                <div class="fy-near-widget-thumb">
                    ${p.image ? `<img src="${p.image}" alt="">` : fyEscapeHtml(p.name.charAt(0))}
                </div>
                <div class="fy-near-widget-text">
                    <span class="fy-near-widget-name">${fyEscapeHtml(p.name)}</span>
                    <span class="fy-near-widget-dist">${formatDistance(p.distance)}</span>
                </div>
            </a>
        `).join('');
    }

    function renderNearby(userLat, userLng) {
        // Items with no admin-set coordinates (e.g. people profiles) can't
        // be distance-sorted, so they're left out of this section entirely.
        // Only places within NEARBY_RADIUS_KM (100m) of the visitor qualify.
        const list = nearbyList(userLat, userLng);
        renderNearbyGrid(list);
        renderNearWidget(list);
    }

    function requestLocation() {
        if (!navigator.geolocation) {
            statusEl.textContent = "Your browser doesn't support location access.";
            if (widgetStatus) widgetStatus.textContent = "Location isn't supported on this browser.";
            return;
        }

        statusEl.textContent = 'Requesting your location…';
        if (widgetStatus) widgetStatus.textContent = 'Locating…';
        enableBtn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                renderNearby(position.coords.latitude, position.coords.longitude);
            },
            (error) => {
                enableBtn.disabled = false;
                if (error.code === error.PERMISSION_DENIED) {
                    statusEl.textContent = "Location access was denied — you can enable it anytime in your browser settings.";
                    if (widgetStatus) widgetStatus.textContent = 'Location access denied.';
                } else {
                    statusEl.textContent = "Couldn't get your location. Please try again.";
                    if (widgetStatus) widgetStatus.textContent = "Couldn't get your location.";
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    enableBtn.addEventListener('click', requestLocation);

    // If the user already granted permission on a previous visit,
    // show nearby content automatically without prompting again.
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then((result) => {
            if (result.state === 'granted') {
                requestLocation();
            }
        }).catch(() => {});
    }
})();
