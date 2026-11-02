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
