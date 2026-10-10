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

// ── Place detail modal — "View" (and the card itself) opens this
// instead of navigating away, so browsing stays on the For You page.
// Looks the place up from KULTOURA_PLACES by type+id, so it works for
// both the PHP-rendered "Because You Explored" cards and the
// JS-rendered "Near You" ones without a second network round-trip.
const fyDetailModal = (function () {
    const overlay = document.getElementById('fyDetailOverlay');
    if (!overlay) return null;

    const closeBtn = document.getElementById('fyDetailClose');
    const mediaEl = document.getElementById('fyDetailMedia');
    const badgeEl = document.getElementById('fyDetailBadge');
    const nameEl = document.getElementById('fyDetailName');
    const metaEl = document.getElementById('fyDetailMeta');
    const descEl = document.getElementById('fyDetailDesc');
    const favBtn = document.getElementById('fyDetailFavBtn');
    const shareBtn = document.getElementById('fyDetailShareBtn');
    const fullLink = document.getElementById('fyDetailFullLink');

    function findPlace(itemType, itemId) {
        return KULTOURA_PLACES.find((p) => p.itemType === itemType && String(p.itemId) === String(itemId));
    }

    function open(itemType, itemId) {
        const p = findPlace(itemType, itemId);
        if (!p) return;

        mediaEl.innerHTML = '';
        badgeEl.textContent = p.badgeText;
        badgeEl.className = 'fy-gcard-badge fy-badge-' + p.category;
        mediaEl.appendChild(badgeEl);
        if (p.image) {
            const img = document.createElement('img');
            img.src = p.image;
            img.alt = '';
            mediaEl.appendChild(img);
        } else {
            const initial = document.createElement('div');
            initial.className = 'fy-detail-media-initial';
            initial.textContent = p.name.charAt(0);
            mediaEl.appendChild(initial);
        }

        nameEl.textContent = p.name;
        metaEl.textContent = 'Malvar, Batangas' + (p.location ? ' · ' + p.location : '');
        descEl.textContent = p.desc || '';
        descEl.hidden = !p.desc;

        favBtn.dataset.itemType = p.itemType;
        favBtn.dataset.itemId = p.itemId;
        favBtn.classList.toggle('is-favorited', !!p.favorited);
        favBtn.querySelector('.fy-action-icon').innerHTML = p.favorited ? '&#9829;' : '&#9825;';

        shareBtn.onclick = () => fyShare(shareBtn, p.link);
        fullLink.href = p.link;

        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
    });

    return { open, close };
})();

// "View" buttons open the modal (both PHP- and JS-rendered cards share
// this class), falling back to the plain href navigation if the modal
// markup isn't on the page for some reason.
document.addEventListener('click', function (e) {
    const viewBtn = e.target.closest('.fy-view-btn');
    if (!viewBtn) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (!fyDetailModal) return;

    e.preventDefault();
    fyDetailModal.open(viewBtn.dataset.itemType, viewBtn.dataset.itemId);
});

