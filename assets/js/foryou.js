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
                b.setAttribute('aria-label', data.favorited ? 'Remove from favorites' : 'Save to favorites');
                b.title = data.favorited ? 'Remove from favorites' : 'Save to favorites';
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
async function fyShare(btn, link) {
    const url = new URL(link, window.location.href).href;
    const card = btn.closest('.fy-card');
    const nameEl = card && card.querySelector('.fy-card-name');
    const name = nameEl ? nameEl.textContent : document.title;
    if (navigator.share) {
        try {
            await navigator.share({ title: name, text: `Check out ${name} on KulToura!`, url });
        } catch (err) {
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
document.addEventListener('click', function (e) {
    const viewBtn = e.target.closest('.fy-view-btn');
    if (!viewBtn) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (!fyDetailModal) return;
    e.preventDefault();
    fyDetailModal.open(viewBtn.dataset.itemType, viewBtn.dataset.itemId);
});
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
(function () {
    const sortSelect = document.getElementById('fySortSelect');
    const grid = document.getElementById('fyRecommendedGrid');
    if (!sortSelect || !grid) return;
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
