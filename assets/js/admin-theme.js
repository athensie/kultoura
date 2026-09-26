/* =========================================================
   KULTOURA — ADMIN DASHBOARD — DARK / LIGHT MODE TOGGLE
   Shared across every admin page. Pairs with the inline
   FOUC-prevention snippet in each page's <head> and the
   .theme-toggle markup in the sidebar footer.
   ========================================================= */

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  const toggle = document.getElementById('themeToggle');
  if (toggle) toggle.dataset.active = theme;
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  const next = current === 'light' ? 'dark' : 'light';
  try { localStorage.setItem('kt-admin-theme', next); } catch (e) {}
  applyTheme(next);
}

(function () {
  const current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  document.addEventListener('DOMContentLoaded', () => applyTheme(current));
})();
