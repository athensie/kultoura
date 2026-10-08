// KulToura Admin — notification bell (Admin + Super Admin).
// Self-contained: injects its own markup/styles so every admin page only
// needs one <script src> line to get it.
(function () {
    var API = 'notifications_api.php'; // only ever loaded from pages inside /admin/

    var style = document.createElement('style');
    style.textContent =
        '.kt-notif-btn{position:fixed;top:18px;right:22px;z-index:1000;width:42px;height:42px;border-radius:50%;' +
        'background:rgba(255,255,255,.06);border:1px solid rgba(245,237,216,.12);display:flex;align-items:center;' +
        'justify-content:center;cursor:pointer;color:rgba(245,237,216,.8);}' +
        '.kt-notif-btn:hover{background:rgba(255,255,255,.1);}' +
        '.kt-notif-badge{position:absolute;top:-4px;right:-4px;background:#C9572A;color:#fff;font-size:.62rem;' +
        'font-weight:700;min-width:17px;height:17px;border-radius:9px;display:flex;align-items:center;justify-content:center;' +
        'padding:0 4px;}' +
        '.kt-notif-panel{position:fixed;top:66px;right:22px;z-index:1000;width:330px;max-height:420px;overflow-y:auto;' +
        'background:#1e1d1a;border:1px solid rgba(245,237,216,.12);border-radius:12px;box-shadow:0 14px 40px rgba(0,0,0,.5);' +
        'display:none;}' +
        '.kt-notif-panel.open{display:block;}' +
        '.kt-notif-head{padding:12px 16px;font-size:.78rem;font-weight:600;color:rgba(245,237,216,.5);' +
        'text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(245,237,216,.08);}' +
        '.kt-notif-item{padding:12px 16px;border-bottom:1px solid rgba(245,237,216,.06);cursor:pointer;' +
        'font-size:.82rem;color:rgba(245,237,216,.85);line-height:1.4;}' +
        '.kt-notif-item:hover{background:rgba(255,255,255,.04);}' +
        '.kt-notif-item.unread{background:rgba(200,169,110,.06);}' +
        '.kt-notif-time{font-size:.68rem;color:rgba(245,237,216,.35);margin-top:4px;}' +
        '.kt-notif-empty{padding:28px 16px;text-align:center;font-size:.8rem;color:rgba(245,237,216,.35);}';
    document.head.appendChild(style);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'kt-notif-btn';
    btn.setAttribute('aria-label', 'Notifications');
    btn.innerHTML =
        '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>' +
        '<span class="kt-notif-badge" style="display:none;"></span>';

    var panel = document.createElement('div');
    panel.className = 'kt-notif-panel';
    panel.innerHTML = '<div class="kt-notif-head">Notifications</div><div class="kt-notif-body"><div class="kt-notif-empty">Loading…</div></div>';

    document.addEventListener('DOMContentLoaded', function () {
        document.body.appendChild(btn);
        document.body.appendChild(panel);
    });

    function timeAgo(dateStr) {
        var diff = (Date.now() - new Date(dateStr.replace(' ', 'T') + 'Z').getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function render(data) {
        var badge = btn.querySelector('.kt-notif-badge');
        if (data.unread > 0) {
            badge.style.display = 'flex';
            badge.textContent = data.unread > 99 ? '99+' : data.unread;
        } else {
            badge.style.display = 'none';
        }

        var body = panel.querySelector('.kt-notif-body');
        if (!data.notifications || !data.notifications.length) {
            body.innerHTML = '<div class="kt-notif-empty">No notifications yet.</div>';
            return;
        }
        body.innerHTML = '';
        data.notifications.forEach(function (n) {
            var item = document.createElement('div');
            item.className = 'kt-notif-item' + (n.isRead ? '' : ' unread');
            item.innerHTML = '<div>' + n.message.replace(/</g, '&lt;') + '</div><div class="kt-notif-time">' + timeAgo(n.createdAt) + '</div>';
            item.addEventListener('click', function () {
                if (n.link) window.location.href = n.link;
            });
            body.appendChild(item);
        });
    }

    function load() {
        fetch(API + '?action=list', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        load();
        setInterval(load, 25000);

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = panel.classList.contains('open');
            panel.classList.toggle('open');
            if (!wasOpen) {
                fetch(API, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'mark_all_read' }) })
                    .then(load)
                    .catch(function () {});
            }
        });

        document.addEventListener('click', function (e) {
            if (!panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                panel.classList.remove('open');
            }
        });
    });
})();
