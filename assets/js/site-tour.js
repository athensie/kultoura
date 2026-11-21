(function initFirstVisitTour() {
    const navbar = document.querySelector('.navbar');
    const sidebar = document.querySelector('.sidebar');
    const pageRoot = document.querySelector('main, .main-content, .activity-page, .auth-container, .auth-card');
    if (!navbar && !sidebar && !pageRoot) return;

    const pagePath = (location.pathname.replace(/\/+$/, '') || '/').toLowerCase();
    const storageKey = 'kultouraPageTourV2:' + pagePath;
    try { if (localStorage.getItem(storageKey)) return; } catch (err) {}

    const navItems = Array.from(navbar?.querySelectorAll('.nav-links .nav-item') || []);
    const findItem = (label) => navItems.find((item) => item.textContent.trim().toLowerCase().startsWith(label));
    const visible = (element) => !!element && element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden' && Number(getComputedStyle(element).opacity) > 0.05;
    const heading = document.querySelector('.main-content h1, main h1, .activity-page h1, .page-header h1, h1');
    const pageTitle = (heading?.textContent || document.title).replace(/\s+/g, ' ').trim() || 'this page';
    const toolSelectors = [
        '.main-content input[type="search"]', 'main input[type="search"]', '.main-content .filter-bar',
        '.main-content .filters', 'main .filter-bar', 'main .filters', '.main-content form', 'main form',
        '.tw-map-leaflet', 'main .map', '.main-content table', 'main table',
        '.main-content button[type="submit"]', 'main button[type="submit"]'
    ].join(',');
    const tool = Array.from(document.querySelectorAll(toolSelectors)).find(visible);
    const steps = [];
    if (sidebar) {
        steps.push({ title: 'Admin navigation', body: 'The sidebar groups analytics, content, and management tools. The highlighted item is this page.', target: () => sidebar.querySelector('.sidebar-nav a.active, .sidebar-nav a[aria-current="page"]') || sidebar.querySelector('.sidebar-logo') || sidebar });
        steps.push({ title: pageTitle, body: 'This is the page you are on. Use its sections and controls to review or manage KULTOURA.', target: () => heading || document.querySelector('.main-content h2') || document.querySelector('.main-content') });
    } else if (navbar?.querySelector('.nav-links')) {
        steps.push({ title: 'Explore Malvar', body: 'Browse destinations, food, places to stay, local products, fiestas, people, and services.', target: () => findItem('explore malvar') || navbar.querySelector('.navbar-hamburger') });
        if (findItem('more')) steps.push({ title: 'Your shortcuts', body: 'Open More for recommendations, favorites, your profile, and Travel Diary tools.', target: () => findItem('more') || navbar.querySelector('.navbar-hamburger') });
        const search = navbar.querySelector('.navbar-search-toggle');
        if (search) steps.push({ title: 'Find something specific', body: 'Use search to look up a place, product, event, or service anywhere on KULTOURA.', target: () => search });
        steps.push({ title: pageTitle, body: 'You are on this page. Explore its content and use the highlighted controls to find what you need.', target: () => heading || pageRoot });
    } else {
        steps.push({ title: pageTitle, body: 'This is your current KULTOURA page. The highlighted controls show where to begin.', target: () => heading || pageRoot });
    }
    if (tool) steps.push({ title: 'Page tools', body: 'Use this area to search, filter, review, or manage the information on this page.', target: () => tool });
    if (steps.length < 2) steps.push({ title: 'You are ready to continue', body: 'Use the highlighted sections to continue exploring KULTOURA.', target: () => pageRoot || sidebar || navbar });
    const style = document.createElement('style');
    style.textContent = `
        .kt-tour-root{position:fixed;inset:0;z-index:100000;pointer-events:auto;font-family:Inter,system-ui,sans-serif;color:#2a1a10}
        .kt-tour-root[hidden]{display:none}
        .kt-tour-dim{position:fixed;inset:0;background:rgba(17,20,15,.78)}
        .kt-tour-root.kt-tour-has-target .kt-tour-dim{display:none}
        .kt-tour-spot{position:fixed;z-index:1;border:3px solid #f3c778;border-radius:13px;box-shadow:0 0 0 5px rgba(243,199,120,.35),0 0 30px 8px rgba(243,199,120,.65),0 0 0 9999px rgba(17,20,15,.78);pointer-events:none;transition:all .22s ease;animation:ktTourPulse 1.5s ease-in-out infinite alternate}
        .kt-tour-spot:after{content:'LOOK HERE';position:absolute;left:0;bottom:calc(100% + 7px);padding:5px 9px;border-radius:999px;background:#f3c778;color:#2a1a10;font:800 9px/1 Inter,system-ui,sans-serif;letter-spacing:.12em;white-space:nowrap;box-shadow:0 3px 10px #0005}
        @keyframes ktTourPulse{to{box-shadow:0 0 0 8px rgba(243,199,120,.22),0 0 38px 12px rgba(243,199,120,.72),0 0 0 9999px rgba(17,20,15,.78)}}
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
        @media(prefers-reduced-motion:reduce){.kt-tour-spot,.kt-tour-progress i{transition:none;animation:none}}
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
        if (!visible(target)) target = navbar?.querySelector('.navbar-hamburger') || sidebar || pageRoot;
        title.textContent = step.title;
        body.textContent = step.body;
        count.textContent = (index + 1) + ' / ' + steps.length;
        progress.style.width = ((index + 1) / steps.length * 100) + '%';
        back.disabled = index === 0;
        next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
        if (target) {
            root.classList.add('kt-tour-has-target');
            const rect = target.getBoundingClientRect();
            spot.hidden = false;
            spot.style.left = Math.max(4, rect.left - 5) + 'px';
            spot.style.top = Math.max(4, rect.top - 5) + 'px';
            spot.style.width = Math.max(24, rect.width + 10) + 'px';
            spot.style.height = Math.max(24, rect.height + 10) + 'px';
            positionCard(target);
        } else {
            root.classList.remove('kt-tour-has-target');
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
