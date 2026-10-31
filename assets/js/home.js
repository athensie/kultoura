(function () {
    const exploreBtn = document.getElementById('exploreBtn');
    const recommendBtn = document.getElementById('recommendBtn');
    if (exploreBtn) exploreBtn.addEventListener('click', () => { window.location.href = 'pages/tourism.php'; });
    if (recommendBtn) recommendBtn.addEventListener('click', () => { window.location.href = 'pages/foryou.php'; });
})();
(function () {
    const form = document.getElementById('homeNewsletterForm');
    if (!form) return;
    const input = form.querySelector('input[type="email"]');
    const btn = form.querySelector('button[type="submit"]');
    const msg = document.getElementById('homeNewsletterMsg');
    function show(text, cls) {
        if (!msg) return;
        msg.hidden = false;
        msg.className = 'home-newsletter-msg ' + (cls || '');
        msg.textContent = text;
    }
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = input.value.trim();
        if (!email) return;
        btn.disabled = true;
        show('Subscribing...', '');
        try {
            const res = await fetch('index.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'newsletter_subscribe', email })
            });
            const data = await res.json();
            show(data.message, data.ok ? 'is-success' : 'is-error');
            if (data.ok) input.value = '';
        } catch (err) {
            show('Something went wrong. Please try again.', 'is-error');
        }
        btn.disabled = false;
    });
})();
function toggleFooterAccordion(heading) {
    const col = heading.closest('.footer-accordion');
    if (col) col.classList.toggle('is-open');
}
(function () {
    const btn = document.getElementById('homeScrollTop');
    if (!btn) return;
    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    const toggleVisible = () => btn.classList.toggle('is-visible', window.scrollY > 400);
    window.addEventListener('scroll', toggleVisible, { passive: true });
    toggleVisible();
})();
(function () {
    const btn = document.getElementById('scrollHintBtn');
    const target = document.querySelector('.home-foryou');
    if (!btn || !target) return;
    btn.addEventListener('click', () => {
        target.scrollIntoView({ behavior: 'smooth' });
    });
})();
(function () {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    const onScroll = () => navbar.classList.toggle('is-scrolled', window.scrollY > 10);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
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
