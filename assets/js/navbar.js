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
    // "Intent to visit" actions, classified by where the link actually goes (works for links
    // that come from the database too). A data-intent="..." attribute overrides detection.
    const visitIntent = (control, href, lowerLabel) => {
        const declared = (control.dataset.intent || '').toLowerCase();
        if (['directions', 'call', 'message', 'email', 'share', 'social', 'website'].includes(declared)) return declared;
        const link = (href || '').trim().toLowerCase();
        if (link.startsWith('tel:')) return 'call';
        if (link.startsWith('mailto:')) return 'email';
        if (/facebook\.com\/sharer|facebook\.com\/share|twitter\.com\/intent|x\.com\/intent|linkedin\.com\/share|api\.whatsapp\.com\/send\?text|wa\.me\/\?text/.test(link) || /\bshare\b/.test(lowerLabel)) return 'share';
        if (link.startsWith('sms:') || link.startsWith('viber:') || /(^|\/\/)(www\.)?(m\.me|messenger\.com|wa\.me|api\.whatsapp\.com)\//.test(link)) return 'message';
        if (/google\.[a-z.]+\/maps|maps\.google\.|maps\.app\.goo\.gl|goo\.gl\/maps|waze\.com|maps\.apple\.com/.test(link) || (/directions|navigate|get there/.test(lowerLabel) && !/\b(close|hide|cancel|back)\b/.test(lowerLabel))) return 'directions';
        if (/(^|\/\/)([a-z0-9-]+\.)?(facebook\.com|fb\.com|fb\.me|instagram\.com|tiktok\.com|youtube\.com|youtu\.be)\b/.test(link)) return 'social';
        if (/^https?:\/\//.test(link)) {
            try { if (new URL(href).host !== window.location.host) return 'website'; } catch (error) { return ''; }
        }
        return '';
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
        const intent = visitIntent(control, href, lowerLabel);
        if (intent === 'directions') activityType = 'map_interaction';
        const currentPage = pageCategory(window.location.pathname) || window.location.pathname.split('/').pop().replace(/\.php$/i, '');
        send({
            activity_type: activityType,
            intent: intent,
            category: itemType,
            item_type: itemType && itemId ? itemType : '',
            item_id: itemId,
            page: currentPage || 'other',
            element: label || control.tagName.toLowerCase()
        });
    }, { capture: true, passive: true });
})();
