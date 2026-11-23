(function () {
    var API = 'notifications_api.php';
    var style = document.createElement('style');
    style.textContent =
        '.kt-notif-btn{position:fixed;top:18px;right:22px;z-index:1000;width:42px;height:42px;border-radius:50%;' +
        'background:#fff;border:1px solid #CFD8CC;display:flex;align-items:center;' +
        'justify-content:center;cursor:pointer;color:#3A4A3D;box-shadow:0 1px 3px rgba(17,30,20,.08);' +
        'transition:background .2s,color .2s,box-shadow .2s;}' +
        '.kt-notif-btn:hover{background:#F1F5EF;color:#2C7A45;box-shadow:0 6px 18px rgba(17,30,20,.1);}' +
        '.kt-notif-btn:focus-visible{outline:2px solid #2C7A45;outline-offset:2px;}' +
        '.kt-notif-badge{position:absolute;top:-4px;right:-4px;background:#B93838;color:#fff;font-size:.62rem;' +
        'font-weight:700;min-width:17px;height:17px;border-radius:9px;display:flex;align-items:center;justify-content:center;' +
        'padding:0 4px;box-shadow:0 0 0 2px #fff;}' +
        '.kt-notif-panel{position:fixed;top:66px;right:22px;z-index:1000;width:330px;max-height:420px;overflow-y:auto;' +
        'background:#fff;border:1px solid #E3E8DF;border-radius:14px;box-shadow:0 24px 60px rgba(17,30,20,.18);' +
        'display:none;}' +
        '.kt-notif-panel.open{display:block;}' +
        '.kt-notif-head{position:sticky;top:0;background:#fff;padding:12px 16px;font-size:.72rem;font-weight:700;color:#5F6E62;' +
        'text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid #E3E8DF;}' +
        '.kt-notif-item{padding:12px 16px;border-bottom:1px solid #EDF1EB;cursor:pointer;' +
        'font-size:.82rem;color:#18251B;line-height:1.4;}' +
        '.kt-notif-item:hover{background:#F7F8F4;}' +
        '.kt-notif-item.unread{background:rgba(44,122,69,.07);box-shadow:inset 3px 0 0 #2C7A45;}' +
        '.kt-notif-time{font-size:.68rem;color:#5F6E62;margin-top:4px;}' +
        '.kt-notif-empty{padding:28px 16px;text-align:center;font-size:.8rem;color:#5F6E62;}';
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
