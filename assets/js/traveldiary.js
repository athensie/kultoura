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

    const CHECK_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';

    function showConfirm(nearby) {
        spinner.hidden = true;
        iconEl.hidden = false;
        iconEl.innerHTML = nearby ? CHECK_ICON : '';
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

/* ---------------- Checklist + Timeline: log another visit ---------------- */
document.querySelectorAll('.td-log-again-btn, .td-timeline-log-again').forEach((btn) => {
    btn.addEventListener('click', function () {
        if (!tdVisitModal) return;
        const place = this.classList.contains('td-timeline-log-again')
            ? { placeName: this.dataset.place, lat: this.dataset.lat ? parseFloat(this.dataset.lat) : null, lng: this.dataset.lng ? parseFloat(this.dataset.lng) : null }
            : tdPlaceFromRow(this);
        tdVisitModal.open({ itemType: this.dataset.itemType, itemId: this.dataset.itemId, ...place });
    });
});

/* ---------------- Checklist + Timeline: upload photo ----------------
   One picker click uploads as many photos as you select at once (instead
   of repeating "pick a photo, wait, pick the next one" one at a time),
   with the button showing live progress through the batch. useCamera
   opens the device camera directly (capture="environment") instead of
   the photo library — mobile browsers only; desktop just falls back to
   its normal file picker, so this is safe to set unconditionally. */
async function tdUploadFiles(files, itemType, itemId, btn) {
    if (!files.length) return;

    btn.disabled = true;
    const originalLabel = btn.innerHTML; // innerHTML, not textContent — several of these buttons carry an icon <span>, not just text
    let uploaded = 0;
    let failed = 0;

    for (const file of files) {
        btn.textContent = files.length > 1 ? `Uploading ${uploaded + failed + 1}/${files.length}…` : 'Uploading…';

        const formData = new FormData();
        formData.append('action', 'upload_photo');
        formData.append('item_type', itemType);
        formData.append('item_id', itemId);
        formData.append('photo', file);

        try {
            const res = await fetch('traveldiary_actions.php', { method: 'POST', body: formData }).then((r) => r.json());
            if (res.success) uploaded++; else failed++;
        } catch (err) {
            failed++;
        }
    }

    if (uploaded > 0) {
        location.reload();
        return;
    }

    tdToast(failed === 1 ? 'Upload failed.' : `All ${failed} uploads failed.`);
    btn.disabled = false;
    btn.innerHTML = originalLabel;
}

function tdUploadPhotos(itemType, itemId, btn) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png, image/jpeg, image/webp, image/gif';
    input.multiple = true;
    input.addEventListener('change', () => tdUploadFiles(Array.from(input.files), itemType, itemId, btn));
    input.click();
}

/* Live camera: shows the device camera in-page (rear camera on phones,
   webcam on laptops) and captures a still. Falls back to the OS camera
   via a file input only if the browser can't provide a live stream. */
const tdCamera = (function () {
    const overlay = document.getElementById('tdCameraOverlay');
    const video = document.getElementById('tdCameraVideo');
    const preview = document.getElementById('tdCameraPreview');
    const canvas = document.getElementById('tdCameraCanvas');
    const errorEl = document.getElementById('tdCameraError');
    const shootBtn = document.getElementById('tdCameraShoot');
    const cancelBtn = document.getElementById('tdCameraCancel');
    const closeBtn = document.getElementById('tdCameraClose');
    const flipBtn = document.getElementById('tdCameraFlip');
    const shootActions = document.getElementById('tdCameraShootActions');
    const previewActions = document.getElementById('tdCameraPreviewActions');
    const retakeBtn = document.getElementById('tdCameraRetake');
    const useBtn = document.getElementById('tdCameraUse');
    if (!overlay) return null;

    let stream = null;
    let pending = null; // { itemType, itemId, btn }
    let facingMode = 'environment'; // back camera by default — better for photographing a place
    let capturedBlob = null;
    let capturedUrl = null;

    function stop() {
        if (stream) stream.getTracks().forEach((t) => t.stop());
        stream = null;
        video.srcObject = null;
    }

    function showLive() {
        if (capturedUrl) { URL.revokeObjectURL(capturedUrl); capturedUrl = null; }
        capturedBlob = null;
        preview.hidden = true;
        preview.src = '';
        video.hidden = false;
        if (flipBtn) flipBtn.hidden = false;
        updateFlipAvailability();
        previewActions.hidden = true;
        shootActions.hidden = false;
    }

    function showPreview(blob) {
        capturedBlob = blob;
        capturedUrl = URL.createObjectURL(blob);
        preview.src = capturedUrl;
        preview.hidden = false;
        video.hidden = true;
        if (flipBtn) flipBtn.hidden = true;
        shootActions.hidden = true;
        previewActions.hidden = false;
    }

    function close() {
        stop();
        if (capturedUrl) { URL.revokeObjectURL(capturedUrl); capturedUrl = null; }
        capturedBlob = null;
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        pending = null;
        facingMode = 'environment';
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.hidden = false;
        shootBtn.disabled = true;
    }

    // Mirror the live viewfinder for a front/selfie camera — otherwise
    // moving your hand right shows it moving left, which feels backwards
    // (same reason every video-call app mirrors your own self-view). A
    // phone's actual rear camera is left alone since you're framing a
    // place, not yourself. Only the live preview is mirrored; the frame
    // drawn to canvas on capture reads the unmirrored video data, so the
    // saved photo itself is never flipped.
    function updateMirror(cameraCount) {
        const mirrored = facingMode === 'user' || cameraCount < 2;
        video.classList.toggle('td-camera-mirrored', mirrored);
    }

    // Only offer the flip button when the device actually has more than
    // one camera — otherwise it's a button that can't do anything.
    async function updateFlipAvailability() {
        if (flipBtn) flipBtn.hidden = true;
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            updateMirror(1);
            return;
        }
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const cameraCount = devices.filter((d) => d.kind === 'videoinput').length;
            if (flipBtn) flipBtn.hidden = cameraCount < 2;
            updateMirror(cameraCount);
        } catch (err) {
            // Can't enumerate (older browser, permissions) — leave the
            // flip button hidden rather than offer something that may fail.
            updateMirror(1);
        }
    }

    async function startStream() {
        errorEl.hidden = true;
        shootBtn.disabled = false;
        try {
            const newStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: facingMode } },
                audio: false,
            });
            stop(); // only tear down the old stream once the new one is confirmed working
            stream = newStream;
            video.srcObject = stream;
            updateFlipAvailability();
        } catch (err) {
            showError(err.name === 'NotAllowedError'
                ? 'Camera access was blocked. Allow camera access in your browser settings to take a photo.'
                : "Couldn't open the camera. Try choosing a photo from your library instead.");
        }
    }

    async function open(itemType, itemId, btn) {
        pending = { itemType, itemId, btn };
        facingMode = 'environment';
        errorEl.hidden = true;
        shootBtn.disabled = false;
        if (capturedUrl) { URL.revokeObjectURL(capturedUrl); capturedUrl = null; }
        capturedBlob = null;
        preview.hidden = true;
        preview.src = '';
        video.hidden = false;
        shootActions.hidden = false;
        previewActions.hidden = true;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            close();
            fallbackToFileInput(itemType, itemId, btn);
            return;
        }

        await startStream();
    }

    if (flipBtn) {
        flipBtn.addEventListener('click', () => {
            facingMode = facingMode === 'environment' ? 'user' : 'environment';
            startStream();
        });
    }

    function fallbackToFileInput(itemType, itemId, btn) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.capture = 'environment';
        input.addEventListener('change', () => tdUploadFiles(Array.from(input.files), itemType, itemId, btn));
        input.click();
    }

    shootBtn.addEventListener('click', () => {
        if (!pending || !stream || !video.videoWidth) return;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob((blob) => {
            if (!blob) {
                tdToast("Couldn't capture that photo. Please try again.");
                return;
            }
            showPreview(blob);
        }, 'image/jpeg', 0.9);
    });

    retakeBtn.addEventListener('click', showLive);

    useBtn.addEventListener('click', () => {
        if (!pending || !capturedBlob) return;
        const { itemType, itemId, btn } = pending;
        const file = new File([capturedBlob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg' });
        close();
        tdUploadFiles([file], itemType, itemId, btn);
    });

    cancelBtn.addEventListener('click', close);
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('open')) close();
    });

    return { open };
})();

