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