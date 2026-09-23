// KULTOURA — Shared navbar hamburger toggle (mobile).
// Included on every page that uses the shared navbar markup.
(function () {
    function closeNavbar(navbar) {
        navbar.classList.remove('nav-open');
        const toggle = navbar.querySelector('.navbar-hamburger');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.navbar-hamburger');
        if (toggle) {
            const navbar = toggle.closest('.navbar');
            if (!navbar) return;
            const isOpen = navbar.classList.toggle('nav-open');
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
