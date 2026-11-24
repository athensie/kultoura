/*
 * Opens folder-style <details data-folder-modal> blocks in a modal dialog instead of
 * expanding them inline. Folders inside an open folder (e.g. a listing inside a category)
 * open as a second level in the same dialog, with a Back button.
 *
 * Markup contract:
 *   <details data-folder-modal>
 *     <summary> ... <span data-folder-title>Name</span> <span data-folder-meta>3 items</span></summary>
 *     <div data-folder-content> ... </div>
 *   </details>
 *
 * Content elements are moved into the dialog while shown and moved back when closed,
 * so forms, listeners and ids inside them keep working.
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
                '<button type="button" class="folder-modal-back" hidden>' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>' +
                    '<span></span>' +
                '</button>' +
                '<h2 class="folder-modal-title" id="folderModalTitle"></h2>' +
                '<p class="folder-modal-meta"></p>' +
            '</div>' +
            '<button type="button" class="folder-modal-close" aria-label="Close folder">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>' +
        '</header>' +
        '<div class="folder-modal-search" hidden>' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>' +
            '<input type="search" autocomplete="off" spellcheck="false">' +
            '<span class="folder-modal-search-count" aria-live="polite"></span>' +
        '</div>' +
        '<div class="folder-modal-body"></div>';

    var titleEl = dialog.querySelector('.folder-modal-title');
    var metaEl = dialog.querySelector('.folder-modal-meta');
    var bodyEl = dialog.querySelector('.folder-modal-body');
    var backEl = dialog.querySelector('.folder-modal-back');
    var backLabel = backEl.querySelector('span');
    var searchBox = dialog.querySelector('.folder-modal-search');
    var searchInput = searchBox.querySelector('input');
    var searchCount = searchBox.querySelector('.folder-modal-search-count');
    var stack = [];   // [{ content, placeholder, trigger, title, meta, scroll, query }]

    // ---- Search within the folder on screen (listings in a category, listings in a promote folder) ----
    var normalize = function (text) {
        return (text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
    };
    function searchableItems(content) {
        return Array.prototype.slice.call(content.querySelectorAll('details[data-folder-modal], .promote-row'));
    }
    function itemText(item) {
        var title = item.querySelector('[data-folder-title], h3');
        return normalize(title ? title.textContent : item.textContent);
    }
    function applySearch() {
        var top = stack[stack.length - 1];
        if (!top) return;
        top.query = searchInput.value;
        var words = normalize(searchInput.value).split(' ').filter(Boolean);
        var items = searchableItems(top.content);
        var shown = 0;
        items.forEach(function (item) {
            var text = itemText(item);
            var match = words.every(function (word) { return text.indexOf(word) !== -1; });
            item.classList.toggle('is-search-hidden', !match);
            if (match) shown++;
        });
        searchCount.textContent = words.length ? (shown ? shown + ' of ' + items.length : 'No matches') : items.length + ' items';
        searchBox.classList.toggle('has-no-results', words.length > 0 && shown === 0);
    }
    function setupSearch() {
        var top = stack[stack.length - 1];
        var count = top ? searchableItems(top.content).length : 0;
        searchBox.hidden = count < 2;
        searchInput.placeholder = top ? 'Search in ' + top.title + '…' : 'Search…';
        searchInput.setAttribute('aria-label', searchInput.placeholder);
        searchInput.value = top && top.query ? top.query : '';
        if (top) applySearch();
    }
    searchInput.addEventListener('input', applySearch);
    searchInput.addEventListener('keydown', function (event) {
        // Esc clears the search first; Enter opens the only match.
        if (event.key === 'Escape' && searchInput.value) { event.preventDefault(); event.stopPropagation(); searchInput.value = ''; applySearch(); }
        if (event.key === 'Enter') {
            var top = stack[stack.length - 1];
            var visible = top ? searchableItems(top.content).filter(function (item) { return !item.classList.contains('is-search-hidden'); }) : [];
            if (visible.length === 1 && visible[0].matches('details[data-folder-modal]')) { event.preventDefault(); open(visible[0]); }
        }
    });

    function textOf(details, selector) {
        var element = details.querySelector(selector);
        return element ? element.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function renderHeader() {
        var top = stack[stack.length - 1];
        if (!top) return;
        titleEl.textContent = top.title;
        metaEl.textContent = top.meta;
        metaEl.hidden = !top.meta;
        var parent = stack[stack.length - 2];
        backEl.hidden = !parent;
        backLabel.textContent = parent ? 'Back to ' + parent.title : '';
        dialog.classList.toggle('is-nested', !!parent);
        setupSearch();
    }

    function popLevel() {
        var level = stack.pop();
        if (!level) return null;
        searchableItems(level.content).forEach(function (item) { item.classList.remove('is-search-hidden'); });
        level.placeholder.replaceWith(level.content);
        var parent = stack[stack.length - 1];
        if (parent) {
            parent.content.style.display = '';
            bodyEl.scrollTop = parent.scroll || 0;
        }
        return level;
    }

    function restoreAll() {
        var trigger = stack.length ? stack[0].trigger : null;
        while (stack.length) popLevel();
        document.documentElement.classList.remove('folder-modal-open');
        if (trigger && document.contains(trigger)) trigger.focus();
    }

    function open(details) {
        var content = details.querySelector(':scope > [data-folder-content]');
        if (!content) return;
        var parent = stack[stack.length - 1];
        // Opening a folder that is not inside the one on screen starts a fresh dialog.
        if (parent && !parent.content.contains(details)) { close(); parent = null; }
        if (parent) {
            parent.scroll = bodyEl.scrollTop;
            parent.content.style.display = 'none';
        }
        var placeholder = document.createComment('folder-modal');
        content.before(placeholder);
        bodyEl.appendChild(content);
        stack.push({
            content: content,
            placeholder: placeholder,
            trigger: details.querySelector(':scope > summary'),
            title: textOf(details, '[data-folder-title]'),
            meta: textOf(details, '[data-folder-meta]'),
            scroll: 0
        });
        renderHeader();
        bodyEl.scrollTop = 0;
        if (!dialog.isConnected) document.body.appendChild(dialog);
        if (!dialog.open) {
            document.documentElement.classList.add('folder-modal-open');
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
        }
        backEl.hidden ? dialog.querySelector('.folder-modal-close').focus() : backEl.focus();
    }

    function back() {
        if (stack.length <= 1) { close(); return; }
        var level = popLevel();
        renderHeader();
        if (level && level.trigger && document.contains(level.trigger)) level.trigger.focus();
    }

    function close() {
        if (typeof dialog.close === 'function' && dialog.open) dialog.close();
        else { dialog.removeAttribute('open'); restoreAll(); }
    }

    dialog.addEventListener('close', restoreAll);
    // Esc goes back one level first, then closes.
    dialog.addEventListener('cancel', function (event) {
        if (stack.length > 1) { event.preventDefault(); back(); }
    });
    dialog.querySelector('.folder-modal-close').addEventListener('click', close);
    backEl.addEventListener('click', back);
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

    // A normal form submit inside the dialog reloads the page; remember the open folders
    // (e.g. category, then listing) so they open again afterwards.
    var storageKey = 'kt-folder-modal:' + location.pathname;
    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented || !stack.length || !dialog.contains(event.target)) return;
        try { sessionStorage.setItem(storageKey, JSON.stringify(stack.map(function (level) { return level.title; }))); } catch (error) {}
    });
    function reopenRemembered() {
        var path = null;
        try {
            path = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
            sessionStorage.removeItem(storageKey);
        } catch (error) {}
        if (typeof path === 'string') path = [path];
        if (!Array.isArray(path)) return;
        var scope = document;
        path.forEach(function (title) {
            if (!scope) return;
            var match = Array.prototype.find.call(scope.querySelectorAll('details[data-folder-modal]'), function (details) {
                return textOf(details, '[data-folder-title]') === title;
            });
            if (!match) { scope = null; return; }
            open(match);
            scope = stack[stack.length - 1].content;
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', reopenRemembered);
    else reopenRemembered();

    window.ktFolderModal = { open: open, close: close, back: back };
})();
