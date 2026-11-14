(function () {
    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = '../../assets/css/reviews.css?v=20261114-2';
    document.head.appendChild(stylesheet);

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
    const stars = (count) => '\u2605'.repeat(Number(count) || 0) + '\u2606'.repeat(5 - (Number(count) || 0));

    async function decorateListingRatings() {
        const page = location.pathname.split('/').pop().replace(/\.php$/i, '').toLowerCase();
        const type = ({ products: 'product', restaurants: 'restaurant', fiestas: 'fiesta' })[page] || page;
        const allowed = ['product', 'restaurant', 'fiesta', 'nature', 'resort', 'industry', 'accommodation', 'church', 'service'];
        if (!allowed.includes(type)) return;
        const cards = Array.from(document.querySelectorAll('.p-card[data-id]'));
        const ids = [...new Set(cards.map((card) => Number(card.dataset.id)).filter((id) => id > 0))];
        if (!ids.length) return;
        try {
            const params = new URLSearchParams({ item_type: type, summary_ids: ids.join(',') });
            const response = await fetch(`../reviews_api.php?${params}`, { credentials: 'same-origin', cache: 'no-store' });
            const data = await response.json();
            if (!data.success) return;
            cards.forEach((card) => {
                const summary = data.summaries?.[card.dataset.id];
                if (!summary || !summary.count) return;
                const body = card.querySelector('.p-card-body');
                if (!body || body.querySelector('.kt-card-rating')) return;
                const badge = document.createElement('div');
                badge.className = 'kt-card-rating';
                badge.setAttribute('aria-label', `${Number(summary.average).toFixed(1)} out of 5 stars from ${summary.count} reviews`);
                badge.innerHTML = `<span class="kt-card-rating-stars" aria-hidden="true">${stars(Math.round(Number(summary.average)))}</span><span>${Number(summary.average).toFixed(1)}</span><span class="kt-card-rating-count">(${Number(summary.count)} ${Number(summary.count) === 1 ? 'review' : 'reviews'})</span>`;
                const actions = body.querySelector('.p-card-actions');
                if (actions) body.insertBefore(badge, actions);
                else body.appendChild(badge);
            });
        } catch (_) { /* The item detail panel can still load its ratings. */ }
    }
    const listingStyles = document.createElement('style');
    listingStyles.textContent = '.kt-card-rating{display:flex;align-items:center;gap:6px;margin:9px 0 2px;color:#8f321b;font-size:.88rem;font-weight:700}.kt-card-rating-stars{color:#d49b31;letter-spacing:.05em}.kt-card-rating-count{color:#8d8176;font-size:.8rem;font-weight:500}';
    document.head.appendChild(listingStyles);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', decorateListingRatings);
    else decorateListingRatings();
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
                <span class="kt-review-rating-hint">Click the selected star again to clear your rating.</span>
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
        panel.querySelector('[data-review-average]').textContent = data.count ? Number(data.average).toFixed(1) : '\u2014';
        panel.querySelector('[data-review-stars]').textContent = data.count ? stars(Math.round(Number(data.average))) : '\u2606\u2606\u2606\u2606\u2606';
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
        const canReport = document.body.dataset.loggedIn === '1';
        const hasSamples = data.reviews.some((review) => review.is_sample);
        list.innerHTML = `${hasSamples ? '<p class="kt-review-sample-note">Illustrative feedback is included for demonstration.</p>' : ''}` + data.reviews.map((review) => `
            <article class="kt-review-entry">
                <div class="kt-review-entry-head"><strong>${esc(review.display_name)}</strong><time>${esc(new Date(review.created_at.replace(' ', 'T')).toLocaleDateString())}</time></div>
                <div class="kt-review-stars" aria-label="${Number(review.rating)} out of 5 stars">${stars(review.rating)}</div>
                <p>${esc(review.comment)}</p>
                ${canReport && !review.is_sample ? `<button type="button" class="kt-review-report-toggle" data-review-id="${Number(review.review_id)}">Report</button>
                <form class="kt-review-report-form" hidden data-review-id="${Number(review.review_id)}">
                    <label>Reason <select name="reason" required><option value="">Choose a reason</option><option value="spam">Spam</option><option value="harassment">Harassment or bullying</option><option value="hate">Hateful content</option><option value="sexual">Sexual content</option><option value="personal_info">Personal information</option><option value="misinformation">Misleading information</option><option value="other">Other</option></select></label>
                    <textarea name="details" maxlength="1000" rows="2" placeholder="Additional details (optional)"></textarea>
                    <div><button type="submit" class="kt-review-submit">Send report</button> <button type="button" class="kt-review-report-cancel">Cancel</button></div>
                    <p class="kt-review-report-status" aria-live="polite"></p>
                </form>` : ''}
            </article>
        `).join('');
        list.querySelectorAll('.kt-review-report-toggle').forEach((button) => {
            button.onclick = () => {
                const form = button.nextElementSibling;
                form.hidden = !form.hidden;
            };
        });
        list.querySelectorAll('.kt-review-report-cancel').forEach((button) => {
            button.onclick = () => { button.closest('form').hidden = true; };
        });
        list.querySelectorAll('.kt-review-report-form').forEach((form) => {
            form.onsubmit = async (event) => {
                event.preventDefault();
                const submit = form.querySelector('[type="submit"]');
                const status = form.querySelector('.kt-review-report-status');
                const body = new URLSearchParams(new FormData(form));
                body.set('action', 'report');
                const modal = modalForReviews();
                body.set('item_type', modal?.dataset.reviewType || '');
                body.set('item_id', modal?.dataset.reviewItemId || '');
                body.set('review_id', form.dataset.reviewId);
                submit.disabled = true;
                status.textContent = 'Sending report…';
                try {
                    const response = await fetch('../reviews_api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
                    const result = await response.json();
                    status.textContent = result.message || 'Could not send report.';
                    if (result.success) { form.reset(); form.hidden = true; }
                } catch (_) { status.textContent = 'Could not send report. Please try again.'; }
                finally { submit.disabled = false; }
            };
        });
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
                const label = input.closest('label');
                label.addEventListener('pointerdown', () => { input.dataset.wasChecked = input.checked ? '1' : '0'; });
                input.addEventListener('keydown', (event) => {
                    if (event.key === ' ' || event.key === 'Enter') input.dataset.wasChecked = input.checked ? '1' : '0';
                });
                input.addEventListener('click', (event) => {
                    if (input.dataset.wasChecked !== '1') return;
                    event.preventDefault();
                    window.setTimeout(() => {
                        input.checked = false;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }, 0);
                });
                input.onchange = () => {
                    const selected = reviewForm.querySelector('input[name="rating"]:checked');
                    reviewForm.querySelectorAll('.kt-review-star-picker label').forEach((starLabel) => {
                        starLabel.classList.toggle('is-selected', !!selected && Number(starLabel.querySelector('input').value) <= Number(selected.value));
                    });
                };
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
