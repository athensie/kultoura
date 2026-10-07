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
        csrf_token: KT_CSRF_TOKEN,
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

/* ---------- MODALS ---------- */
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('open');
}
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
}
function closeModalOutside(event, id) {
  if (event.target.id === id) closeModal(id);
}

/* ---------- CHANGE PASSWORD ---------- */
// Submitting doesn't change the password right away — it emails the
// admin an Approve/Reject link (see settings_actions.php). On success
// the modal swaps its form for a "check your email" panel instead of
// just closing, so it's clear nothing has changed yet.
function openChangePasswordModal() {
  document.getElementById('changePasswordForm').hidden = false;
  document.getElementById('changePasswordPending').hidden = true;
  openModal('changePasswordModal');
}

async function submitChangePassword(event) {
  event.preventDefault();
  const form = event.target;

  try {
    const response = await fetch('/kultoura/admin/settings_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'change_password',
        current_password: form.current_password.value,
        csrf_token: KT_CSRF_TOKEN,
      }),
    });

    const data = await response.json();
    if (data.success) {
      form.reset();
      document.getElementById('changePasswordPendingMsg').textContent =
        data.message || "Check your email to approve this password change.";
      form.hidden = true;
      document.getElementById('changePasswordPending').hidden = false;
    } else {
      showToast(data.message || 'Could not send the approval email.');
    }
  } catch (err) {
    console.error(err);
    showToast('Could not send the approval email. Try again.');
  }
  return false;
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