/* ---------------- Add Photo: camera vs library choice ---------------- */
const tdPhotoChoice = (function () {
    const overlay = document.getElementById('tdPhotoChoiceOverlay');
    if (!overlay) return null;

    const closeBtn = document.getElementById('tdPhotoChoiceClose');
    const cameraBtn = document.getElementById('tdPhotoChoiceCamera');
    const libraryBtn = document.getElementById('tdPhotoChoiceLibrary');

    let pending = null; // { itemType, itemId, btn }

    function open(itemType, itemId, btn) {
        pending = { itemType, itemId, btn };
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        pending = null;
    }

    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('open')) close();
    });

    cameraBtn.addEventListener('click', () => {
        if (!pending) return;
        const { itemType, itemId, btn } = pending;
        close();
        if (tdCamera) tdCamera.open(itemType, itemId, btn);
    });

    libraryBtn.addEventListener('click', () => {
        if (!pending) return;
        const { itemType, itemId, btn } = pending;
        close();
        tdUploadPhotos(itemType, itemId, btn);
    });

    return { open };
})();

document.querySelectorAll('.td-upload-btn, .td-timeline-add-photo, .td-check-photo-btn').forEach((btn) => {
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (tdPhotoChoice) {
            tdPhotoChoice.open(this.dataset.itemType, this.dataset.itemId, this);
        } else {
            tdUploadPhotos(this.dataset.itemType, this.dataset.itemId, this);
        }
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
tdWireExpandButton('tdExpandTimeline', '.td-group-list .td-hidden-extra');
tdWireExpandButton('tdExpandGallery', '.td-gallery .td-hidden-extra');

/* ---------------- Travel Timeline: destination pop-open ----------------
   Clicking a folder card flies it (DOM-wise, via the Web Animations API)
   from its exact on-page rect to a responsive, top-anchored open rect —
   a FLIP-style animation: capture the starting rect, move the real row
   into the backdrop, then interpolate left/top/width/height from start
   to end while the cover's color morphs, the right page flips into
   view, and the tab/depth-sliver fade in once the cover has mostly
   arrived. A comment-node placeholder marks where the row came from so
   closing can fly it back to the exact same spot in the grid. Only one
   folder is open at a time, closed via its × button, the backdrop, or
   Escape. The open folder's identity is persisted to sessionStorage so
   it re-opens automatically (instantly, no flight) across the
   location.reload() that mark_visited/upload_photo/delete_entry already
   do (see tdPost calls below) — otherwise logging another visit from
   inside an open folder would silently close it back on reload. */
(function () {
    const list = document.getElementById('tdGroupList');
    const backdrop = document.getElementById('tdFolderBackdrop');
    if (!list || !backdrop) return;

    const STORAGE_KEY = 'tdExpandedGroup';
    const REDUCE_MOTION = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';
    const D_OPEN = 650;
    const D_CLOSE = 500;

    let placeholder = null; // marks the open row's spot in the grid while it's moved into the backdrop
    let busy = false;

    function readExpandedKey() {
        try { return sessionStorage.getItem(STORAGE_KEY) || null; } catch (err) { return null; }
    }

    function writeExpandedKey(key) {
        try {
            if (key) sessionStorage.setItem(STORAGE_KEY, key);
            else sessionStorage.removeItem(STORAGE_KEY);
        } catch (err) {
            // Private browsing / storage disabled — the open folder just
            // won't survive a reload, nothing else depends on it.
        }
    }

    function px(r) {
        return { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' };
    }

    // Responsive + top-anchored: capped by viewport, sitting near the top
    // instead of dead-center, so it reads as "the folder opened right
    // where you clicked" rather than jumping to the middle of the screen.
    function finalRect(height) {
        const margin = 16;
        const width = Math.min(880, innerWidth - margin * 2);
        const top = Math.max(margin, Math.min(innerHeight * 0.05, 48));
        const maxHeight = innerHeight - top - margin;
        return { left: (innerWidth - width) / 2, top, width, height: Math.min(height, maxHeight) };
    }

    // How tall the open folder wants to be for ITS OWN content (varies —
    // more visits/photos need more room) — measured off-screen so nothing
    // visibly flashes before the real animation starts.
    function measureNaturalHeight(row, width) {
        const prevStyle = row.getAttribute('style');
        row.style.cssText = `position:fixed; visibility:hidden; left:-9999px; top:0; width:${width}px; height:auto;`;
        const height = row.getBoundingClientRect().height;
        if (prevStyle === null) row.removeAttribute('style'); else row.setAttribute('style', prevStyle);
        return height;
    }

    // Where the row would naturally sit back in the grid right now — used
    // as the close animation's target, re-measured each time (rather than
    // reusing the rect captured at open-time) so it's still correct even
    // if search/sort/resize reflowed the grid while the folder was open.
    function measureCollapsedRect(row) {
        if (!placeholder || !placeholder.parentNode) return null;
        const hadExpanded = row.classList.contains('is-expanded');
        const prevStyle = row.getAttribute('style');
        row.classList.remove('is-expanded');
        row.style.cssText = 'visibility:hidden;';
        placeholder.parentNode.insertBefore(row, placeholder);
        const rect = row.getBoundingClientRect();
        backdrop.appendChild(row);
        if (prevStyle === null) row.removeAttribute('style'); else row.setAttribute('style', prevStyle);
        if (hadExpanded) row.classList.add('is-expanded');
        return rect;
    }

    function setPageRightFolded(row, folded) {
        const pageRight = row.querySelector('.td-folder-page-right');
        if (pageRight) pageRight.style.cssText = folded ? 'opacity:0; transform:rotateY(-65deg);' : '';
    }

    function setAccentsVisible(row, visible) {
        const tab = row.querySelector('.td-folder-tab');
        if (tab) tab.style.cssText = visible ? '' : 'opacity:0; transform:scale(0.7);';
        const close = row.querySelector('.td-group-close');
        if (close) close.style.opacity = visible ? '' : '0';
    }

    function folderColors(row) {
        const cs = getComputedStyle(row);
        return {
            closed: cs.getPropertyValue('--td-folder-closed').trim(),
            open: cs.getPropertyValue('--td-folder-open').trim(),
        };
    }

    function settleRow(row, animations) {
        animations.forEach((a) => { try { a.commitStyles(); } catch (err) {} a.cancel(); });
        row.style.opacity = '';
        setPageRightFolded(row, false);
        setAccentsVisible(row, true);
    }

    function closeAll(instant) {
        if (busy && !instant) return Promise.resolve(); // don't interrupt an in-flight open/close

        const row = backdrop.querySelector('.td-group-row.is-expanded');
        if (!row) {
            placeholder = null;
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
            return Promise.resolve();
        }

        const finish = () => {
            row.classList.remove('is-expanded');
            row.querySelectorAll('.td-folder-page-left, .td-folder-page-right').forEach((page) => { page.hidden = true; });
            row.querySelector('.td-group-header')?.setAttribute('aria-expanded', 'false');
            row.removeAttribute('style');
            setPageRightFolded(row, false);
            setAccentsVisible(row, true);
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(row, placeholder);
            } else {
                list.appendChild(row);
            }
            if (placeholder && placeholder.parentNode) placeholder.remove();
            placeholder = null;
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
        };

        if (instant || REDUCE_MOTION) {
            finish();
            return Promise.resolve();
        }

        busy = true;
        const to = measureCollapsedRect(row) || row.getBoundingClientRect();
        const from = row.getBoundingClientRect();
        const colors = folderColors(row);

        const animations = [
            row.animate([{ ...px(from), opacity: 1 }, { ...px(to), opacity: 0 }], { duration: D_CLOSE, easing: EASE_OUT, fill: 'both' }),
            row.animate([{ backgroundColor: colors.open }, { backgroundColor: colors.closed }], { duration: D_CLOSE, fill: 'both' }),
        ];
        const pageRight = row.querySelector('.td-folder-page-right');
        if (pageRight) {
            animations.push(pageRight.animate(
                [{ opacity: 1, transform: 'rotateY(0deg)' }, { opacity: 0, transform: 'rotateY(-65deg)' }],
                { duration: D_CLOSE * 0.55, easing: 'ease-in', fill: 'both' }
            ));
        }
        [row.querySelector('.td-folder-tab'), row.querySelector('.td-group-close')].forEach((el) => {
            if (!el) return;
            animations.push(el.animate([{ opacity: 1 }, { opacity: 0 }], { duration: D_CLOSE * 0.3, fill: 'both' }));
        });
        backdrop.classList.remove('open');

        return Promise.all(animations.map((a) => a.finished)).catch(() => {}).then(() => {
            animations.forEach((a) => { try { a.cancel(); } catch (err) {} });
            finish();
            busy = false;
        });
    }

    function openRow(row, instant) {
        if (busy) return;
        const alreadyOpen = backdrop.querySelector('.td-group-row.is-expanded');
        const doOpen = () => {
            busy = true;
            const from = row.getBoundingClientRect();

            placeholder = document.createComment('td-folder-slot');
            row.parentNode.insertBefore(placeholder, row);
            backdrop.appendChild(row);

            row.classList.add('is-expanded');
            row.querySelectorAll('.td-folder-page-left, .td-folder-page-right').forEach((page) => { page.hidden = false; });
            row.querySelector('.td-group-header')?.setAttribute('aria-expanded', 'true');
            backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
            writeExpandedKey(row.dataset.groupKey);

            const naturalHeight = measureNaturalHeight(row, finalRect(0).width);
            const to = finalRect(naturalHeight);

            if (instant || REDUCE_MOTION) {
                row.removeAttribute('style');
                Object.assign(row.style, { position: 'fixed' }, px(to));
                busy = false;
                return;
            }

            row.removeAttribute('style');
            Object.assign(row.style, { position: 'fixed', opacity: '0' }, px(from));
            setPageRightFolded(row, true);
            setAccentsVisible(row, false);
            row.getBoundingClientRect(); // force the browser to register the "from" state before animating

            const colors = folderColors(row);
            const animations = [
                row.animate([{ ...px(from), opacity: 0 }, { ...px(to), opacity: 1 }], { duration: D_OPEN, easing: EASE_OUT, fill: 'both' }),
                row.animate([{ backgroundColor: colors.closed }, { backgroundColor: colors.open }], { duration: D_OPEN, fill: 'both' }),
            ];
            const pageRight = row.querySelector('.td-folder-page-right');
            if (pageRight) {
                animations.push(pageRight.animate(
                    [{ opacity: 0, transform: 'rotateY(-65deg)' }, { opacity: 1, transform: 'rotateY(0deg)' }],
                    { duration: D_OPEN * 0.5, delay: D_OPEN * 0.4, easing: EASE_OUT, fill: 'both' }
                ));
            }
            [row.querySelector('.td-folder-tab'), row.querySelector('.td-group-close')].forEach((el) => {
                if (!el) return;
                animations.push(el.animate(
                    [{ opacity: 0, transform: 'scale(0.7)' }, { opacity: 1, transform: 'scale(1)' }],
                    { duration: D_OPEN * 0.35, delay: D_OPEN * 0.55, easing: EASE_OUT, fill: 'both' }
                ));
            });

            Promise.all(animations.map((a) => a.finished)).catch(() => {}).then(() => {
                settleRow(row, animations);
                busy = false;
            });
        };

        if (alreadyOpen && alreadyOpen !== row) {
            closeAll().then(doOpen);
        } else if (!alreadyOpen) {
            doOpen();
        }
    }

    function toggleRow(row) {
        if (row.classList.contains('is-expanded')) {
            closeAll();
            writeExpandedKey(null);
        } else {
            openRow(row);
        }
    }

    list.querySelectorAll('.td-group-row').forEach((row) => {
        row.dataset.groupKey = row.dataset.name + '|' + row.dataset.latestDate; // stable enough without a dedicated id attribute
    });

    const savedKey = readExpandedKey();
    if (savedKey) {
        const match = Array.from(list.querySelectorAll('.td-group-row')).find((r) => r.dataset.groupKey === savedKey);
        if (match) openRow(match, true); // instant — a reload shouldn't replay the fly-in
    }

    // Keep the open folder correctly sized/positioned if the viewport
    // changes (rotate, resize, zoom) while it's open.
    let resizeFrame = null;
    addEventListener('resize', () => {
        if (busy) return;
        const row = backdrop.querySelector('.td-group-row.is-expanded');
        if (!row) return;
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => {
            const naturalHeight = measureNaturalHeight(row, finalRect(0).width);
            Object.assign(row.style, px(finalRect(naturalHeight)));
        });
    });

    // Delegated on document, not just the grid — once a card is moved into
    // the backdrop it's no longer a descendant of #tdGroupList, so clicks
    // on its close button / header there would never reach a listener
    // scoped to the grid.
    document.addEventListener('click', (e) => {
        const closeBtn = e.target.closest('.td-group-close');
        if (closeBtn) {
            closeAll();
            writeExpandedKey(null);
            return;
        }
        const header = e.target.closest('.td-group-header');
        if (header) {
            const row = header.closest('.td-group-row');
            if (row) toggleRow(row);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) {
            closeAll();
            writeExpandedKey(null);
            return;
        }
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const header = e.target.closest('.td-group-header');
        if (!header) return;
        e.preventDefault();
        const row = header.closest('.td-group-row');
        if (row) toggleRow(row);
    });

    // Only the backdrop's own background should close it — a click inside
    // the open card still bubbles up to this listener (the card is now
    // nested inside the backdrop), so it must be filtered out here.
    backdrop.addEventListener('click', (e) => {
        if (e.target !== backdrop) return;
        closeAll();
        writeExpandedKey(null);
    });
})();

/* ---------------- Travel Timeline: Visit History row expand ----------------
   Inside an open folder, each visit's own date row collapses its note and
   Remove button until clicked (photos now show inline in the row header
   itself, always visible) — delegated on document since these rows live
   inside the backdrop once their folder is popped open. */
(function () {
    document.addEventListener('click', (e) => {
        const head = e.target.closest('.td-visit-row-head');
        if (!head) return;
        const body = head.nextElementSibling;
        if (!body || !body.classList.contains('td-visit-row-body')) return;
        const expanded = body.hidden;
        body.hidden = !expanded;
        head.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const head = e.target.closest('.td-visit-row-head');
        if (!head) return;
        e.preventDefault();
        head.click();
    });
})();

/* ---------------- Travel Timeline: Visit History pagination ----------------
   Each open folder's Visit History list pages itself (PAGE_SIZE rows at a
   time) once it has more than one page's worth, instead of growing into a
   long scroll — dots below the list jump between pages. Scoped per
   .td-visit-history instance so each diary group paginates independently;
   initialized here at load time since the rows are all server-rendered
   upfront (all visible), same as the rest of this feature. */
(function () {
    const PAGE_SIZE = 3;

    function buildPagination(history) {
        const rows = Array.from(history.querySelectorAll('.td-visit-row'));
        const pagination = history.nextElementSibling;
        if (!pagination || !pagination.classList.contains('td-visit-pagination')) return;

        const pageCount = Math.ceil(rows.length / PAGE_SIZE);
        if (pageCount <= 1) {
            pagination.hidden = true;
            return;
        }

        rows.forEach((row, i) => {
            row.style.display = Math.floor(i / PAGE_SIZE) === 0 ? '' : 'none';
        });

        pagination.innerHTML = '';
        for (let p = 0; p < pageCount; p++) {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'td-visit-dot' + (p === 0 ? ' is-active' : '');
            dot.dataset.page = p;
            dot.setAttribute('aria-label', `Visit history page ${p + 1} of ${pageCount}`);
            pagination.appendChild(dot);
        }
        pagination.hidden = false;
    }

    document.querySelectorAll('.td-visit-history').forEach(buildPagination);

    document.addEventListener('click', (e) => {
        const dot = e.target.closest('.td-visit-dot');
        if (!dot) return;
        const pagination = dot.closest('.td-visit-pagination');
        const history = pagination && pagination.previousElementSibling;
        if (!history || !history.classList.contains('td-visit-history')) return;

        const page = Number(dot.dataset.page);
        Array.from(history.querySelectorAll('.td-visit-row')).forEach((row, i) => {
            row.style.display = Math.floor(i / PAGE_SIZE) === page ? '' : 'none';
        });
        pagination.querySelectorAll('.td-visit-dot').forEach((d) => d.classList.toggle('is-active', d === dot));
    });
})();

/* ---------------- Travel Timeline: Photos "View All" ----------------
   Per-folder reveal (unlike tdWireExpandButton, which is for page-wide
   singletons) — each open folder has its own Photos section and its own
   button, so this is scoped to whichever .td-folder-page-right the
   clicked button lives in. */
(function () {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.td-photos-viewall');
        if (!btn) return;
        const pageRight = btn.closest('.td-folder-page-right');
        if (!pageRight) return;
        pageRight.querySelectorAll('.td-folder-photos .td-hidden-extra').forEach((el) => el.classList.remove('td-hidden-extra'));
        btn.remove();
    });
})();

