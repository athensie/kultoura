/* =========================================================
   KULTOURA — ADMIN DASHBOARD (SITE CONTENT) — SCRIPTS
   ========================================================= */

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ---------- MODAL HELPERS ---------- */
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  if (id === 'pagePreviewModal') {
    const frame = document.getElementById('pagePreviewFrame');
    frame.src = 'about:blank';
  }
}
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

/* ---------- Hero Photos: "Preview page" popup instead of navigating away ---------- */
let currentPreviewHighlight = null;

function openPagePreview(url, label, highlightSelector) {
  currentPreviewHighlight = highlightSelector || null;
  document.getElementById('pagePreviewTitle').textContent = label;
  document.getElementById('pagePreviewOpenNew').href = url;
  document.getElementById('previewFrameLoading').style.display = 'flex';
  document.getElementById('pagePreviewFrame').src = url;
  setPreviewSize('desktop');
  openModal('pagePreviewModal');
}

function setPreviewSize(size) {
  const wrap = document.getElementById('previewFrameWrap');
  wrap.classList.toggle('is-mobile', size === 'mobile');
  document.querySelectorAll('.preview-size-btn[data-size]').forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.size === size);
  });
}

function handlePreviewFrameLoad(iframe) {
  document.getElementById('previewFrameLoading').style.display = 'none';
  if (iframe.src === 'about:blank') return;
  injectPreviewHighlight(iframe, currentPreviewHighlight);
}

// Reaches into the (same-origin) preview iframe and marks the exact section
// this photo slot controls, so the admin doesn't have to guess where on the
// page it lands.
function injectPreviewHighlight(iframe, selector) {
  if (!selector) return;
  try {
    const doc = iframe.contentDocument;
    const target = doc && doc.querySelector(selector);
    if (!target) return;

    if (!doc.getElementById('kt-preview-style')) {
      const style = doc.createElement('style');
      style.id = 'kt-preview-style';
      style.textContent = `
        .kt-preview-highlight { outline: 4px solid #E8A842 !important; outline-offset: -4px; animation: kt-preview-pulse 1.6s ease-in-out infinite; }
        @keyframes kt-preview-pulse { 0%, 100% { outline-color: #E8A842; } 50% { outline-color: #fff6e0; } }
        .kt-preview-badge { position: absolute; top: 14px; left: 14px; z-index: 99999; display: inline-flex; align-items: center; gap: 5px; background: #E8A842; color: #1A1208; font: 700 11px/1 'DM Sans', Arial, sans-serif; letter-spacing: .4px; text-transform: uppercase; padding: 7px 12px; border-radius: 999px; box-shadow: 0 4px 14px rgba(0,0,0,.35); pointer-events: none; }
      `;
      doc.head.appendChild(style);
    }

    target.classList.add('kt-preview-highlight');
    if (!target.querySelector(':scope > .kt-preview-badge')) {
      const badge = doc.createElement('div');
      badge.className = 'kt-preview-badge';
      badge.textContent = 'Photo changes here';
      target.appendChild(badge);
    }

    target.scrollIntoView({ block: 'start', behavior: 'instant' });
  } catch (e) {
    // Same-origin access can still fail (timing, sandboxing) — the preview
    // itself keeps working, it just won't be highlighted.
  }
}

function openAddSection() {
  openModal('addSectionModal');
}

function openEditSection(section) {
  document.getElementById('editSectionId').value = section.id;
  document.getElementById('editSectionTitle').value = section.title;
  document.getElementById('editSectionIcon').value = section.icon_key;
  document.getElementById('editSectionBody').value = section.body;

  const removeRow = document.getElementById('editSectionRemoveImageRow');
  const removeCheckbox = removeRow.querySelector('input[type="checkbox"]');
  removeCheckbox.checked = false;
  removeRow.hidden = !section.hasImage;

  openModal('editSectionModal');
}

/* ---------- TOAST (flash message support, same pattern as other admin pages) ---------- */
let toastTimer = null;
function showToast(msg) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

/* ---------- Hero Photos: highlight the jump-nav pill for the group in view ---------- */
(function () {
  const jumpLinks = document.querySelectorAll('.hero-group-jump a[data-group]');
  const groups = document.querySelectorAll('.hero-photo-group');
  if (!jumpLinks.length || !groups.length || !('IntersectionObserver' in window)) return;

  const linkByGroup = new Map();
  jumpLinks.forEach((link) => linkByGroup.set(link.dataset.group, link));

  const setActive = (id) => {
    jumpLinks.forEach((link) => link.classList.toggle('is-active', link.dataset.group === id));
  };

  const observer = new IntersectionObserver((entries) => {
    const visible = entries.filter((e) => e.isIntersecting);
    if (visible.length) setActive(visible[0].target.id);
  }, { rootMargin: '-96px 0px -70% 0px', threshold: 0 });

  groups.forEach((group) => observer.observe(group));
  setActive(groups[0].id);
})();

/* ---------- About Sections: drag-and-drop reorder (replaces the up/down arrows) ---------- */
(function () {
  const list = document.getElementById('sectionList');
  if (!list) return;

  let dragEl = null;

  const clearDropMarkers = () => {
    list.querySelectorAll('.section-row').forEach((r) => r.classList.remove('drag-over-top', 'drag-over-bottom'));
  };

  list.addEventListener('dragstart', (e) => {
    const row = e.target.closest('.section-row');
    if (!row) return;
    dragEl = row;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', row.dataset.id);
    setTimeout(() => row.classList.add('is-dragging'), 0);
  });

  list.addEventListener('dragover', (e) => {
    if (!dragEl) return;
    e.preventDefault();
    const row = e.target.closest('.section-row');
    if (!row || row === dragEl) return;
    const rect = row.getBoundingClientRect();
    const isAfter = (e.clientY - rect.top) > rect.height / 2;
    clearDropMarkers();
    row.classList.add(isAfter ? 'drag-over-bottom' : 'drag-over-top');
  });

  list.addEventListener('drop', (e) => {
    if (!dragEl) return;
    e.preventDefault();
    const row = e.target.closest('.section-row');
    if (!row || row === dragEl) return;
    const rect = row.getBoundingClientRect();
    const isAfter = (e.clientY - rect.top) > rect.height / 2;
    row.parentNode.insertBefore(dragEl, isAfter ? row.nextSibling : row);
  });

  list.addEventListener('dragend', () => {
    if (dragEl) dragEl.classList.remove('is-dragging');
    clearDropMarkers();
    dragEl = null;
    persistSectionOrder();
  });

  function persistSectionOrder() {
    const ids = Array.from(list.querySelectorAll('.section-row')).map((r) => r.dataset.id);
    if (!ids.length) return;

    const body = new URLSearchParams();
    body.set('action', 'reorder_sections');
    body.set('csrf_token', window.KT_CSRF_TOKEN || '');
    ids.forEach((id) => body.append('order[]', id));

    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    })
      .then((r) => r.json())
      .then((data) => { if (data && data.success) showToast('Order updated'); })
      .catch(() => {});
  }
})();
