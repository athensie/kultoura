// KULTOURA — Navbar search bar: expand/collapse, debounced live dropdown,
// Enter (or the "View all results" link) goes to the full results page.
// Self-contained — only needs the .navbar-search markup to be present.
(function () {
    const root = document.querySelector('.navbar-search');
    if (!root) return;

    const toggleBtn = root.querySelector('.navbar-search-toggle');
    const form = root.querySelector('.navbar-search-form');
    const input = root.querySelector('.navbar-search-input');
    const dropdown = root.querySelector('.navbar-search-dropdown');

    // Every page's depth differs (root vs pages/ vs pages/tourism/), so
    // the markup carries the already-correct relative paths itself.
    const API_PATH = root.dataset.api || 'search_api.php';
    const RESULTS_PATH = root.dataset.results || 'search.php';

    let debounceTimer = null;
    let currentQuery = '';

    function openSearch() {
        root.classList.add('is-open');
        toggleBtn.setAttribute('aria-expanded', 'true');
        setTimeout(() => input.focus(), 50);
    }

    function closeSearch() {
        root.classList.remove('is-open');
        toggleBtn.setAttribute('aria-expanded', 'false');
        closeDropdown();
    }

    function closeDropdown() {
        dropdown.classList.remove('is-open');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function renderResults(results, query) {
        if (!results.length) {
            dropdown.innerHTML = `<div class="navbar-search-empty">No matches for "${escapeHtml(query)}".</div>`;
            dropdown.classList.add('is-open');
            return;
        }

        const items = results.map((r) => `
            <a class="navbar-search-result" href="${r.link}">
                <span class="navbar-search-result-thumb">${r.image ? `<img src="${r.image}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">` : escapeHtml(r.name.charAt(0))}</span>
                <span class="navbar-search-result-text">
                    <span class="navbar-search-result-name">${escapeHtml(r.name)}</span>
                    <span class="navbar-search-result-badge">${escapeHtml(r.badgeText)}</span>
                </span>
            </a>
        `).join('');

        dropdown.innerHTML = items + `<a class="navbar-search-viewall" href="${RESULTS_PATH}?q=${encodeURIComponent(query)}">View all results →</a>`;
        dropdown.classList.add('is-open');
    }

    async function runSearch(query) {
        currentQuery = query;
        if (query.trim().length < 2) {
            closeDropdown();
            return;
        }

        dropdown.innerHTML = '<div class="navbar-search-loading">Searching…</div>';
        dropdown.classList.add('is-open');

        try {
            const res = await fetch(API_PATH + '?q=' + encodeURIComponent(query), { cache: 'no-store' });
            const data = await res.json();
            // The user may have kept typing while this request was in
            // flight — only render if this is still the latest query.
            if (query === currentQuery) {
                renderResults(data.results || [], query);
            }
        } catch (err) {
            if (query === currentQuery) {
                dropdown.innerHTML = '<div class="navbar-search-empty">Search is unavailable right now.</div>';
                dropdown.classList.add('is-open');
            }
        }
    }

    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (root.classList.contains('is-open')) {
            closeSearch();
        } else {
            openSearch();
        }
    });

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const value = this.value;
        debounceTimer = setTimeout(() => runSearch(value), 250);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const value = input.value.trim();
        if (value !== '') {
            window.location.href = RESULTS_PATH + '?q=' + encodeURIComponent(value);
        }
    });

    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) closeSearch();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.classList.contains('is-open')) closeSearch();
    });
})();
