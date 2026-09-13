// KULTOURA — Travel Diary page
// Checklist toggling, photo upload, notes, timeline delete, gallery
// lightbox, and search/filter over the checklist.

async function tdPost(action, data) {
    try {
        const res = await fetch('traveldiary_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action, ...data })
        });
        return await res.json();
    } catch (err) {
        return { success: false, message: 'Network error. Please try again.' };
    }
}

let _tdToastTimer = null;
function tdToast(message) {
    const toast = document.getElementById('tdToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(_tdToastTimer);
    _tdToastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

/* ---------------- Visit confirmation modal ----------------
   Two jobs: (1) if the browser's geolocation says the user is near the
   place, logging a visit is one click; (2) if it can't confirm that
   (denied/unsupported/too far/no coordinates on file, e.g. "person"
   items), it asks them to confirm and pick the actual date — covers
   both "I'm not there right now but I've definitely been" and "I
   forgot to check in yesterday, let me log it now" (also how repeat
   visits — e.g. today AND yesterday — get logged on separate dates). */
const tdVisitModal = (function () {
    const overlay = document.getElementById('tdVisitOverlay');
    if (!overlay) return null;

    const spinner = document.getElementById('tdVisitSpinner');
    const iconEl = document.getElementById('tdVisitIcon');
    const titleEl = document.getElementById('tdVisitTitle');
    const subEl = document.getElementById('tdVisitSub');
    const dateEl = document.getElementById('tdVisitDate');
    const closeBtn = document.getElementById('tdVisitClose');
    const askActions = document.getElementById('tdVisitAskActions');
    const confirmActions = document.getElementById('tdVisitConfirmActions');
    const noBtn = document.getElementById('tdVisitNo');
    const yesBtn = document.getElementById('tdVisitYes');
    const cancelBtn = document.getElementById('tdVisitCancel');
    const submitBtn = document.getElementById('tdVisitSubmit');

    const GEOFENCE_METERS = 1000; // generous — GPS drift + resorts/parks are large

    let current = null; // { itemType, itemId, placeName, lat, lng, onCancel, onSuccess }

    function todayStr() {
        const d = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    function haversineMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = (d) => (d * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function showChecking() {
        spinner.hidden = false;
        iconEl.hidden = true;
        dateEl.hidden = true;
        askActions.hidden = true;
        confirmActions.hidden = true;
        titleEl.textContent = 'Checking your location…';
        subEl.textContent = `Seeing if you're near ${current.placeName}.`;
    }

    function showAsk() {
        spinner.hidden = true;
        iconEl.hidden = false;
        iconEl.textContent = '?';
        iconEl.className = 'td-visit-icon td-visit-icon-warn';
        titleEl.textContent = `We couldn't confirm you're at ${current.placeName} right now.`;
        subEl.textContent = "Are you sure you've already been there?";
        dateEl.hidden = true;
        askActions.hidden = false;
        confirmActions.hidden = true;
    }

    function showConfirm(nearby) {
        spinner.hidden = true;
        iconEl.hidden = false;
        iconEl.textContent = nearby ? '✓' : '';
        iconEl.className = nearby ? 'td-visit-icon td-visit-icon-ok' : 'td-visit-icon';
        titleEl.textContent = nearby ? `You're at ${current.placeName}!` : `When did you visit ${current.placeName}?`;
        subEl.textContent = nearby
            ? 'Log this visit for:'
            : "Maybe you forgot to check in that day — pick the actual date.";
        dateEl.hidden = false;
        dateEl.max = todayStr();
        dateEl.value = todayStr();
        askActions.hidden = true;
        confirmActions.hidden = false;
    }

    function open(place) {
        current = place;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        showChecking();

        if (place.lat == null || place.lng == null || !('geolocation' in navigator)) {
            showAsk();
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                if (!current) return; // modal was closed while we waited
                const dist = haversineMeters(pos.coords.latitude, pos.coords.longitude, place.lat, place.lng);
                showConfirm(dist <= GEOFENCE_METERS);
            },
            () => { if (current) showAsk(); },
            { timeout: 8000, maximumAge: 60000 }
        );
    }

    function close(cancelled) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        if (cancelled && current && current.onCancel) current.onCancel();
        current = null;
    }

    async function submitVisit() {
        if (!current) return;
        submitBtn.disabled = true;
        const res = await tdPost('mark_visited', {
            item_type: current.itemType,
            item_id: current.itemId,
            visited_date: dateEl.value || todayStr(),
        });
        submitBtn.disabled = false;
        if (res.success) {
            location.reload();
        } else {
            tdToast(res.message || 'Something went wrong.');
        }
    }

    closeBtn.addEventListener('click', () => close(true));
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(true); });
    noBtn.addEventListener('click', () => close(true));
    yesBtn.addEventListener('click', () => showConfirm(false));
    cancelBtn.addEventListener('click', () => close(true));
    submitBtn.addEventListener('click', submitVisit);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('open')) close(true);
    });

    return { open };
})();

