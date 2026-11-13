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
    const GEOFENCE_METERS = 1000;
    let current = null;
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
                if (!current) return;
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
document.querySelectorAll('.td-checkbox').forEach((checkbox) => {
    checkbox.addEventListener('change', async function () {
        const itemType = this.dataset.itemType;
        const itemId = this.dataset.itemId;
        if (this.checked) {
            if (!tdVisitModal) return;
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
document.querySelectorAll('.td-log-again-btn, .td-timeline-log-again').forEach((btn) => {
    btn.addEventListener('click', function () {
        if (!tdVisitModal) return;
        const place = this.classList.contains('td-timeline-log-again')
            ? { placeName: this.dataset.place, lat: this.dataset.lat ? parseFloat(this.dataset.lat) : null, lng: this.dataset.lng ? parseFloat(this.dataset.lng) : null }
            : tdPlaceFromRow(this);
        tdVisitModal.open({ itemType: this.dataset.itemType, itemId: this.dataset.itemId, ...place });
    });
});
async function tdUploadFiles(files, itemType, itemId, btn, knownVideoDuration = null) {
    if (!files.length) return;
    btn.disabled = true;
    const originalLabel = btn.innerHTML;
    let uploaded = 0;
    let failed = 0;
    let lastError = '';
    for (const file of files) {
        btn.textContent = files.length > 1 ? `Uploading ${uploaded + failed + 1}/${files.length}…` : 'Uploading…';
        const isVideo = file.type.startsWith('video/') || /\.(mp4|webm|mov)$/i.test(file.name);
        if (file.size > 18 * 1024 * 1024) {
            failed++;
            lastError = 'Each photo or video must be 18MB or smaller.';
            continue;
        }
        if (!isVideo && !file.type.startsWith('image/') && !/\.(jpe?g|png|webp|gif)$/i.test(file.name)) {
            failed++;
            lastError = 'Choose an image or a video file.';
            continue;
        }
        if (isVideo) {
            const duration = knownVideoDuration !== null && files.length === 1
                ? knownVideoDuration
                : await tdGetVideoDuration(file);
            if (!Number.isFinite(duration) || duration >= 10) {
                failed++;
                lastError = 'Videos must be shorter than 10 seconds.';
                continue;
            }
        }
        const formData = new FormData();
        formData.append('action', 'upload_photo');
        formData.append('item_type', itemType);
        formData.append('item_id', itemId);
        formData.append('media', file);
        try {
            const res = await fetch('traveldiary_actions.php', { method: 'POST', body: formData }).then((r) => r.json());
            if (res.success) uploaded++; else { failed++; lastError = res.message || 'Upload failed.'; }
        } catch (err) {
            failed++;
            lastError = 'Upload failed. Please try again.';
        }
    }
    if (uploaded > 0) {
        location.reload();
        return;
    }
    tdToast(lastError || (failed === 1 ? 'Upload failed.' : `All ${failed} uploads failed.`));
    btn.disabled = false;
    btn.innerHTML = originalLabel;
}
function tdGetVideoDuration(file) {
    return new Promise((resolve) => {
        const probe = document.createElement('video');
        const url = URL.createObjectURL(file);
        const cleanup = (duration) => {
            URL.revokeObjectURL(url);
            probe.removeAttribute('src');
            resolve(duration);
        };
        probe.preload = 'metadata';
        probe.onloadedmetadata = () => cleanup(probe.duration);
        probe.onerror = () => cleanup(NaN);
        probe.src = url;
    });
}
function tdUploadPhotos(itemType, itemId, btn) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png, image/jpeg, image/webp, image/gif, video/mp4, video/webm, video/quicktime';
    input.multiple = true;
    input.addEventListener('change', () => tdUploadFiles(Array.from(input.files), itemType, itemId, btn));
    input.click();
}
const tdCamera = (function () {
    const overlay = document.getElementById('tdCameraOverlay');
    const video = document.getElementById('tdCameraVideo');
    const photoPreview = document.getElementById('tdCameraPhotoPreview');
    const videoPreview = document.getElementById('tdCameraVideoPreview');
    const canvas = document.getElementById('tdCameraCanvas');
    const errorEl = document.getElementById('tdCameraError');
    const cameraTitle = overlay && overlay.querySelector('.td-photo-choice-title');
    const shootBtn = document.getElementById('tdCameraShoot');
    const cancelBtn = document.getElementById('tdCameraCancel');
    const closeBtn = document.getElementById('tdCameraClose');
    const flipBtn = document.getElementById('tdCameraFlip');
    const recordBtn = document.getElementById('tdCameraRecord');
    const recordingStatus = document.getElementById('tdCameraRecordingStatus');
    const shootActions = document.getElementById('tdCameraShootActions');
    const previewActions = document.getElementById('tdCameraPreviewActions');
    const retakeBtn = document.getElementById('tdCameraRetake');
    const useBtn = document.getElementById('tdCameraUse');
    if (!overlay) return null;
    let stream = null;
    let pending = null;
    let facingMode = 'environment';
    let capturedBlob = null;
    let capturedUrl = null;
    let capturedDuration = null;
    let recorder = null;
    let recordedChunks = [];
    let recordTimer = null;
    let captureMode = 'photo';
    function stop() {
        if (stream) stream.getTracks().forEach((t) => t.stop());
        stream = null;
        video.srcObject = null;
    }
    function showLive() {
        if (capturedUrl) { URL.revokeObjectURL(capturedUrl); capturedUrl = null; }
        capturedBlob = null;
        capturedDuration = null;
        photoPreview.hidden = true;
        photoPreview.src = '';
        videoPreview.hidden = true;
        videoPreview.pause();
        videoPreview.src = '';
        video.hidden = false;
        if (flipBtn) flipBtn.hidden = false;
        if (recordBtn) recordBtn.hidden = captureMode !== 'video';
        if (recordBtn) recordBtn.textContent = 'Record Video';
        if (recordBtn) recordBtn.disabled = false;
        shootBtn.disabled = false;
        if (recordingStatus) recordingStatus.hidden = true;
        updateFlipAvailability();
        previewActions.hidden = true;
        shootActions.hidden = false;
    }
    function showPreview(blob, isVideo = false, durationSeconds = null) {
        capturedBlob = blob;
        capturedDuration = durationSeconds;
        capturedUrl = URL.createObjectURL(blob);
        photoPreview.hidden = isVideo;
        photoPreview.src = isVideo ? '' : capturedUrl;
        videoPreview.hidden = !isVideo;
        if (isVideo) {
            videoPreview.src = capturedUrl;
        } else {
            videoPreview.pause();
            videoPreview.src = '';
        }
        video.hidden = true;
        if (flipBtn) flipBtn.hidden = true;
        shootActions.hidden = true;
        previewActions.hidden = false;
    }
    function close() {
        if (recordTimer) clearTimeout(recordTimer);
        recordTimer = null;
        if (recorder && recorder.state !== 'inactive') recorder.stop();
        recorder = null;
        stop();
        if (capturedUrl) { URL.revokeObjectURL(capturedUrl); capturedUrl = null; }
        capturedBlob = null;
        capturedDuration = null;
        photoPreview.src = '';
        videoPreview.pause();
        videoPreview.src = '';
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        pending = null;
        facingMode = 'environment';
    }
    function showError(message) {
        errorEl.textContent = message;
        errorEl.hidden = false;
        shootBtn.disabled = true;
        if (recordBtn && captureMode === 'video') recordBtn.disabled = true;
    }
    function updateMirror(cameraCount) {
        const mirrored = facingMode === 'user' || cameraCount < 2;
        video.classList.toggle('td-camera-mirrored', mirrored);
    }
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
            updateMirror(1);
        }
    }
    async function startStream() {
        errorEl.hidden = true;
        shootBtn.disabled = false;
        try {
            const newStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: facingMode } },
                audio: captureMode === 'video',
            });
            stop();
            stream = newStream;
            video.srcObject = stream;
            updateFlipAvailability();
        } catch (err) {
            showError(err.name === 'NotAllowedError'
                ? `Camera access was blocked. Allow camera access in your browser settings to ${captureMode === 'video' ? 'record a video' : 'take a photo'}.`
                : `Couldn't open the camera. Try choosing ${captureMode === 'video' ? 'a video' : 'a photo'} from your device instead.`);
        }
    }
    async function open(itemType, itemId, btn, mode = 'photo') {
        pending = { itemType, itemId, btn };
        captureMode = mode;
        if (cameraTitle) cameraTitle.textContent = mode === 'video' ? 'Record a Short Video' : 'Take a Photo';
        facingMode = 'environment';
        errorEl.hidden = true;
        shootBtn.disabled = false;
        if (capturedUrl) { URL.revokeObjectURL(capturedUrl); capturedUrl = null; }
        capturedBlob = null;
        photoPreview.hidden = true;
        videoPreview.hidden = true;
        photoPreview.src = '';
        videoPreview.src = '';
        if (recordBtn) recordBtn.hidden = mode !== 'video';
        if (recordBtn) { recordBtn.disabled = false; recordBtn.textContent = 'Record Video'; }
        if (recordingStatus) recordingStatus.hidden = true;
        shootBtn.hidden = mode === 'video';
        video.hidden = false;
        shootActions.hidden = false;
        previewActions.hidden = true;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            close();
            fallbackToFileInput(itemType, itemId, btn, mode);
            return;
        }
        if (mode === 'video' && !window.MediaRecorder) {
            close();
            fallbackToFileInput(itemType, itemId, btn, mode);
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
    function fallbackToFileInput(itemType, itemId, btn, mode = 'photo') {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = mode === 'video'
            ? 'video/mp4, video/webm, video/quicktime'
            : 'image/*';
        if (mode === 'photo') input.capture = 'environment';
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
    function stopRecording() {
        if (recordTimer) clearTimeout(recordTimer);
        recordTimer = null;
        if (recorder && recorder.state === 'recording') recorder.stop();
    }
    function startRecording() {
        if (!stream || !window.MediaRecorder) {
            showError('Video recording is not supported in this browser. Choose a video from your device instead.');
            return;
        }
        const mimeType = ['video/mp4;codecs=avc1.42E01E', 'video/mp4', 'video/webm;codecs=vp8', 'video/webm']
            .find((type) => MediaRecorder.isTypeSupported(type));
        try {
            recordedChunks = [];
            const activeRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
            recorder = activeRecorder;
            const startedAt = performance.now();
            activeRecorder.addEventListener('dataavailable', (e) => {
                if (e.data && e.data.size) recordedChunks.push(e.data);
            });
            activeRecorder.addEventListener('stop', () => {
                if (recordTimer) clearTimeout(recordTimer);
                recordTimer = null;
                if (recordingStatus) recordingStatus.hidden = true;
                if (recordBtn) recordBtn.textContent = 'Record Video';
                if (!pending) return;
                const duration = (performance.now() - startedAt) / 1000;
                if (duration >= 10) {
                    tdToast('Videos must be shorter than 10 seconds.');
                    showLive();
                    return;
                }
                const blob = new Blob(recordedChunks, { type: activeRecorder.mimeType || 'video/webm' });
                if (!blob.size) {
                    tdToast("Couldn't record that video. Please try again.");
                    showLive();
                    return;
                }
                showPreview(blob, true, duration);
            }, { once: true });
            activeRecorder.start();
            if (recordBtn) recordBtn.textContent = 'Stop Recording';
            if (recordingStatus) recordingStatus.hidden = false;
            recordTimer = setTimeout(stopRecording, 9000);
        } catch (err) {
            showError('Could not start video recording. Try choosing a video from your device.');
        }
    }
    if (recordBtn) {
        recordBtn.addEventListener('click', () => {
            if (recorder && recorder.state === 'recording') stopRecording();
            else startRecording();
        });
    }
    retakeBtn.addEventListener('click', showLive);
    useBtn.addEventListener('click', () => {
        if (!pending || !capturedBlob) return;
        const { itemType, itemId, btn } = pending;
        const duration = capturedDuration;
        const mimeType = capturedBlob.type || 'image/jpeg';
        const extension = mimeType.includes('mp4') ? 'mp4' : mimeType.includes('webm') ? 'webm' : 'jpg';
        const file = new File([capturedBlob], `camera-${Date.now()}.${extension}`, { type: mimeType });
        close();
        tdUploadFiles([file], itemType, itemId, btn, duration);
    });
    cancelBtn.addEventListener('click', close);
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('open')) close();
    });
    return { open };
})();
const tdPhotoChoice = (function () {
    const overlay = document.getElementById('tdPhotoChoiceOverlay');
    if (!overlay) return null;
    const closeBtn = document.getElementById('tdPhotoChoiceClose');
    const cameraBtn = document.getElementById('tdPhotoChoiceCamera');
    const videoBtn = document.getElementById('tdPhotoChoiceVideo');
    const libraryBtn = document.getElementById('tdPhotoChoiceLibrary');
    let pending = null;
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
    videoBtn.addEventListener('click', () => {
        if (!pending) return;
        const { itemType, itemId, btn } = pending;
        close();
        if (tdCamera) tdCamera.open(itemType, itemId, btn, 'video');
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
document.querySelectorAll('.td-check-toggle-note').forEach((btn) => {
    btn.addEventListener('click', function () {
        const panel = this.closest('.td-check-row').querySelector('.td-check-expand');
        if (panel) panel.hidden = !panel.hidden;
    });
});
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
(function () {
    const list = document.getElementById('tdGroupList');
    const backdrop = document.getElementById('tdFolderBackdrop');
    if (!list || !backdrop) return;
    const STORAGE_KEY = 'tdExpandedGroup';
    const REDUCE_MOTION = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';
    const D_OPEN = 820;
    const D_CLOSE = 680;
    let placeholder = null;
    let busy = false;
    function readExpandedKey() {
        try { return sessionStorage.getItem(STORAGE_KEY) || null; } catch (err) { return null; }
    }
    function writeExpandedKey(key) {
        try {
            if (key) sessionStorage.setItem(STORAGE_KEY, key);
            else sessionStorage.removeItem(STORAGE_KEY);
        } catch (err) {
        }
    }
    function px(r) {
        return { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' };
    }
    function finalRect(height) {
        const margin = 16;
        const width = Math.min(880, innerWidth - margin * 2);
        const top = Math.max(margin, Math.min(innerHeight * 0.05, 48));
        const maxHeight = innerHeight - top - margin;
        return { left: (innerWidth - width) / 2, top, width, height: Math.min(height, maxHeight) };
    }
    function measureNaturalHeight(row, width) {
        const prevStyle = row.getAttribute('style');
        row.style.cssText = `position:fixed; visibility:hidden; left:-9999px; top:0; width:${width}px; height:auto;`;
        const height = row.getBoundingClientRect().height;
        if (prevStyle === null) row.removeAttribute('style'); else row.setAttribute('style', prevStyle);
        return height;
    }
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
    function animateFolderContent(row, opening) {
        const keyframes = opening
            ? [{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'translateY(0)' }]
            : [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-6px)' }];
        return [row.querySelector('.td-group-header'), row.querySelector('.td-folder-page-left')]
            .filter(Boolean)
            .map((element) => element.animate(keyframes, {
                duration: opening ? D_OPEN * 0.55 : D_CLOSE * 0.5,
                delay: opening ? D_OPEN * 0.22 : 0,
                easing: EASE_OUT,
                fill: 'both',
            }));
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
        row.querySelectorAll('.td-group-header, .td-folder-page-left').forEach((el) => {
            el.style.opacity = '';
            el.style.transform = '';
        });
        setPageRightFolded(row, false);
        setAccentsVisible(row, true);
    }
    function closeAll(instant) {
        if (busy && !instant) return Promise.resolve();
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
            row.querySelectorAll('.td-group-header, .td-folder-page-left').forEach((el) => {
                el.style.opacity = '';
                el.style.transform = '';
            });
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
            ...animateFolderContent(row, false),
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
            row.getBoundingClientRect();
            const colors = folderColors(row);
            const animations = [
                row.animate([{ ...px(from), opacity: 0 }, { ...px(to), opacity: 1 }], { duration: D_OPEN, easing: EASE_OUT, fill: 'both' }),
                row.animate([{ backgroundColor: colors.closed }, { backgroundColor: colors.open }], { duration: D_OPEN, fill: 'both' }),
                ...animateFolderContent(row, true),
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
        row.dataset.groupKey = row.dataset.name + '|' + row.dataset.latestDate;
    });
    const savedKey = readExpandedKey();
    if (savedKey) {
        const match = Array.from(list.querySelectorAll('.td-group-row')).find((r) => r.dataset.groupKey === savedKey);
        if (match) openRow(match, true);
    }
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
    backdrop.addEventListener('click', (e) => {
        if (e.target !== backdrop) return;
        closeAll();
        writeExpandedKey(null);
    });
})();
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
(function () {
    const PAGE_SIZE = 3;
    const DURATION = 480;
    const REDUCE_MOTION = matchMedia('(prefers-reduced-motion: reduce)').matches;
    function buildPagination(history) {
        const rows = Array.from(history.querySelectorAll('.td-visit-row'));
        const pagination = history.nextElementSibling;
        if (!pagination || !pagination.classList.contains('td-visit-pagination')) return;
        const pageCount = Math.ceil(rows.length / PAGE_SIZE);
        if (pageCount <= 1) {
            pagination.hidden = true;
            return;
        }
        for (let p = 0; p < pageCount; p++) {
            const pageEl = document.createElement('div');
            pageEl.className = 'td-visit-page';
            pageEl.dataset.page = p;
            if (p !== 0) pageEl.hidden = true;
            rows.slice(p * PAGE_SIZE, p * PAGE_SIZE + PAGE_SIZE).forEach((row) => pageEl.appendChild(row));
            history.appendChild(pageEl);
        }
        history.dataset.activePage = '0';
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
    function flipToPage(history, pagination, targetPage) {
        const current = Number(history.dataset.activePage || 0);
        if (current === targetPage || history.dataset.flipping === 'true') return;
        const fromEl = history.querySelector(`.td-visit-page[data-page="${current}"]`);
        const toEl = history.querySelector(`.td-visit-page[data-page="${targetPage}"]`);
        if (!fromEl || !toEl) return;
        pagination.querySelectorAll('.td-visit-dot').forEach((d) => {
            d.classList.toggle('is-active', Number(d.dataset.page) === targetPage);
        });
        history.dataset.activePage = String(targetPage);
        if (REDUCE_MOTION) {
            fromEl.hidden = true;
            toEl.hidden = false;
            return;
        }
        history.dataset.flipping = 'true';
        const forward = targetPage > current;
        toEl.hidden = false;
        const height = Math.max(fromEl.getBoundingClientRect().height, toEl.getBoundingClientRect().height);
        history.style.height = height + 'px';
        Object.assign(fromEl.style, { position: 'absolute', top: '0', left: '0', right: '0', transformOrigin: forward ? 'left center' : 'right center' });
        Object.assign(toEl.style, { position: 'absolute', top: '0', left: '0', right: '0', transformOrigin: forward ? 'right center' : 'left center' });
        const outAnim = fromEl.animate(
            [{ transform: 'rotateY(0deg)', opacity: 1 }, { transform: `rotateY(${forward ? '-100' : '100'}deg)`, opacity: 0 }],
            { duration: DURATION * 0.55, easing: 'ease-in', fill: 'both' }
        );
        const inAnim = toEl.animate(
            [{ transform: `rotateY(${forward ? '100' : '-100'}deg)`, opacity: 0 }, { transform: 'rotateY(0deg)', opacity: 1 }],
            { duration: DURATION * 0.6, delay: DURATION * 0.35, easing: 'ease-out', fill: 'both' }
        );
        Promise.all([outAnim.finished, inAnim.finished]).catch(() => {}).then(() => {
            outAnim.cancel();
            inAnim.cancel();
            fromEl.hidden = true;
            fromEl.removeAttribute('style');
            toEl.removeAttribute('style');
            history.style.height = '';
            delete history.dataset.flipping;
        });
    }
    document.addEventListener('click', (e) => {
        const dot = e.target.closest('.td-visit-dot');
        if (!dot) return;
        const pagination = dot.closest('.td-visit-pagination');
        const history = pagination && pagination.previousElementSibling;
        if (!history || !history.classList.contains('td-visit-history')) return;
        flipToPage(history, pagination, Number(dot.dataset.page));
    });
})();
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
(function () {
    const searchInput = document.getElementById('tdTimelineSearch');
    const sortSelect = document.getElementById('tdTimelineSort');
    const list = document.getElementById('tdGroupList');
    const noResults = document.getElementById('tdTimelineNoResults');
    const expandBtn = document.getElementById('tdExpandTimeline');
    if (!list) return;
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
(function () {
    const periodOverlay = document.getElementById('twPeriodOverlay');
    const periodCloseBtn = document.getElementById('twPeriodClose');
    const overlay = document.getElementById('twOverlay');
    const slidesEl = document.getElementById('twSlides');
    const badge = document.getElementById('twBadge');
    const storyBars = document.getElementById('twStoryBars');
    const prevBtn = document.getElementById('twPrev');
    const nextBtn = document.getElementById('twNext');
    const closeBtn = document.getElementById('twClose');
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
    if (!overlay) return;
    const slideCount = slidesEl ? slidesEl.children.length : 0;
    let current = 0;
    let storyElapsed = 0;
    let storyPaused = false;
    let storyTimer = null;
    let storyTickAt = 0;
    function playCountUp(slideEl) {
        slideEl.querySelectorAll('.tw-stat-num').forEach((el) => {
            const target = parseInt(el.dataset.twTarget || el.textContent, 10);
            if (!Number.isFinite(target)) return;
            el.dataset.twTarget = target;
            const duration = 700;
            const start = performance.now();
            function tick(now) {
                const progress = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased);
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }
    function playSlideAnimation(slideEl) {
        if (!slideEl) return;
        slideEl.classList.remove('tw-animate');
        void slideEl.offsetWidth;
        slideEl.classList.add('tw-animate');
        playCountUp(slideEl);
    }
    function render() {
        if (!slidesEl) return;
        slidesEl.style.transform = `translateX(-${current * 100}%)`;
        if (badge) badge.textContent = `${current + 1}/${slideCount}`;
        if (storyBars) {
            [...storyBars.children].forEach((bar, index) => {
                const fill = bar.firstElementChild;
                if (fill) fill.style.width = index < current ? '100%' : index === current ? `${Math.min(100, storyElapsed / 6500 * 100)}%` : '0%';
            });
        }
        const activeSlide = slidesEl.children[current];
        playSlideAnimation(activeSlide);
        if (activeSlide && activeSlide.classList.contains('tw-slide-7') && typeof tdWrappedMap !== 'undefined' && tdWrappedMap) {
            tdWrappedMap.activate();
        }
    }
    function goTo(index) {
        if (!slideCount) return;
        current = Math.max(0, Math.min(slideCount - 1, index));
        storyElapsed = 0;
        render();
    }
    function open() {
        if (typeof TD_CAN_WRAP === 'undefined' || !TD_CAN_WRAP) {
            tdToast('No visits logged for that range yet. Try a different one!');
            return;
        }
        current = 0;
        storyElapsed = 0;
        storyPaused = false;
        render();
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        storyTickAt = performance.now();
        if (storyTimer) clearInterval(storyTimer);
        storyTimer = setInterval(() => {
            const now = performance.now();
            const delta = Math.min(now - storyTickAt, 150);
            storyTickAt = now;
            if (!overlay.classList.contains('open') || storyPaused) return;
            storyElapsed += delta;
            const fill = storyBars?.children[current]?.firstElementChild;
            if (fill) fill.style.width = `${Math.min(100, storyElapsed / 6500 * 100)}%`;
            if (storyElapsed >= 6500 && current < slideCount - 1) goTo(current + 1);
        }, 50);
    }
    function close() {
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (storyTimer) clearInterval(storyTimer);
        storyTimer = null;
    }
    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
    });
    if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1));
    overlay.addEventListener('pointerdown', (e) => {
        if (!overlay.classList.contains('open') || e.target.closest('button, a')) return;
        storyPaused = true;
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
        overlay.addEventListener(eventName, () => { storyPaused = false; storyTickAt = performance.now(); });
    });
    if (slidesEl) slidesEl.addEventListener('click', (e) => {
        if (e.target.closest('button, a')) return;
        const bounds = slidesEl.getBoundingClientRect();
        goTo(e.clientX - bounds.left < bounds.width * 0.3 ? current - 1 : current + 1);
    });
    document.addEventListener('keydown', (e) => {
        if (!overlay.classList.contains('open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight') goTo(current + 1);
        if (e.key === 'ArrowLeft') goTo(current - 1);
    });
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
    if (typeof TD_WRAPPED_REQUESTED !== 'undefined' && TD_WRAPPED_REQUESTED) {
        open();
    }
    const downloadBtn = document.getElementById('twDownloadBtn');
    const shareBtn = document.getElementById('twShareBtn');
    const periodLabel = (typeof TD_WRAPPED_PERIOD !== 'undefined' && TD_WRAPPED_PERIOD) || 'wrapped';
    async function twCaptureCurrentSlide() {
        if (typeof html2canvas === 'undefined') {
            tdToast("Photo export didn't load — check your connection and try again.");
            return null;
        }
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
                }
            } else {
                if (canvas) twDownloadCanvas(canvas);
                tdToast('Photo saved! Upload it to Instagram, or use the Facebook button to share.');
            }
            shareBtn.disabled = false;
        });
    }
})();
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
        map.fitBounds(L.latLngBounds(latlngs), { padding: [24, 24], maxZoom: 16 });
        points.forEach((p, i) => {
            const marker = L.marker([p.lat, p.lng], { icon: categoryIcon(p.category) }).addTo(map);
            const markerEl = marker.getElement();
            if (markerEl) {
                setTimeout(() => markerEl.classList.add('tw-marker-visible'), 500 + i * 150);
            }
        });
        setTimeout(() => {
            el.querySelectorAll('.tw-map-pin-marker').forEach((m) => m.classList.add('tw-marker-visible'));
        }, 1500);
        setTimeout(() => map.invalidateSize(), 300);
    }
    return { activate };
})();
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
(function () {
    const btn = document.getElementById('tdNearbyBtn');
    const btnLabel = btn && btn.querySelector('.td-nearby-btn-label');
    const statusEl = document.getElementById('tdNearbyStatus');
    const checklist = document.getElementById('tdChecklist');
    const expandBtn = document.getElementById('tdExpandChecklist');
    if (!btn || !checklist) return;
    const GEOFENCE_METERS = 1000;
    const VISIBLE_COUNT = 6;
    function haversineMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = (d) => (d * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
        const safeA = Math.min(1, Math.max(0, a));
        return R * 2 * Math.atan2(Math.sqrt(safeA), Math.sqrt(1 - safeA));
    }
    function formatDistance(meters) {
        return meters < 1000
            ? `${Math.round(meters / 10) * 10} m`
            : `${(meters / 1000).toFixed(1)} km`;
    }
    function setLabel(text) {
        if (btnLabel) btnLabel.textContent = text;
    }
    function showRandomPlaces(message) {
        const rows = Array.from(checklist.querySelectorAll('.td-check-row'));
        for (let i = rows.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [rows[i], rows[j]] = [rows[j], rows[i]];
        }
        const stillCollapsed = !!expandBtn && document.body.contains(expandBtn);
        rows.forEach((row, i) => {
            row.classList.remove('td-is-nearby');
            row.classList.toggle('td-hidden-extra', stillCollapsed && i >= VISIBLE_COUNT);
            const badge = row.querySelector('.td-nearby-badge');
            if (badge) {
                badge.hidden = true;
                const distanceLabel = badge.querySelector('.td-nearby-distance-label');
                if (distanceLabel) distanceLabel.textContent = 'Nearby';
            }
            checklist.appendChild(row);
        });
        statusEl.textContent = message;
        statusEl.hidden = false;
    }
    function applyNearby(userLat, userLng) {
        const rows = Array.from(checklist.querySelectorAll('.td-check-row'));
        const withDist = [];
        const withoutCoords = [];
        rows.forEach((row) => {
            const lat = parseFloat(row.dataset.lat);
            const lng = parseFloat(row.dataset.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)
                || Math.abs(lat) > 90 || Math.abs(lng) > 180
                || (lat === 0 && lng === 0)) {
                withoutCoords.push(row);
                return;
            }
            withDist.push({ row, dist: haversineMeters(userLat, userLng, lat, lng) });
        });
        if (withDist.length === 0) {
            showRandomPlaces('No saved place coordinates are available. Showing places in a random order.');
            return;
        }
        withDist.sort((a, b) => a.dist - b.dist);
        const stillCollapsed = !!expandBtn && document.body.contains(expandBtn);
        const ordered = [...withDist.map((d) => d.row), ...withoutCoords];
        ordered.forEach((row, i) => {
            row.classList.toggle('td-hidden-extra', stillCollapsed && i >= VISIBLE_COUNT);
            checklist.appendChild(row);
        });
        withDist.forEach(({ row, dist }) => {
            const isNearby = dist <= GEOFENCE_METERS;
            const badge = row.querySelector('.td-nearby-badge');
            row.classList.toggle('td-is-nearby', isNearby);
            if (badge) {
                const distanceLabel = badge.querySelector('.td-nearby-distance-label');
                if (distanceLabel) distanceLabel.textContent = `${formatDistance(dist)} away`;
                badge.hidden = false;
            }
        });
        const nearest = withDist[0];
        const nearestName = nearest.row.dataset.place || 'A place';
        statusEl.textContent = `Location found. Nearest: ${nearestName} (${formatDistance(nearest.dist)}). Closest places are at the top.`;
        statusEl.hidden = false;
    }
    function requestLocation() {
        if (!navigator.geolocation) {
            showRandomPlaces("Your browser doesn't support location access. Showing places in a random order.");
            return;
        }
        btn.disabled = true;
        setLabel('Finding your location…');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                btn.disabled = false;
                setLabel('Show places near me first');
                applyNearby(position.coords.latitude, position.coords.longitude);
            },
            (error) => {
                btn.disabled = false;
                setLabel('Show places near me first');
                const reason = error.code === error.PERMISSION_DENIED
                    ? 'Location access was denied. Allow location for this site in your browser settings.'
                    : error.code === error.POSITION_UNAVAILABLE
                        ? 'Your device could not determine your location. Turn on device location services.'
                        : 'Finding your location took too long. Check device location services.';
                showRandomPlaces(`${reason} Showing places in a random order.`);
            },
            { enableHighAccuracy: false, timeout: 20000, maximumAge: 120000 }
        );
    }
    btn.addEventListener('click', requestLocation);
})();
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
const tdLightbox = (function () {
    const lightbox = document.getElementById('tdLightbox');
    const lightboxImg = document.getElementById('tdLightboxImg');
    const lightboxVideo = document.getElementById('tdLightboxVideo');
    const lightboxCaption = document.getElementById('tdLightboxCaption');
    const closeBtn = document.getElementById('tdLightboxClose');
    if (!lightbox) return null;
    function openLightbox(src, caption, mediaType = 'image') {
        const isVideo = mediaType === 'video';
        lightboxImg.hidden = isVideo;
        lightboxImg.src = isVideo ? '' : src;
        lightboxVideo.hidden = !isVideo;
        if (isVideo) {
            lightboxVideo.src = src;
            lightboxVideo.currentTime = 0;
            lightboxVideo.play().catch(() => {});
        } else {
            lightboxVideo.pause();
            lightboxVideo.src = '';
        }
        lightboxCaption.textContent = caption || '';
        lightbox.classList.add('open');
    }
    function closeLightbox() {
        lightbox.classList.remove('open');
        lightboxImg.src = '';
        lightboxVideo.pause();
        lightboxVideo.src = '';
        lightboxVideo.hidden = true;
        lightboxImg.hidden = false;
    }
    document.addEventListener('click', (e) => {
        if (e.target.closest('.td-photo-delete-btn')) return;
        const trigger = e.target.closest('.td-lightbox-trigger');
        if (!trigger) return;
        openLightbox(trigger.dataset.lightboxSrc, trigger.dataset.lightboxCaption, trigger.dataset.lightboxType);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeLightbox(); return; }
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const trigger = document.activeElement && document.activeElement.closest && document.activeElement.closest('.td-lightbox-trigger');
        if (!trigger) return;
        e.preventDefault();
        openLightbox(trigger.dataset.lightboxSrc, trigger.dataset.lightboxCaption, trigger.dataset.lightboxType);
    });
    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });
    return { open: openLightbox, close: closeLightbox };
})();
function tdRemovePhotoFromStaticSections(photoId) {
    const matches = document.querySelectorAll(`.td-photo-delete-btn[data-photo-id="${photoId}"]`);
    const decrementedRows = new Set();
    matches.forEach((btn) => {
        if (btn.closest('#tdMemoryGrid')) return;
        const groupRow = btn.closest('.td-group-row');
        if (groupRow && !decrementedRows.has(groupRow)) {
            decrementedRows.add(groupRow);
            groupRow.querySelectorAll('.td-group-photo-count').forEach((countEl) => {
                const remaining = Math.max(0, Number(countEl.dataset.count) - 1);
                countEl.dataset.count = remaining;
                countEl.textContent = countEl.dataset.format === 'suffix'
                    ? `${remaining} media item${remaining === 1 ? '' : 's'}`
                    : String(remaining);
            });
        }
        const visitRow = btn.closest('.td-visit-row');
        const visitCountEl = visitRow && visitRow.querySelector('.td-visit-photo-count');
        if (visitCountEl) {
            const remaining = Math.max(0, Number(visitCountEl.dataset.count) - 1);
            visitCountEl.dataset.count = remaining;
            visitCountEl.textContent = `${remaining} media item${remaining === 1 ? '' : 's'}`;
        }
        const container = btn.closest('.td-timeline-photo, .td-gallery-tile');
        if (container) container.remove();
    });
    document.querySelectorAll(`.td-visit-row-thumb[data-photo-id="${photoId}"]`).forEach((el) => {
        el.remove();
    });
}
document.addEventListener('click', async (e) => {
    const delBtn = e.target.closest('.td-photo-delete-btn');
    if (!delBtn) return;
    e.preventDefault();
    e.stopPropagation();
    if (!window.confirm('Delete this photo or video?')) return;
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
            const mediaMarkup = m.isVideo
                ? `<video src="${escapeHtml(m.image)}" muted playsinline preload="metadata"></video>`
                : `<img src="${escapeHtml(m.image)}" alt="" loading="lazy">`;
            return `
                <div class="td-memory-card">
                    <div class="td-memory-photo td-lightbox-trigger" tabindex="0" role="button"
                        data-lightbox-src="${escapeHtml(m.image)}"
                        data-lightbox-type="${m.isVideo ? 'video' : 'image'}"
                        data-lightbox-caption="${escapeHtml(m.placeName)} · ${escapeHtml(dateText)}">
                        <span class="td-memory-icon">${categoryIcons[m.category] || ''}</span>
                        ${mediaMarkup}
                        <button type="button" class="td-photo-delete-btn" aria-label="Delete media" title="Delete media" data-photo-id="${m.photoId}">${TRASH_ICON}</button>
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
