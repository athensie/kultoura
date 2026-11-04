(function () {
    const params = new URLSearchParams(window.location.search);
    const itemId = params.get('open_item');
    if (!itemId || !/^\d+$/.test(itemId)) return;

    function openRequestedItem() {
        let opened = false;

        if (typeof window.ktOpenViewDetails === 'function') {
            const button = document.querySelector('.p-btn-primary[data-id="' + itemId + '"]');
            if (button) {
                window.ktOpenViewDetails(button);
                opened = true;
            }
        } else if (typeof window.openViewDetails === 'function') {
            window.openViewDetails(Number(itemId));
            opened = true;
        }

        if (opened) {
            params.delete('open_item');
            const query = params.toString();
            window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openRequestedItem, { once: true });
    } else {
        openRequestedItem();
    }
})();