function tdPlaceFromRow(el) {
    const row = el.closest('.td-check-row');
    return {
        placeName: (row && row.dataset.place) || 'this place',
        lat: row && row.dataset.lat ? parseFloat(row.dataset.lat) : null,
        lng: row && row.dataset.lng ? parseFloat(row.dataset.lng) : null,
    };
}

/* ---------------- Checklist: mark / unmark visited ---------------- */
document.querySelectorAll('.td-checkbox').forEach((checkbox) => {
    checkbox.addEventListener('change', async function () {
        const itemType = this.dataset.itemType;
        const itemId = this.dataset.itemId;

        if (this.checked) {
            if (!tdVisitModal) return; // no modal on the page — nothing to open
            const place = tdPlaceFromRow(this);
            tdVisitModal.open({
                itemType, itemId, ...place,
                onCancel: () => { this.checked = false; },
            });
        } else {
            const res = await tdPost('unmark_visited', { item_type: itemType, item_id: itemId });
            if (res.success) {
                location.reload();
            } else {
                this.checked = true;
                tdToast(res.message || "Couldn't unmark that.");
            }
        }
    });
});

/* ---------------- Checklist: log another visit ---------------- */
document.querySelectorAll('.td-log-again-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
        if (!tdVisitModal) return;
        const place = tdPlaceFromRow(this);
        tdVisitModal.open({ itemType: this.dataset.itemType, itemId: this.dataset.itemId, ...place });
    });
});

/* ---------------- Checklist: upload photo ---------------- */
document.querySelectorAll('.td-upload-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
        const itemType = this.dataset.itemType;
        const itemId = this.dataset.itemId;

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png, image/jpeg, image/webp, image/gif';

        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file) return;

            btn.disabled = true;
            const originalLabel = btn.textContent;
            btn.textContent = 'Uploading…';

            const formData = new FormData();
            formData.append('action', 'upload_photo');
            formData.append('item_type', itemType);
            formData.append('item_id', itemId);
            formData.append('photo', file);

            try {
                const res = await fetch('traveldiary_actions.php', { method: 'POST', body: formData }).then((r) => r.json());
                if (res.success) {
                    location.reload();
                } else {
                    tdToast(res.message || 'Upload failed.');
                    btn.disabled = false;
                    btn.textContent = originalLabel;
                }
            } catch (err) {
                tdToast('Upload failed. Please try again.');
                btn.disabled = false;
                btn.textContent = originalLabel;
            }
        });

        input.click();
    });
});

/* ---------------- Checklist: notes (save on blur) ---------------- */
document.querySelectorAll('.td-note-input').forEach((textarea) => {
    let originalValue = textarea.value;
    textarea.addEventListener('blur', async function () {
        if (this.value === originalValue) return;
        const res = await tdPost('save_note', {
            item_type: this.dataset.itemType,
            item_id: this.dataset.itemId,
            note: this.value,
        });
        if (res.success) {
            originalValue = this.value;
            tdToast('Note saved.');
        } else {
            tdToast(res.message || "Couldn't save that note.");
        }
    });
});

/* ---------------- Checklist: toggle note/photo panel ---------------- */
document.querySelectorAll('.td-check-toggle-note').forEach((btn) => {
    btn.addEventListener('click', function () {
        const panel = this.closest('.td-check-row').querySelector('.td-check-expand');
        if (panel) panel.hidden = !panel.hidden;
    });
});

