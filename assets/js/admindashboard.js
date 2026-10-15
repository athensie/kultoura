/* =========================================================
   KULTOURA — ADMIN DASHBOARD (ANALYTICS) — SCRIPTS
   ========================================================= */

// PERIOD SELECTOR
// Now real <a href="?period=..."> links handled server-side in
// admindashboard.php (see the PERIOD SELECTOR block there) — no
// client-side toggle needed here anymore.

// MOBILE SIDEBAR TOGGLE
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ANALYTICS TYPE FILTER — show/hide whole sections by their
// data-category (descriptive/predictive/prescriptive), client-side,
// no reload. "All" just clears the filter.
(function initAnalyticsFilter() {
  const bar = document.getElementById('analyticsFilterBar');
  if (!bar) return;
  const sections = document.querySelectorAll('.analytics-section');

  bar.addEventListener('click', function (e) {
    const pill = e.target.closest('.analytics-filter-pill');
    if (!pill) return;

    bar.querySelectorAll('.analytics-filter-pill').forEach(p => p.classList.remove('is-active'));
    pill.classList.add('is-active');

    const filter = pill.dataset.filter;
    sections.forEach(section => {
      const show = filter === 'all' || section.dataset.category === filter;
      section.classList.toggle('is-hidden', !show);
    });
  });
})();

// LUCIDE ICONS INIT
(function initLucide() {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
})();