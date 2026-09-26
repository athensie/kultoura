// KULTOURA — Homepage (index.php)
// Hero CTA buttons, "For You" preview carousel + category pills,
// newsletter stub, and the scroll-to-top footer button.

/* ---------------- Hero CTA buttons ---------------- */
(function () {
    const exploreBtn = document.getElementById('exploreBtn');
    const recommendBtn = document.getElementById('recommendBtn');
    if (exploreBtn) exploreBtn.addEventListener('click', () => { window.location.href = 'pages/tourism.php'; });
    if (recommendBtn) recommendBtn.addEventListener('click', () => { window.location.href = 'pages/foryou.php'; });
})();

/* ---------------- For You preview: category pills + carousel ---------------- */
(function () {
    const pillBar = document.getElementById('homePills');
    const carousel = document.getElementById('homeCarousel');
    const prevBtn = document.getElementById('homeCarouselPrev');
    const nextBtn = document.getElementById('homeCarouselNext');
    const noResults = document.getElementById('homeNoResults');
    if (!carousel) return;

    if (pillBar) {
        pillBar.addEventListener('click', (e) => {
            const pill = e.target.closest('.home-pill');
            if (!pill) return;

            pillBar.querySelectorAll('.home-pill').forEach((p) => p.classList.toggle('is-active', p === pill));

            const category = pill.dataset.category;
            let visibleCount = 0;
            carousel.querySelectorAll('.home-card').forEach((card) => {
                const show = category === 'all' || card.dataset.category === category;
                card.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (noResults) noResults.hidden = visibleCount !== 0;
            carousel.scrollTo({ left: 0, behavior: 'smooth' });
        });
    }

    function scrollByCard(direction) {
        const card = carousel.querySelector('.home-card');
        const step = card ? card.getBoundingClientRect().width + 24 : 280;
        carousel.scrollBy({ left: step * direction, behavior: 'smooth' });
    }

    if (prevBtn) prevBtn.addEventListener('click', () => scrollByCard(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => scrollByCard(1));
})();

/* ---------------- Newsletter form (no backend yet — just a friendly ack) ---------------- */
(function () {
    const form = document.getElementById('homeNewsletterForm');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const input = form.querySelector('input[type="email"]');
        if (!input || !input.value.trim()) return;
        input.value = '';
        input.placeholder = "Thanks — you're on the list!";
    });
})();

/* ---------------- Footer accordion (collapsed on mobile) ---------------- */
function toggleFooterAccordion(heading) {
    const col = heading.closest('.footer-accordion');
    if (col) col.classList.toggle('is-open');
}

/* ---------------- Scroll to top ---------------- */
(function () {
    const btn = document.getElementById('homeScrollTop');
    if (!btn) return;
    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();

/* ---------------- Scroll-hint jumps to the next section ---------------- */
(function () {
    const btn = document.getElementById('scrollHintBtn');
    const target = document.querySelector('.home-foryou');
    if (!btn || !target) return;
    btn.addEventListener('click', () => {
        target.scrollIntoView({ behavior: 'smooth' });
    });
})();

/* ---------------- Navbar gains a shadow once the page is scrolled ---------------- */
(function () {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    const onScroll = () => navbar.classList.toggle('is-scrolled', window.scrollY > 10);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();

/* ---------------- Fade sections in as they enter the viewport ---------------- */
(function () {
    const revealEls = document.querySelectorAll('.reveal');
    if (!revealEls.length) return;

    if (!('IntersectionObserver' in window)) {
        revealEls.forEach((el) => el.classList.add('in-view'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach((el) => observer.observe(el));
})();
