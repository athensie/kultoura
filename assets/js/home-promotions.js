(function () {
    const modal = document.getElementById('homePromoModal');
    if (!modal) return;
    const title = document.getElementById('homePromoTitle');
    const badge = document.getElementById('homePromoBadge');
    const description = document.getElementById('homePromoDescription');
    const imageWrap = modal.querySelector('.home-promo-image-wrap');
    const image = document.getElementById('homePromoImage');
    const link = document.getElementById('homePromoLink');
    let previousOverflow = '';

    function sendEvent(card, eventName) {
        const body = new URLSearchParams({
            item_type: card.dataset.itemType || '',
            item_id: card.dataset.itemId || '',
            event: eventName
        });
        fetch('pages/track_promoted_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
            keepalive: true
        }).catch(() => {});
    }

    function openCard(card) {
        title.textContent = card.dataset.name || '';
        badge.textContent = card.dataset.badge || '';
        description.textContent = card.dataset.description || '';
        link.href = card.dataset.link || '#';
        image.src = card.dataset.image || '';
        image.alt = card.dataset.name || '';
        imageWrap.hidden = !card.dataset.image;
        sendEvent(card, 'click');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.querySelector('.home-promo-close').focus();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousOverflow;
    }

    document.addEventListener('click', function (event) {
        const closeControl = event.target.closest('[data-promo-close]');
        if (closeControl) {
            closeModal();
            return;
        }
        const card = event.target.closest('.home-promoted-card');
        if (card) openCard(card);
    });

    document.addEventListener('keydown', function (event) {
        const card = event.target.closest('.home-promoted-card');
        if (card && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openCard(card);
        } else if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    const cards = document.querySelectorAll('.home-promoted-card');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.4) {
                    sendEvent(entry.target, 'impression');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: [0.4] });
        cards.forEach(card => observer.observe(card));
    } else {
        cards.forEach(card => sendEvent(card, 'impression'));
    }
})();
