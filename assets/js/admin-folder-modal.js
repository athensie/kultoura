/*
 * Opens folder-style <details data-folder-modal> blocks in a modal dialog
 * instead of expanding them inline.
 *
 * Markup contract:
 *   <details data-folder-modal>
 *     <summary> ... <span data-folder-title>Name</span> <span data-folder-meta>3 items</span></summary>
 *     <div data-folder-content> ... </div>
 *   </details>
 *
 * The content element is moved into the dialog while it is open and moved back
 * when it closes, so forms, listeners and ids inside it keep working.
 */
(function () {
    if (window.ktFolderModal) return;

    var dialog = document.createElement('dialog');
    dialog.className = 'folder-modal';
    dialog.setAttribute('aria-labelledby', 'folderModalTitle');
    dialog.innerHTML =
        '<header class="folder-modal-head">' +
            '<span class="folder-modal-icon" aria-hidden="true">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/></svg>' +
            '</span>' +
            '<div class="folder-modal-titles">' +
                '<h2 class="folder-modal-title" id="folderModalTitle"></h2>' +
                '<p class="folder-modal-meta"></p>' +
            '</div>' +
            '<button type="button" class="folder-modal-close" aria-label="Close folder">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>' +
        '</header>' +
        '<div class="folder-modal-body"></div>';

    var titleEl = dialog.querySelector('.folder-modal-title');
    var metaEl = dialog.querySelector('.folder-modal-meta');
    var bodyEl = dialog.querySelector('.folder-modal-body');
    var current = null; // { content, placeholder, trigger }

    function restore() {
        if (!current) return;
        current.placeholder.replaceWith(current.content);
        var trigger = current.trigger;
        current = null;
        document.documentElement.classList.remove('folder-modal-open');
        if (trigger && document.contains(trigger)) trigger.focus();
    }

    function open(details) {
        var content = details.querySelector(':scope > [data-folder-content]');
        if (!content) return;
        if (current) close();
        var summary = details.querySelector(':scope > summary');
        var title = details.querySelector('[data-folder-title]');
        var meta = details.querySelector('[data-folder-meta]');
        titleEl.textContent = title ? title.textContent.trim() : '';
        metaEl.textContent = meta ? meta.textContent.trim() : '';
        metaEl.hidden = !metaEl.textContent;

        var placeholder = document.createComment('folder-modal');
        content.before(placeholder);
        bodyEl.appendChild(content);
        bodyEl.scrollTop = 0;
        current = { content: content, placeholder: placeholder, trigger: summary };

        if (!dialog.isConnected) document.body.appendChild(dialog);
        document.documentElement.classList.add('folder-modal-open');
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    }

    function close() {
        if (typeof dialog.close === 'function' && dialog.open) dialog.close();
        else { dialog.removeAttribute('open'); restore(); }
    }

    dialog.addEventListener('close', restore);
    dialog.querySelector('.folder-modal-close').addEventListener('click', close);
    // Click on the backdrop (outside the dialog box) closes it.
    dialog.addEventListener('click', function (event) {
        if (event.target !== dialog) return;
        var rect = dialog.getBoundingClientRect();
        var inside = event.clientX >= rect.left && event.clientX <= rect.right &&
            event.clientY >= rect.top && event.clientY <= rect.bottom;
        if (!inside) close();
    });

    document.addEventListener('click', function (event) {
        var summary = event.target.closest('details[data-folder-modal] > summary');
        if (!summary) return;
        event.preventDefault();
        open(summary.parentElement);
    });

    // A normal form submit inside the dialog reloads the page; remember the
    // folder so it opens again afterwards instead of making the admin re-find it.
    var storageKey = 'kt-folder-modal:' + location.pathname;
    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented || !current || !dialog.contains(event.target)) return;
        try { sessionStorage.setItem(storageKey, titleEl.textContent); } catch (error) {}
    });
    function reopenRemembered() {
        var title = null;
        try {
            title = sessionStorage.getItem(storageKey);
            sessionStorage.removeItem(storageKey);
        } catch (error) {}
        if (!title) return;
        var match = Array.prototype.find.call(
            document.querySelectorAll('details[data-folder-modal]'),
            function (details) {
                var el = details.querySelector('[data-folder-title]');
                return el && el.textContent.trim() === title;
            }
        );
        if (match) open(match);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', reopenRemembered);
    else reopenRemembered();

    window.ktFolderModal = { open: open, close: close };
})();
