(function () {
    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = '../../assets/css/reviews.css?v=20261104-1';
    document.head.appendChild(stylesheet);

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
    const stars = (count) => '★'.repeat(Number(count) || 0) + '☆'.repeat(5 - (Number(count) || 0));

    function modalForReviews() {
        return document.querySelector('#ktViewDetailsModal, #viewDetailsModal, #pViewModal');
    }

    function ensureReviewPanel(modal) {
        const card = modal && modal.querySelector('.p-modal-card');
        if (!card) return null;
        if (card.querySelector('.kt-review-panel')) return card.querySelector('.kt-review-panel');
        const closeButton = card.querySelector('.p-modal-close');
        const layout = document.createElement('div');
        layout.className = 'kt-reviews-layout';
        const details = document.createElement('div');
        details.className = 'kt-reviews-details';
        const panel = document.createElement('aside');
        panel.className = 'kt-review-panel';
        panel.setAttribute('aria-label', 'Ratings and reviews');
        panel.innerHTML = `
            <header class="kt-review-header">
                <p class="kt-review-eyebrow">COMMUNITY VOICES</p>
                <h3>Ratings &amp; Reviews</h3>
                <div class="kt-review-average"><span data-review-average>—</span><span class="kt-review-stars" data-review-stars>☆☆☆☆☆</span><span data-review-count>0 reviews</span></div>
            </header>
            <div class="kt-review-filters" role="group" aria-label="Filter reviews by star rating">
                <button type="button" class="is-active" data-review-filter="0">All</button>
                ${[5, 4, 3, 2, 1].map((rating) => `<button type="button" data-review-filter="${rating}">${rating} ★ <span data-filter-count="${rating}">0</span></button>`).join('')}
            </div>
            <div class="kt-review-list" aria-live="polite"><p class="kt-review-empty">Loading reviews…</p></div>
            <form class="kt-review-form">
                <h4>Share your experience</h4>
                <span class="kt-review-rating-label">Your rating</span>
                <div class="kt-review-star-picker" role="radiogroup" aria-label="Choose a rating from one to five stars">
                    ${[1, 2, 3, 4, 5].map((rating) => `<label title="${rating} ${rating === 1 ? 'star' : 'stars'}"><input type="radio" name="rating" value="${rating}" required aria-label="${rating} ${rating === 1 ? 'star' : 'stars'}"><span aria-hidden="true">★</span></label>`).join('')}
                </div>
                <label for="ktReviewComment">Comment</label>
                <textarea id="ktReviewComment" name="comment" rows="3" minlength="3" maxlength="1500" placeholder="What should other visitors know?" required></textarea>
                <button type="submit" class="kt-review-submit">Submit review</button>
                <p class="kt-review-form-status" aria-live="polite"></p>
            </form>
        `;
        Array.from(card.children).forEach((child) => {
            if (child !== closeButton) details.appendChild(child);
        });
        layout.append(details, panel);
        if (closeButton) card.append(closeButton);
        card.append(layout);
        card.classList.add('kt-has-reviews');
        return panel;
    }

    function renderReviews(panel, data) {
        panel.querySelector('[data-review-average]').textContent = data.count ? Number(data.average).toFixed(1) : '—';
        panel.querySelector('[data-review-stars]').textContent = data.count ? stars(Math.round(Number(data.average))) : '☆☆☆☆☆';
        panel.querySelector('[data-review-count]').textContent = `${data.count} ${data.count === 1 ? 'review' : 'reviews'}`;
        [1, 2, 3, 4, 5].forEach((rating) => {
            const node = panel.querySelector(`[data-filter-count="${rating}"]`);
            if (node) node.textContent = data.counts?.[rating] ?? 0;
        });
        const list = panel.querySelector('.kt-review-list');
        if (!data.reviews?.length) {
            list.innerHTML = '<p class="kt-review-empty">No reviews for this rating yet. Be the first to share your experience.</p>';
            return;
        }
        list.innerHTML = data.reviews.map((review) => `
            <article class="kt-review-entry">
                <div class="kt-review-entry-head"><strong>${esc(review.display_name)}</strong><time>${esc(new Date(review.created_at.replace(' ', 'T')).toLocaleDateString())}</time></div>
                <div class="kt-review-stars" aria-label="${Number(review.rating)} out of 5 stars">${stars(review.rating)}</div>
                <p>${esc(review.comment)}</p>
            </article>
        `).join('');
    }

    window.ktReviewsOpen = async function (type, itemId) {
        const modal = modalForReviews();
        const panel = ensureReviewPanel(modal);
        if (!panel || !itemId) return;
        modal.dataset.reviewType = type;
        modal.dataset.reviewItemId = itemId;
        panel.querySelector('.kt-review-list').innerHTML = '<p class="kt-review-empty">Loading reviews…</p>';
        const form = panel.querySelector('.kt-review-form');
        const loginUrl = '../../auth/login.php';
        if (document.body.dataset.loggedIn !== '1') {
            form.innerHTML = `<h4>Share your experience</h4><p class="kt-review-signin">Sign in to rate this item and leave a comment.</p><a class="kt-review-submit kt-review-login" href="${loginUrl}">Sign in to review</a>`;
        }
        const filterButtons = panel.querySelectorAll('[data-review-filter]');
        filterButtons.forEach((button) => {
            button.onclick = () => {
                filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                loadReviews(panel, type, itemId, Number(button.dataset.reviewFilter));
            };
        });
        const reviewForm = panel.querySelector('form.kt-review-form');
        if (reviewForm) {
            reviewForm.reset();
            const status = reviewForm.querySelector('.kt-review-form-status');
            if (status) status.textContent = '';
            reviewForm.querySelectorAll('input[name="rating"]').forEach((input) => {
                input.onchange = () => reviewForm.querySelectorAll('.kt-review-star-picker label').forEach((label) => {
                    label.classList.toggle('is-selected', Number(label.querySelector('input').value) <= Number(input.value));
                });
            });
            reviewForm.onreset = () => reviewForm.querySelectorAll('.kt-review-star-picker label').forEach((label) => label.classList.remove('is-selected'));
            reviewForm.onsubmit = (event) => submitReview(event, panel, type, itemId);
        }
        await loadReviews(panel, type, itemId, 0);
    };

    async function loadReviews(panel, type, itemId, starsFilter) {
        const list = panel.querySelector('.kt-review-list');
        try {
            const params = new URLSearchParams({ item_type: type, item_id: itemId, stars: starsFilter });
            const response = await fetch(`../reviews_api.php?${params}`, { credentials: 'same-origin', cache: 'no-store' });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Reviews are unavailable.');
            renderReviews(panel, data);
        } catch (error) {
            list.innerHTML = `<p class="kt-review-empty">${esc(error.message || 'Could not load reviews.')}</p>`;
        }
    }

    async function submitReview(event, panel, type, itemId) {
        event.preventDefault();
        const form = event.currentTarget;
        const status = form.querySelector('.kt-review-form-status');
        const submit = form.querySelector('[type="submit"]');
        const body = new URLSearchParams(new FormData(form));
        body.set('item_type', type);
        body.set('item_id', itemId);
        submit.disabled = true;
        status.textContent = 'Submitting…';
        try {
            const response = await fetch('../reviews_api.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body
            });
            const data = await response.json();
            status.textContent = data.message || (data.success ? 'Review saved.' : 'Could not save review.');
            if (data.success) {
                form.reset();
                panel.querySelector('[data-review-filter="0"]').click();
            }
        } catch (error) {
            status.textContent = 'Could not submit your review. Please try again.';
        } finally {
            submit.disabled = false;
        }
    }
})();
