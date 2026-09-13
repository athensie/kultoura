/* =========================================================
   KULTOURA — ADMIN DASHBOARD (SETTINGS) — SCRIPTS
   ========================================================= */

/* ---------- TOAST ---------- */
let toastTimer = null;
function showToast(msg) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

/* ---------- SETTING TOGGLES ---------- */
// Flips the switch immediately for a responsive feel, fires the real
// request to the backend, then rolls the switch back if the save
// actually failed — so the UI never silently lies about saved state.
async function toggleSetting(btn) {
  const key = btn.dataset.key;
  const wasOn = btn.classList.contains('on');
  const newValue = !wasOn;

  btn.classList.toggle('on');
  btn.classList.add('loading');

  try {
    const response = await fetch('/kultoura/admin/settings_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'toggle_setting',
        key: key,
        value: newValue ? '1' : '0',
      }),
    });

    if (!response.ok) throw new Error('Request failed');

    showToast(formatSettingLabel(key) + (newValue ? ' enabled.' : ' disabled.'));
  } catch (err) {
    console.error(err);
    // Roll back the visual state — the save didn't actually happen.
    btn.classList.toggle('on');
    showToast('Could not save that change. Try again.');
  } finally {
    btn.classList.remove('loading');
  }
}

function formatSettingLabel(key) {
  const labels = {
    public_access: 'Public access',
    maintenance_mode: 'Maintenance mode',
    show_visitor_count: 'Live visitor count',
    email_alerts: 'Email alerts',
    new_listing_alert: 'New listing alert',
    flagged_content_alert: 'Flagged content alert',
    weekly_summary: 'Weekly summary email',
  };
  return labels[key] || 'Setting';
}

/* ---------- PASSWORD RESET ---------- */
async function requestPasswordReset() {
  if (!confirm('Send a password reset link to this admin\'s email?')) return;

  try {
    const response = await fetch('/kultoura/admin/settings_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'send_password_reset' }),
    });

    if (!response.ok) throw new Error('Request failed');
    showToast('Password reset email sent.');
  } catch (err) {
    console.error(err);
    showToast('Could not send the reset email. Try again.');
  }
}

/* ---------- MOBILE SIDEBAR TOGGLE ---------- */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ---------- LUCIDE ICONS INIT ---------- */
(function initLucide() {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
})();