/* ---------------- Travel Timeline: search + sort ---------------- */
(function () {
    const searchInput = document.getElementById('tdTimelineSearch');
    const sortSelect = document.getElementById('tdTimelineSort');
    const list = document.getElementById('tdGroupList');
    const noResults = document.getElementById('tdTimelineNoResults');
    const expandBtn = document.getElementById('tdExpandTimeline');
    if (!list) return;

    // Searching/sorting needs every row in play — a match hidden behind the
    // "View All Destinations" collapse (.td-hidden-extra is display:none
    // !important) would otherwise never show up. Same one-way reveal the
    // button itself does, just triggered by typing/choosing a sort instead.
    function revealAll() {
        list.querySelectorAll('.td-hidden-extra').forEach((el) => el.classList.remove('td-hidden-extra'));
        if (expandBtn) expandBtn.remove();
    }

    function applySearch() {
        const term = (searchInput && searchInput.value.trim().toLowerCase()) || '';
        if (term) revealAll();
        let visibleCount = 0;

        list.querySelectorAll('.td-group-row').forEach((row) => {
            const matches = !term || row.dataset.name.includes(term) || row.dataset.address.includes(term);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        if (noResults) noResults.hidden = visibleCount !== 0;
    }

    function applySort() {
        if (!sortSelect) return;
        const mode = sortSelect.value;
        if (mode !== 'newest') revealAll();
        const rows = Array.from(list.querySelectorAll('.td-group-row'));

        rows.sort((a, b) => {
            switch (mode) {
                case 'oldest':
                    return a.dataset.latestDate.localeCompare(b.dataset.latestDate);
                case 'name':
                    return a.dataset.name.localeCompare(b.dataset.name);
                case 'most-visited':
                    return Number(b.dataset.visitCount) - Number(a.dataset.visitCount);
                case 'newest':
                default:
                    return b.dataset.latestDate.localeCompare(a.dataset.latestDate);
            }
        });

        rows.forEach((row) => list.appendChild(row));
    }

    if (searchInput) searchInput.addEventListener('input', applySearch);
    if (sortSelect) sortSelect.addEventListener('change', applySort);
})();

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

    // Counts a stat number up from 0 to its real value instead of just
    // appearing — small touch, but it's the one that makes "By the
    // Numbers" actually feel like a reveal instead of a static list.
    function playCountUp(slideEl) {
        slideEl.querySelectorAll('.tw-stat-num').forEach((el) => {
            const target = parseInt(el.dataset.twTarget || el.textContent, 10);
            if (!Number.isFinite(target)) return;
            el.dataset.twTarget = target;

            const duration = 700;
            const start = performance.now();
            function tick(now) {
                const progress = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                el.textContent = Math.round(target * eased);
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }

    // Re-triggers this slide's CSS entrance animation every time it
    // becomes active (not just the first time) — removing the class,
    // forcing a reflow, then re-adding it is what makes a CSS animation
    // replay instead of silently no-opping because the class never
    // actually changed.
    function playSlideAnimation(slideEl) {
        if (!slideEl) return;
        slideEl.classList.remove('tw-animate');
        void slideEl.offsetWidth; // force reflow
        slideEl.classList.add('tw-animate');
        playCountUp(slideEl);
    }

    function render() {
        if (!slidesEl) return;
        slidesEl.style.transform = `translateX(-${current * 100}%)`;
        if (badge) badge.textContent = `${current + 1}/${slideCount}`;
        const activeSlide = slidesEl.children[current];
        playSlideAnimation(activeSlide);

        // "Your Travel Map" is identified by its fixed .tw-slide-7 class,
        // not its position — conditional slides mean its actual position
        // in the sequence varies from one Wrapped to the next. Leaflet
        // also needs the container to already have real dimensions, which
        // it won't until the overlay is open and this slide is showing,
        // hence lazy-initializing it here rather than on page load.
        if (activeSlide && activeSlide.classList.contains('tw-slide-7') && typeof tdWrappedMap !== 'undefined' && tdWrappedMap) {
            tdWrappedMap.activate();
        }
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

/* ---------------- Travel Wrapped: "Your Travel Map" (Leaflet) ----------------
   A real OpenStreetMap tile map (same library the admin map pickers use),
   not an abstract shape — but entirely non-interactive (dragging/zoom/etc.
   all disabled below), since a draggable map inside a horizontally-
   swiped slideshow would fight the slideshow's own swipe-to-navigate.
   It's a snapshot view, not a widget. */
const tdWrappedMap = (function () {
    const el = document.getElementById('twMapLeaflet');
    if (!el || typeof L === 'undefined') return null;

    let map = null;
    let points = [];
    let boundary = [];
    try {
        points = JSON.parse(el.dataset.points || '[]');
        boundary = JSON.parse(el.dataset.boundary || '[]');
    } catch (err) {
        points = [];
    }

    function categoryIcon(category) {
        const icons = (typeof TD_CATEGORY_ICONS !== 'undefined' && TD_CATEGORY_ICONS) || {};
        return L.divIcon({
            className: `tw-map-pin-marker td-badge-${category}`,
            html: icons[category] || '',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
        });
    }

    function activate() {
        if (map) {
            // Leaflet caches tile positions from whatever size the
            // container was at init — re-measure every time this slide
            // is revisited in case the panel itself was resized (e.g.
            // rotating the device) since the last time it was shown.
            map.invalidateSize();
            return;
        }
        if (!points.length) return;

        map = L.map(el, {
            dragging: false,
            touchZoom: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            zoomControl: false,
            attributionControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        if (boundary.length > 2) {
            L.polygon(boundary, {
                color: '#5f7d4c',
                weight: 1.5,
                fillColor: '#5f7d4c',
                fillOpacity: 0.12,
            }).addTo(map);
        }

        const latlngs = points.map((p) => [p.lat, p.lng]);
        if (latlngs.length > 1) {
            L.polyline(latlngs, {
                color: '#8b2e1a',
                weight: 2,
                dashArray: '6, 6',
            }).addTo(map);
        }

        // fitBounds first — a marker added before the map has a view set
        // doesn't reliably get a real DOM element yet, so getElement()
        // below would just silently return nothing and it'd never fade in.
        map.fitBounds(L.latLngBounds(latlngs), { padding: [24, 24], maxZoom: 16 });

        // Each pin fades in with a short stagger (see the matching
        // opacity transition on .tw-map-pin-marker in the CSS) once the
        // map itself has settled in, rather than all four appearing at
        // once the moment tiles load.
        points.forEach((p, i) => {
            const marker = L.marker([p.lat, p.lng], { icon: categoryIcon(p.category) }).addTo(map);
            const markerEl = marker.getElement();
            if (markerEl) {
                setTimeout(() => markerEl.classList.add('tw-marker-visible'), 500 + i * 150);
            }
        });

        // Belt-and-suspenders: if getElement() ever came back empty for a
        // marker above (so it missed its own staggered reveal), nothing
        // should still be invisible once the whole map has clearly
        // finished loading.
        setTimeout(() => {
            el.querySelectorAll('.tw-map-pin-marker').forEach((m) => m.classList.add('tw-marker-visible'));
        }, 1500);

        // The slide was still mid-transition (effectively zero width) the
        // instant this ran, so Leaflet's own size measurement from just
        // now can't be trusted — re-measure once that's settled.
        setTimeout(() => map.invalidateSize(), 300);
    }

    return { activate };
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

/* ---------------- Checklist: "show places near me first" ----------------
   Moves any checklist item within GEOFENCE_METERS of the visitor's live
   location to the top of the list (and out from behind the "View All
   Places" collapse), so someone standing at an unvisited spot doesn't have
   to search/scroll to find it. Same 1000m threshold as the visit-
   confirmation modal above, since both answer the same "am I basically
   here?" question. Click-to-opt-in (not an on-load prompt) matches the
   Near You widget on foryou.js; a previously-granted permission still
   re-runs automatically so returning visitors don't have to click again. */
(function () {
    const btn = document.getElementById('tdNearbyBtn');
    const btnLabel = btn && btn.querySelector('.td-nearby-btn-label');
    const statusEl = document.getElementById('tdNearbyStatus');
    const checklist = document.getElementById('tdChecklist');
    const expandBtn = document.getElementById('tdExpandChecklist');
    if (!btn || !checklist) return;

    const GEOFENCE_METERS = 1000;
    const VISIBLE_COUNT = 6; // matches the server-rendered collapse (first 6 shown, rest behind "View All Places")

    function haversineMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = (d) => (d * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function setLabel(text) {
        if (btnLabel) btnLabel.textContent = text;
    }

    // Sorts the WHOLE checklist by distance (closest first), not just the
    // places within the 1km geofence — so "closest place at the top" is
    // true for the full list, with the geofence only controlling the
    // "Nearby" badge/highlight, not who gets to move up.
    function applyNearby(userLat, userLng) {
        const rows = Array.from(checklist.querySelectorAll('.td-check-row'));
        const withDist = [];
        const withoutCoords = [];

        rows.forEach((row) => {
            const lat = parseFloat(row.dataset.lat);
            const lng = parseFloat(row.dataset.lng);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                withoutCoords.push(row);
                return;
            }
            withDist.push({ row, dist: haversineMeters(userLat, userLng, lat, lng) });
        });

        if (withDist.length === 0) {
            statusEl.textContent = "None of your places have a location on file yet.";
            statusEl.hidden = false;
            return;
        }

        withDist.sort((a, b) => a.dist - b.dist);

        // Already expanded ("View All Places" was clicked)? Keep everything
        // visible. Otherwise re-apply the same "first N visible" collapse,
        // just against the new, distance-sorted order.
        const stillCollapsed = !!expandBtn && document.body.contains(expandBtn);
        let nearbyCount = 0;

        const ordered = [...withDist.map((d) => d.row), ...withoutCoords];
        ordered.forEach((row, i) => {
            row.classList.toggle('td-hidden-extra', stillCollapsed && i >= VISIBLE_COUNT);
            checklist.appendChild(row);
        });

        withDist.forEach(({ row, dist }) => {
            const isNearby = dist <= GEOFENCE_METERS;
            const badge = row.querySelector('.td-nearby-badge');
            row.classList.toggle('td-is-nearby', isNearby);
            if (badge) badge.hidden = !isNearby;
            if (isNearby) nearbyCount++;
        });

        statusEl.textContent = nearbyCount === 0
            ? 'Sorted by distance — closest places are now at the top.'
            : (nearbyCount === 1
                ? '1 place near you is now at the top of the list.'
                : `${nearbyCount} places near you are now at the top of the list.`);
        statusEl.hidden = false;
    }

    function requestLocation() {
        if (!navigator.geolocation) {
            statusEl.textContent = "Your browser doesn't support location access.";
            statusEl.hidden = false;
            return;
        }

        btn.disabled = true;
        setLabel('Locating…');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                btn.disabled = false;
                setLabel('Show places near me first');
                applyNearby(position.coords.latitude, position.coords.longitude);
            },
            (error) => {
                btn.disabled = false;
                setLabel('Show places near me first');
                statusEl.textContent = error.code === error.PERMISSION_DENIED
                    ? 'Location access was denied — you can enable it anytime in your browser settings.'
                    : "Couldn't get your location. Please try again.";
                statusEl.hidden = false;
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    btn.addEventListener('click', requestLocation);

    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then((result) => {
            if (result.state === 'granted') requestLocation();
        }).catch(() => {});
    }
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

/* ---------------- Photo lightbox ----------------
   Delegated on document (not a one-time querySelectorAll) so it also
   covers the Recent Memories grid, which renders its tiles client-side
   after this script runs — a static binding would miss them entirely. */
const tdLightbox = (function () {
    const lightbox = document.getElementById('tdLightbox');
    const lightboxImg = document.getElementById('tdLightboxImg');
    const lightboxCaption = document.getElementById('tdLightboxCaption');
    const closeBtn = document.getElementById('tdLightboxClose');
    if (!lightbox) return null;

    function openLightbox(src, caption) {
        lightboxImg.src = src;
        lightboxCaption.textContent = caption || '';
        lightbox.classList.add('open');
    }

    function closeLightbox() {
        lightbox.classList.remove('open');
        lightboxImg.src = '';
    }

    document.addEventListener('click', (e) => {
        if (e.target.closest('.td-photo-delete-btn')) return; // delete icon, not the photo itself
        const trigger = e.target.closest('.td-lightbox-trigger');
        if (!trigger) return;
        openLightbox(trigger.dataset.lightboxSrc, trigger.dataset.lightboxCaption);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeLightbox(); return; }
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const trigger = document.activeElement && document.activeElement.closest && document.activeElement.closest('.td-lightbox-trigger');
        if (!trigger) return;
        e.preventDefault();
        openLightbox(trigger.dataset.lightboxSrc, trigger.dataset.lightboxCaption);
    });

    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });

    return { open: openLightbox, close: closeLightbox };
})();

/* ---------------- Delete a single photo ----------------
   One photo can appear in up to three places at once — the Timeline
   (that visit's own photos), the static Photo Memories gallery (every
   photo), and the client-rendered Recent Memories grid — since all three
   read from the same diary_photos rows. A single delegated handler here
   does the actual delete call once, then scrubs the Timeline/Gallery
   copies directly and tells the Recent Memories grid (which keeps its
   own in-memory list for pagination) via a custom event, so nothing goes
   stale without a page reload. */
function tdRemovePhotoFromStaticSections(photoId) {
    const matches = document.querySelectorAll(`.td-photo-delete-btn[data-photo-id="${photoId}"]`);

    // A photo can appear TWICE within the same open folder now (its visit's
    // own entry in Visit History, and again in the aggregated "Photos (N)"
    // gallery) — decrement that folder's counts once per distinct group,
    // not once per DOM copy, or deleting one photo would subtract 2.
    const decrementedRows = new Set();

    matches.forEach((btn) => {
        if (btn.closest('#tdMemoryGrid')) return; // the grid re-renders itself from its own array

        const groupRow = btn.closest('.td-group-row');
        if (groupRow && !decrementedRows.has(groupRow)) {
            decrementedRows.add(groupRow);
            // The destination row's photo counts are static server-rendered
            // text, in up to three places at once (collapsed header, the
            // open folder's stat box, and its "Photos (N)" heading) —
            // without this they'd go stale the moment a photo is deleted
            // live (no reload happens for this action).
            groupRow.querySelectorAll('.td-group-photo-count').forEach((countEl) => {
                const remaining = Math.max(0, Number(countEl.dataset.count) - 1);
                countEl.dataset.count = remaining;
                countEl.textContent = countEl.dataset.format === 'suffix'
                    ? `${remaining} photo${remaining === 1 ? '' : 's'}`
                    : String(remaining);
            });
        }

        // This copy's own Visit History row ("N photos" next to its
        // chevron) — only the Visit History copy of a photo sits inside a
        // .td-visit-row, so this naturally doesn't double-fire for the
        // aggregated gallery's copy of the same photo.
        const visitRow = btn.closest('.td-visit-row');
        const visitCountEl = visitRow && visitRow.querySelector('.td-visit-photo-count');
        if (visitCountEl) {
            const remaining = Math.max(0, Number(visitCountEl.dataset.count) - 1);
            visitCountEl.dataset.count = remaining;
            visitCountEl.textContent = `${remaining} photo${remaining === 1 ? '' : 's'}`;
        }

        const container = btn.closest('.td-timeline-photo, .td-gallery-tile');
        if (container) container.remove();
    });

    // The decorative preview thumbnail in each visit row's collapsed
    // header (.td-visit-row-thumbs) isn't a .td-photo-delete-btn copy —
    // it's just a plain <img>, so it needs its own cleanup pass here
    // instead of being covered by the loop above.
    document.querySelectorAll(`.td-visit-row-thumb[data-photo-id="${photoId}"]`).forEach((el) => {
        el.remove();
    });
}

document.addEventListener('click', async (e) => {
    const delBtn = e.target.closest('.td-photo-delete-btn');
    if (!delBtn) return;
    e.preventDefault();
    e.stopPropagation();

    if (!window.confirm('Delete this photo?')) return;

    const photoId = delBtn.dataset.photoId;
    delBtn.disabled = true;

    const res = await tdPost('delete_photo', { photo_id: photoId });
    if (res.success) {
        tdRemovePhotoFromStaticSections(photoId);
        document.dispatchEvent(new CustomEvent('td:photo-deleted', { detail: { photoId: Number(photoId) } }));
    } else {
        delBtn.disabled = false;
        tdToast(res.message || "Couldn't delete that photo.");
    }
});

/* ---------------- Recent Memories: tabbed, paginated grid ----------------
   Rendered entirely client-side from TD_PHOTO_MEMORIES so switching "This
   Week/Month/Year" or flipping a page never reloads the page — a plain
   CSS grid (wraps, no overflow) instead of the old horizontal scroller. */
(function () {
    const tabsEl = document.getElementById('tdMemoryTabs');
    const gridEl = document.getElementById('tdMemoryGrid');
    const emptyEl = document.getElementById('tdMemoryEmpty');
    const pagerEl = document.getElementById('tdMemoryPager');
    const prevBtn = document.getElementById('tdMemoryPrev');
    const nextBtn = document.getElementById('tdMemoryNext');
    const statusEl = document.getElementById('tdMemoryPageStatus');
    if (!tabsEl || !gridEl) return;

    const TRASH_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>';

    const memories = (typeof TD_PHOTO_MEMORIES !== 'undefined' && Array.isArray(TD_PHOTO_MEMORIES)) ? TD_PHOTO_MEMORIES.slice() : [];
    const categoryIcons = (typeof TD_CATEGORY_ICONS !== 'undefined' && TD_CATEGORY_ICONS) || {};

    // Tiles get progressively smaller Week → Month → Year (CSS handles the
    // sizing via the grid's modifier class); page sizes grow to match.
    const PERIODS = {
        week:  { days: 7,   perPage: 8,  sizeClass: '' },
        month: { days: 30,  perPage: 12, sizeClass: 'is-month' },
        year:  { days: 365, perPage: 18, sizeClass: 'is-year' },
    };

    let period = 'week';
    let page = 0;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function itemsForPeriod() {
        const cutoff = Date.now() - PERIODS[period].days * 86400000;
        return memories.filter((m) => {
            const t = new Date(m.visitedDate + 'T00:00:00').getTime();
            return !Number.isNaN(t) && t >= cutoff;
        });
    }

    function render() {
        const cfg = PERIODS[period];
        const items = itemsForPeriod();
        const totalPages = Math.max(1, Math.ceil(items.length / cfg.perPage));
        if (page >= totalPages) page = totalPages - 1;
        if (page < 0) page = 0;

        gridEl.className = 'td-memory-grid' + (cfg.sizeClass ? ' ' + cfg.sizeClass : '');

        if (items.length === 0) {
            gridEl.innerHTML = '';
            emptyEl.hidden = false;
            pagerEl.hidden = true;
            return;
        }
        emptyEl.hidden = true;

        const pageItems = items.slice(page * cfg.perPage, (page + 1) * cfg.perPage);
        gridEl.innerHTML = pageItems.map((m) => {
            const dateText = formatDate(m.visitedDate);
            const caption = m.caption ? `<p class="td-memory-caption">${escapeHtml(m.caption)}</p>` : '';
            return `
                <div class="td-memory-card">
                    <div class="td-memory-photo td-lightbox-trigger" tabindex="0" role="button"
                        data-lightbox-src="${escapeHtml(m.image)}"
                        data-lightbox-caption="${escapeHtml(m.placeName)} · ${escapeHtml(dateText)}">
                        <span class="td-memory-icon">${categoryIcons[m.category] || ''}</span>
                        <img src="${escapeHtml(m.image)}" alt="" loading="lazy">
                        <button type="button" class="td-photo-delete-btn" aria-label="Delete photo" title="Delete photo" data-photo-id="${m.photoId}">${TRASH_ICON}</button>
                    </div>
                    <h3 class="td-memory-name">${escapeHtml(m.placeName)}</h3>
                    <p class="td-memory-date">${escapeHtml(dateText)}</p>
                    ${caption}
                </div>
            `;
        }).join('');

        pagerEl.hidden = totalPages <= 1;
        prevBtn.disabled = page === 0;
        nextBtn.disabled = page >= totalPages - 1;
        statusEl.textContent = `Page ${page + 1} of ${totalPages}`;
    }

    tabsEl.addEventListener('click', (e) => {
        const tab = e.target.closest('.td-memory-tab');
        if (!tab) return;
        tabsEl.querySelectorAll('.td-memory-tab').forEach((t) => t.classList.toggle('is-active', t === tab));
        period = tab.dataset.period;
        page = 0;
        render();
    });

    prevBtn.addEventListener('click', () => { page--; render(); });
    nextBtn.addEventListener('click', () => { page++; render(); });

    // The actual delete call/confirm is handled by the single delegated
    // handler (it covers every section) — this just keeps this module's
    // own copy of the data in sync so pagination/empty-state stay correct
    // no matter which section the delete was actually clicked from.
    document.addEventListener('td:photo-deleted', (e) => {
        const idx = memories.findIndex((m) => m.photoId === e.detail.photoId);
        if (idx !== -1) {
            memories.splice(idx, 1);
            render();
        }
    });

    render();
})();