// Instagram-style double-tap-to-like on the card content area. A plain
// single click opens the detail modal (instead of navigating away)
// after a short window — if a second click lands on the same link
// within that window, the modal is skipped and the item is favorited
// instead (a heart burst plays either way, same as Instagram
// double-tapping an already liked photo).
(function () {
    const DOUBLE_TAP_MS = 300;
    let lastTapLink = null;
    let lastTapTime = 0;
    let pendingOpen = null;

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
        const card = link.closest('.fy-card');

        if (isDoubleTap) {
            lastTapLink = null;
            lastTapTime = 0;
            if (pendingOpen) {
                clearTimeout(pendingOpen);
                pendingOpen = null;
            }

            fyHeartBurst(link);
            const favBtn = card && card.querySelector('.fy-fav-btn');
            if (favBtn && !favBtn.classList.contains('is-favorited')) {
                fyToggleFavorite(favBtn);
            }
        } else {
            lastTapLink = link;
            lastTapTime = now;
            pendingOpen = setTimeout(() => {
                pendingOpen = null;
                if (fyDetailModal && card) {
                    fyDetailModal.open(card.dataset.itemType, card.dataset.itemId);
                } else {
                    window.location.href = link.href;
                }
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

    // Re-captured whenever the grid is replaced wholesale (e.g. by
    // applyLocationBoost) — otherwise "Recommended" would try to
    // re-attach cards that no longer exist in the live grid.
    let originalOrder = Array.from(grid.children);
    document.addEventListener('fy:grid-replaced', function () {
        originalOrder = Array.from(grid.children);
        sortSelect.value = 'default';
    });

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

    const NEARBY_RADIUS_KM = 2; // Malvar is a small town — 2km covers a realistic "nearby" radius without just listing everything

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
            statusEl.textContent = "Nothing within 2 km of you right now — try again once you're closer to a listed spot.";
            gate.hidden = false;
            nearbyGrid.hidden = true;
            return;
        }

        nearbyGrid.innerHTML = list.map(p => `
            <div class="fy-card" data-item-type="${p.itemType}" data-item-id="${p.itemId}">
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
                    <a class="fy-action-btn fy-view-btn" href="${p.link}" data-item-type="${p.itemType}" data-item-id="${p.itemId}">View</a>
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
            widgetStatus.textContent = 'Nothing within 2 km of you right now.';
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
        applyLocationBoost(userLat, userLng);
    }

    // ── Blend location into "Because You Explored" ──
    // Each place already carries the server's interaction-based
    // interestScore (see $interestScores in foryou.php). Once geolocation
    // resolves, that's combined with a proximity score (closer = higher,
    // fading out past a 5km soft radius) so "for you" genuinely reflects
    // both signals instead of leaving location to the separate Near You
    // section only. Places with no admin-set coordinates (e.g. people
    // profiles) fall back to interest alone — no proximity signal exists
    // for them.
    const LOCATION_SOFT_RADIUS_KM = 5;
    const recommendedGrid = document.getElementById('fyRecommendedGrid');
    const recommendedHeading = document.querySelector('#fy-recommended h2');

    function applyLocationBoost(userLat, userLng) {
        if (!recommendedGrid || typeof KULTOURA_PLACES === 'undefined') return;

        const scored = KULTOURA_PLACES
            .filter((p) => !p.viewed)
            .map((p) => {
                const hasCoords = typeof p.lat === 'number' && typeof p.lng === 'number';
                const distance = hasCoords ? distanceKm(userLat, userLng, p.lat, p.lng) : null;
                const proximityScore = hasCoords ? Math.max(0, 1 - distance / LOCATION_SOFT_RADIUS_KM) : 0;
                const interestScore = p.interestScore || 0;
                const blended = hasCoords ? (0.6 * interestScore) + (0.4 * proximityScore) : interestScore;
                return { place: p, distance, blended };
            })
            .sort((a, b) => b.blended - a.blended)
            .slice(0, 6);

        if (!scored.length) return;

        recommendedGrid.innerHTML = scored.map(({ place: p, distance }) => `
            <div class="fy-card" data-group="${p.group}" data-created="${p.createdAt}" data-item-type="${p.itemType}" data-item-id="${p.itemId}">
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
                        <p class="fy-gcard-meta">Malvar, Batangas${distance !== null ? ' · ' + formatDistance(distance) : (p.location ? ' · ' + fyEscapeHtml(p.location) : '')}</p>
                    </div>
                </a>
                <div class="fy-card-actions">
                    <button type="button" class="fy-action-btn fy-fav-btn${p.favorited ? ' is-favorited' : ''}"
                            data-item-type="${p.itemType}" data-item-id="${p.itemId}"
                            onclick="fyToggleFavorite(this)">
                        <span class="fy-action-icon">${p.favorited ? '&#9829;' : '&#9825;'}</span> Save
                    </button>
                    <a class="fy-action-btn fy-view-btn" href="${p.link}" data-item-type="${p.itemType}" data-item-id="${p.itemId}">View</a>
                    <button type="button" class="fy-action-btn" onclick="fyShare(this, '${p.link}')">
                        <span class="fy-action-icon">↗</span> Share
                    </button>
                </div>
            </div>
        `).join('');

        if (recommendedHeading) recommendedHeading.textContent = 'Because You Explored — Near You';
        document.dispatchEvent(new CustomEvent('fy:grid-replaced'));
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