/* ---------------- "View All / Full Timeline / Gallery" expand buttons ---------------- */
function tdWireExpandButton(buttonId, hiddenSelector) {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.addEventListener('click', function () {
        document.querySelectorAll(hiddenSelector).forEach((el) => el.classList.remove('td-hidden-extra'));
        this.remove();
    });
}
tdWireExpandButton('tdExpandChecklist', '#tdChecklist .td-hidden-extra');
tdWireExpandButton('tdExpandTimeline', '.td-timeline .td-hidden-extra');
tdWireExpandButton('tdExpandGallery', '.td-gallery .td-hidden-extra');

/* ---------------- Travel Wrapped: period picker + slideshow ---------------- */
(function () {
    const periodOverlay = document.getElementById('twPeriodOverlay');
    const periodCloseBtn = document.getElementById('twPeriodClose');

    const overlay = document.getElementById('twOverlay');
    const slidesEl = document.getElementById('twSlides');
    const badge = document.getElementById('twBadge');
    const prevBtn = document.getElementById('twPrev');
    const nextBtn = document.getElementById('twNext');
    const closeBtn = document.getElementById('twClose');

    /* Step 1: picking a time range (Weekly / Monthly / Yearly). */
    function openPicker() {
        if (!periodOverlay) return;
        periodOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closePicker() {
        if (!periodOverlay) return;
        periodOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.td-wrapped-trigger').forEach((btn) => {
        btn.addEventListener('click', openPicker);
    });

    if (periodCloseBtn) periodCloseBtn.addEventListener('click', closePicker);
    if (periodOverlay) {
        periodOverlay.addEventListener('click', (e) => {
            if (e.target === periodOverlay) closePicker();
        });
    }

    /* Step 2: the actual slideshow for the chosen range (page reloads
       with ?wrapped=weekly|monthly|yearly, then auto-opens below). */
    if (!overlay) return;

    const slideCount = slidesEl ? slidesEl.children.length : 0;
    let current = 0;

    function render() {
        if (!slidesEl) return;
        slidesEl.style.transform = `translateX(-${current * 100}%)`;
        if (badge) badge.textContent = `${current + 1}/${slideCount}`;
    }

    function goTo(index) {
        if (!slideCount) return;
        current = Math.max(0, Math.min(slideCount - 1, index));
        render();
    }

    function open() {
        if (typeof TD_CAN_WRAP === 'undefined' || !TD_CAN_WRAP) {
            tdToast('No visits logged for that range yet. Try a different one!');
            return;
        }
        current = 0;
        render();
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
    });

    if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1));

    document.addEventListener('keydown', (e) => {
        if (!overlay.classList.contains('open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight') goTo(current + 1);
        if (e.key === 'ArrowLeft') goTo(current - 1);
    });

    // Swipe support (touch).
    let touchStartX = null;
    if (slidesEl) {
        slidesEl.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        slidesEl.addEventListener('touchend', (e) => {
            if (touchStartX === null) return;
            const deltaX = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(deltaX) > 40) {
                goTo(deltaX < 0 ? current + 1 : current - 1);
            }
            touchStartX = null;
        });
    }

    // Coming back from the picker with a chosen range — skip straight
    // to the slideshow instead of showing the picker again.
    if (typeof TD_WRAPPED_REQUESTED !== 'undefined' && TD_WRAPPED_REQUESTED) {
        open();
    }

    /* ---------------- Save Photo / Share ----------------
       There's no web API that lets a plain site push an image straight
       into a Facebook or Instagram post — Meta only allows that through
       navigator.share's native OS share sheet (real Instagram/Facebook
       targets on phones), or by prompting a manual download. Both are
       wired below; the Facebook button in the markup is a plain web
       share link (works everywhere, doesn't need the image itself). */
    const downloadBtn = document.getElementById('twDownloadBtn');
    const shareBtn = document.getElementById('twShareBtn');
    const periodLabel = (typeof TD_WRAPPED_PERIOD !== 'undefined' && TD_WRAPPED_PERIOD) || 'wrapped';

    async function twCaptureCurrentSlide() {
        if (typeof html2canvas === 'undefined') {
            tdToast("Photo export didn't load — check your connection and try again.");
            return null;
        }
        // Capture the .tw-slide itself, not .tw-slide-inner — the themed
        // background color/gradient lives on the slide, not its child.
        const target = slidesEl && slidesEl.children[current];
        if (!target) return null;
        try {
            return await html2canvas(target, { backgroundColor: null, scale: 2, useCORS: true });
        } catch (err) {
            tdToast("Couldn't capture that slide. Please try again.");
            return null;
        }
    }

    function twCanvasToBlob(canvas) {
        return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    }

    function twDownloadCanvas(canvas) {
        const link = document.createElement('a');
        link.download = `kultoura-wrapped-${periodLabel}-${current + 1}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    if (downloadBtn) {
        downloadBtn.addEventListener('click', async () => {
            downloadBtn.disabled = true;
            const canvas = await twCaptureCurrentSlide();
            if (canvas) twDownloadCanvas(canvas);
            downloadBtn.disabled = false;
        });
    }

    if (shareBtn) {
        shareBtn.addEventListener('click', async () => {
            shareBtn.disabled = true;
            const canvas = await twCaptureCurrentSlide();
            if (!canvas) {
                shareBtn.disabled = false;
                return;
            }
            const blob = await twCanvasToBlob(canvas);
            const file = blob && new File([blob], `kultoura-wrapped-${periodLabel}.png`, { type: 'image/png' });

            if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'My KULTOURA Travel Wrapped',
                        text: 'Check out my Malvar travel wrapped! \u{1F30D}',
                    });
                } catch (err) {
                    // User backed out of the share sheet — not an error.
                }
            } else {
                // Desktop / unsupported browser: no OS share sheet exists,
                // so save the photo and let them upload it manually.
                if (canvas) twDownloadCanvas(canvas);
                tdToast('Photo saved! Upload it to Instagram, or use the Facebook button to share.');
            }
            shareBtn.disabled = false;
        });
    }
})();

/* ---------------- Checklist: search + category filter ---------------- */
(function () {
    const searchInput = document.getElementById('tdSearch');
    const filterBar = document.getElementById('tdFilterBar');
    const checklist = document.getElementById('tdChecklist');
    const noResults = document.getElementById('tdNoResults');
    if (!searchInput || !filterBar || !checklist) return;

    let activeType = 'all';

    function applyFilter() {
        const term = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        checklist.querySelectorAll('.td-check-row').forEach((row) => {
            const matchesType = activeType === 'all' || row.dataset.type === activeType;
            const matchesSearch = !term || row.dataset.name.includes(term);
            const show = matchesType && matchesSearch;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (noResults) noResults.hidden = visibleCount !== 0;
    }

    searchInput.addEventListener('input', applyFilter);

    filterBar.addEventListener('click', (e) => {
        const pill = e.target.closest('.td-filter-pill');
        if (!pill) return;
        filterBar.querySelectorAll('.td-filter-pill').forEach((p) => p.classList.toggle('is-active', p === pill));
        activeType = pill.dataset.type;
        applyFilter();
    });
})();

/* ---------------- Timeline: delete an entry ---------------- */
document.querySelectorAll('.td-timeline-delete').forEach((btn) => {
    btn.addEventListener('click', async function () {
        if (!window.confirm('Remove this visit from your timeline? This also deletes any photos attached to it.')) return;

        this.disabled = true;
        const res = await tdPost('delete_entry', { entry_id: this.dataset.entryId });
        if (res.success) {
            location.reload();
        } else {
            this.disabled = false;
            tdToast(res.message || "Couldn't remove that visit.");
        }
    });
});

/* ---------------- Photo Memories: lightbox ---------------- */
(function () {
    const lightbox = document.getElementById('tdLightbox');
    const lightboxImg = document.getElementById('tdLightboxImg');
    const lightboxCaption = document.getElementById('tdLightboxCaption');
    const closeBtn = document.getElementById('tdLightboxClose');
    if (!lightbox) return;

    document.querySelectorAll('.td-gallery-tile').forEach((tile) => {
        tile.addEventListener('click', () => {
            lightboxImg.src = tile.dataset.image;
            lightboxCaption.textContent = tile.dataset.caption || '';
            lightbox.classList.add('open');
        });
    });

    function closeLightbox() {
        lightbox.classList.remove('open');
        lightboxImg.src = '';
    }

    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
    });
})();
