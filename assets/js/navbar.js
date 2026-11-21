(function () {
    document.querySelectorAll('.navbar .nav-links').forEach(function (navLinks) {
        const normalizePath = (path) => {
            try { path = decodeURIComponent(path); } catch (err) {}
            return path.replace(/\/+$/, '') || '/';
        };
        const currentPath = normalizePath(window.location.pathname);
        const links = Array.from(navLinks.querySelectorAll('a[href]'));
        links.forEach((link) => {
            link.classList.remove('nav-active', 'nav-sub-active');
            link.removeAttribute('aria-current');
        });
        links.forEach((link) => {
            const rawHref = link.getAttribute('href');
            if (!rawHref || rawHref.startsWith('#')) return;
            let target;
            try { target = new URL(rawHref, window.location.href); } catch (err) { return; }
            if (target.origin !== window.location.origin || normalizePath(target.pathname) !== currentPath) return;
            const isSubmenuLink = link.closest('.mega-column');
            link.classList.add(isSubmenuLink ? 'nav-sub-active' : 'nav-active');
            link.setAttribute('aria-current', 'page');
            const dropdown = link.closest('.dropdown');
            const trigger = dropdown && dropdown.querySelector(':scope > .nav-item');
            if (trigger) trigger.classList.add('nav-active');
        });
    });

    function updateNavbarHeightVar() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            document.documentElement.style.setProperty('--navbar-h', navbar.offsetHeight + 'px');
        }
    }
    updateNavbarHeightVar();
    window.addEventListener('resize', updateNavbarHeightVar);
    let lockedScrollY = 0;
    function lockBodyScroll() {
        lockedScrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = -lockedScrollY + 'px';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.classList.add('nav-menu-open');
    }
    function unlockBodyScroll() {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.classList.remove('nav-menu-open');
        window.scrollTo(0, lockedScrollY);
    }
    function closeNavbar(navbar) {
        navbar.classList.remove('nav-open');
        unlockBodyScroll();
        const toggle = navbar.querySelector('.navbar-hamburger');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.navbar-hamburger');
        if (toggle) {
            const navbar = toggle.closest('.navbar');
            if (!navbar) return;
            const isOpen = navbar.classList.toggle('nav-open');
            if (isOpen) {
                lockBodyScroll();
            } else {
                unlockBodyScroll();
            }
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            return;
        }
        const openNavbar = document.querySelector('.navbar.nav-open');
        if (openNavbar && !openNavbar.contains(e.target)) {
            closeNavbar(openNavbar);
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const openNavbar = document.querySelector('.navbar.nav-open');
        if (openNavbar) closeNavbar(openNavbar);
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            document.querySelectorAll('.navbar.nav-open').forEach(closeNavbar);
        }
    });
})();
(function initFirstVisitTour() {
    const navbar = document.querySelector('.navbar');
    if (!navbar || !navbar.querySelector('.nav-links')) return;

    const storageKey = 'kultouraFirstVisitTourV1';
    try { if (localStorage.getItem(storageKey)) return; } catch (err) {}

    const navItems = Array.from(navbar.querySelectorAll('.nav-links .nav-item'));
    const findItem = (label) => navItems.find((item) => item.textContent.trim().toLowerCase().startsWith(label));
    const steps = [
        { title: 'Welcome to KULTOURA', body: 'Your guide to discovering Malvar, its local places, and the stories behind them.', target: null },
        { title: 'Explore Malvar', body: 'Browse destinations, food, places to stay, local products, fiestas, people, and services.', target: () => findItem('explore malvar') },
        { title: 'Your shortcuts', body: 'Open More for recommendations, favorites, your profile, and Travel Diary tools.', target: () => findItem('more') },
        { title: 'Find something specific', body: 'Use search to look up a place, product, event, or service anywhere on KULTOURA.', target: () => navbar.querySelector('.navbar-search-toggle') },
        { title: 'Ready to explore?', body: 'Choose a place that interests you, save favorites for later, and sign in to keep a Travel Diary of your visits.', target: null }
    ];
    const style = document.createElement('style');
    style.textContent = `
        .kt-tour-root{position:fixed;inset:0;z-index:100000;pointer-events:auto;font-family:Inter,system-ui,sans-serif;color:#2a1a10}
        .kt-tour-root[hidden]{display:none}
        .kt-tour-dim{position:fixed;inset:0;background:rgba(17,20,15,.72)}
        .kt-tour-spot{position:fixed;z-index:1;border:2px solid #e8b86b;border-radius:12px;box-shadow:0 0 0 9999px rgba(17,20,15,.72);pointer-events:none;transition:all .22s ease}
        .kt-tour-card{position:fixed;z-index:2;width:min(390px,calc(100vw - 32px));padding:22px;border:1px solid #eadfce;border-radius:20px;background:#f8f0e4;box-shadow:0 18px 60px rgba(0,0,0,.35)}
        .kt-tour-top{display:flex;justify-content:space-between;align-items:center;gap:12px;color:#587a4c;font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}
        .kt-tour-count{color:#76624f;letter-spacing:.08em;white-space:nowrap}
        .kt-tour-card h2{margin:12px 0 8px;font:700 27px/1.08 Georgia,serif;color:#2a1a10}
        .kt-tour-card p{margin:0;color:#665343;font-size:14px;line-height:1.6}
        .kt-tour-progress{height:4px;margin:18px 0 16px;border-radius:9px;background:#e4d8c6;overflow:hidden}
        .kt-tour-progress i{display:block;height:100%;border-radius:inherit;background:#587a4c;transition:width .2s ease}
        .kt-tour-actions{display:flex;justify-content:space-between;align-items:center;gap:10px}
        .kt-tour-actions button{min-height:42px;padding:0 15px;border:1px solid #d8cbb9;border-radius:999px;background:transparent;color:#382417;font:600 13px Inter,system-ui,sans-serif;cursor:pointer}
        .kt-tour-actions .kt-tour-skip{border:0;padding-left:0;color:#76624f}
        .kt-tour-actions .kt-tour-back:disabled{opacity:.4;cursor:default}
        .kt-tour-actions .kt-tour-next{border-color:#2a1a10;background:#2a1a10;color:#fffaf3}
        .kt-tour-actions button:focus-visible{outline:3px solid #b5472a;outline-offset:3px}
        @media(prefers-reduced-motion:reduce){.kt-tour-spot,.kt-tour-progress i{transition:none}}
    `;
    document.head.appendChild(style);

    const root = document.createElement('div');
    root.className = 'kt-tour-root';
    root.hidden = true;
    root.innerHTML = '<div class="kt-tour-dim"></div><div class="kt-tour-spot" aria-hidden="true"></div><section class="kt-tour-card" role="dialog" aria-modal="true" aria-labelledby="ktTourTitle" aria-describedby="ktTourBody"><div class="kt-tour-top"><span>KULTOURA QUICK TOUR</span><span class="kt-tour-count"></span></div><h2 id="ktTourTitle"></h2><p id="ktTourBody"></p><div class="kt-tour-progress" aria-hidden="true"><i></i></div><div class="kt-tour-actions"><button type="button" class="kt-tour-skip">Skip tour</button><div><button type="button" class="kt-tour-back">Back</button> <button type="button" class="kt-tour-next">Next</button></div></div></section>';
    document.body.appendChild(root);

    const card = root.querySelector('.kt-tour-card');
    const spot = root.querySelector('.kt-tour-spot');
    const title = root.querySelector('#ktTourTitle');
    const body = root.querySelector('#ktTourBody');
    const count = root.querySelector('.kt-tour-count');
    const progress = root.querySelector('.kt-tour-progress i');
    const back = root.querySelector('.kt-tour-back');
    const next = root.querySelector('.kt-tour-next');
    let index = 0;
    let returnFocus = null;

    function finishTour() {
        try { localStorage.setItem(storageKey, 'done'); } catch (err) {}
        root.hidden = true;
        if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
    }

    function positionCard(target) {
        const margin = 16;
        card.style.left = margin + 'px';
        card.style.top = margin + 'px';
        const cardRect = card.getBoundingClientRect();
        if (!target) {
            card.style.left = Math.max(margin, (window.innerWidth - cardRect.width) / 2) + 'px';
            card.style.top = Math.max(margin, (window.innerHeight - cardRect.height) / 2) + 'px';
            return;
        }
        const rect = target.getBoundingClientRect();
        const left = Math.max(margin, Math.min(window.innerWidth - cardRect.width - margin, rect.left + rect.width / 2 - cardRect.width / 2));
        let top = rect.bottom + 14;
        if (top + cardRect.height > window.innerHeight - margin) top = rect.top - cardRect.height - 14;
        card.style.left = left + 'px';
        card.style.top = Math.max(margin, top) + 'px';
    }

    function renderStep() {
        const step = steps[index];
        let target = typeof step.target === 'function' ? step.target() : null;
        if (target && (!target.getClientRects().length || getComputedStyle(target).visibility === 'hidden')) {
            target = navbar.querySelector('.navbar-hamburger') || navbar;
        }
        title.textContent = step.title;
        body.textContent = step.body;
        count.textContent = (index + 1) + ' / ' + steps.length;
        progress.style.width = ((index + 1) / steps.length * 100) + '%';
        back.disabled = index === 0;
        next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
        if (target) {
            const rect = target.getBoundingClientRect();
            spot.hidden = false;
            spot.style.left = Math.max(4, rect.left - 5) + 'px';
            spot.style.top = Math.max(4, rect.top - 5) + 'px';
            spot.style.width = Math.max(24, rect.width + 10) + 'px';
            spot.style.height = Math.max(24, rect.height + 10) + 'px';
            positionCard(target);
        } else {
            spot.hidden = true;
            positionCard(null);
        }
    }

    function startTour() {
        returnFocus = document.activeElement;
        index = 0;
        root.hidden = false;
        renderStep();
        root.querySelector('.kt-tour-next').focus();
    }

    root.querySelector('.kt-tour-skip').addEventListener('click', finishTour);
    back.addEventListener('click', function () { if (index > 0) { index--; renderStep(); } });
    next.addEventListener('click', function () { if (index < steps.length - 1) { index++; renderStep(); } else finishTour(); });
    root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { event.preventDefault(); finishTour(); return; }
        if (event.key !== 'Tab') return;
        const controls = Array.from(card.querySelectorAll('button:not(:disabled)'));
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    window.addEventListener('resize', function () { if (!root.hidden) renderStep(); });
    setTimeout(startTour, 700);
})();
(function initKulTouraActivityTracking() {
    if (window.__ktActivityTrackingBound) return;
    window.__ktActivityTrackingBound = true;
    const scriptUrl = document.currentScript && document.currentScript.src;
    const endpoint = scriptUrl ? new URL('../../pages/track_activity.php', scriptUrl).href : '/kultoura/pages/track_activity.php';
    const itemTypes = new Set(['nature', 'industry', 'resort', 'church', 'product', 'fiesta', 'person', 'restaurant', 'accommodation', 'bank', 'service']);
    const pageCategory = (path) => {
        const name = (path.split('/').pop() || '').replace(/\.php$/i, '').toLowerCase();
        const map = { churches: 'church', products: 'product', fiestas: 'fiesta', people: 'person', restaurants: 'restaurant', services: 'service' };
        return map[name] || (itemTypes.has(name) ? name : '');
    };
    const send = (payload) => {
        const body = new URLSearchParams(payload);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, new Blob([body.toString()], { type: 'application/x-www-form-urlencoded;charset=UTF-8' }));
        } else {
            fetch(endpoint, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(() => {});
        }
    };
    document.addEventListener('click', function (event) {
        const control = event.target.closest('a[href], button, [role="button"], .p-card[data-id]');
        if (!control || control.closest('[data-activity-ignore]') || control.disabled || control.getAttribute('aria-disabled') === 'true') return;
        const href = control.getAttribute('href') || '';
        if (href === '#' || href.startsWith('javascript:')) return;
        const targetPath = href ? new URL(href, window.location.href).pathname : window.location.pathname;
        const card = control.closest('[data-item-type][data-item-id], [data-item-type][data-id], .p-card[data-id], .fy-card[data-item-type][data-item-id]');
        let itemType = card ? (card.dataset.itemType || '') : (control.dataset.itemType || '');
        if (!itemTypes.has(itemType)) itemType = pageCategory(targetPath) || pageCategory(window.location.pathname);
        let itemId = card ? (card.dataset.itemId || card.dataset.id || '') : (control.dataset.itemId || control.dataset.id || '');
        if (!/^\d+$/.test(itemId)) itemId = '';
        const label = (control.getAttribute('aria-label') || control.innerText || control.title || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        const lowerLabel = label.toLowerCase();
        let activityType = 'click';
        if (control.hasAttribute('download') || /\bdownload\b/.test(lowerLabel)) activityType = 'download';
        else if (/\bgenerate\b/.test(lowerLabel)) activityType = 'generate';
        else if (/\bmap\b|navigate|directions/.test(lowerLabel)) activityType = 'map_interaction';
        else if (/recommendation|for you/.test(lowerLabel)) activityType = 'recommendation';
        const currentPage = pageCategory(window.location.pathname) || window.location.pathname.split('/').pop().replace(/\.php$/i, '');
        send({
            activity_type: activityType,
            category: itemType,
            item_type: itemType && itemId ? itemType : '',
            item_id: itemId,
            page: currentPage || 'other',
            element: label || control.tagName.toLowerCase()
        });
    }, { capture: true, passive: true });
})();
