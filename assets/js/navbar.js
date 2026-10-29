// KULTOURA — Shared navbar hamburger toggle (mobile).
// Included on every page that uses the shared navbar markup.
(function () {
    // One page-aware guide across the public site. navbar.js is shared by
    // the public pages, so the assistant follows the visitor as they browse.
    if (document.querySelector('.navbar') && !document.getElementById('ktAiGuide')) {
        const scriptUrl = document.currentScript && document.currentScript.src;
        if (scriptUrl) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = new URL('../../assets/css/ai-guide.css', scriptUrl).href;
            document.head.appendChild(css);

            const root = document.createElement('div');
            root.id = 'ktAiGuide';
            root.className = 'kt-ai-guide';
            root.innerHTML = '<button type="button" class="kt-ai-guide-launch" aria-expanded="false" aria-controls="ktAiGuidePanel"><span aria-hidden="true">✦</span><span>Ask KulToura</span></button>' +
                '<section class="kt-ai-guide-panel" id="ktAiGuidePanel" aria-label="KulToura AI travel guide" hidden>' +
                '<header class="kt-ai-guide-head"><div><strong>KulToura Guide</strong><span>Ask about Malvar and this page</span></div><button type="button" class="kt-ai-guide-close" aria-label="Close">×</button></header>' +
                '<div class="kt-ai-guide-messages" aria-live="polite"><p class="kt-ai-guide-welcome">I can help you explore places, food, events, and local culture.</p></div>' +
                '<form class="kt-ai-guide-form"><label class="kt-ai-guide-sr" for="ktAiGuideQuestion">Your question</label><textarea id="ktAiGuideQuestion" maxlength="600" rows="2" placeholder="Ask about what you’re viewing…" required></textarea><button type="submit" aria-label="Send question">Send</button></form>' +
                '</section>';
            document.body.appendChild(root);

            const endpoint = new URL('../../ai_guide.php', scriptUrl).href;
            const launcher = root.querySelector('.kt-ai-guide-launch');
            const panel = root.querySelector('.kt-ai-guide-panel');
            const close = root.querySelector('.kt-ai-guide-close');
            const form = root.querySelector('.kt-ai-guide-form');
            const input = root.querySelector('#ktAiGuideQuestion');
            const messages = root.querySelector('.kt-ai-guide-messages');
            const history = [];

            function setOpen(open) {
                panel.hidden = !open;
                launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) input.focus();
            }
            function addMessage(text, type) {
                const message = document.createElement('p');
                message.className = 'kt-ai-guide-message kt-ai-guide-message-' + type;
                message.textContent = text;
                messages.appendChild(message);
                messages.scrollTop = messages.scrollHeight;
                return message;
            }

            launcher.addEventListener('click', () => setOpen(panel.hidden));
            close.addEventListener('click', () => setOpen(false));
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const question = input.value.trim();
                if (!question) return;
                addMessage(question, 'user');
                history.push({ role: 'user', content: question });
                input.value = '';
                const pending = addMessage('Thinking…', 'assistant');
                const send = form.querySelector('button[type="submit"]');
                send.disabled = true;
                try {
                    const privateAccountPage = /\/(traveldiary|favorites)\.php$/i.test(location.pathname);
                    const pageText = privateAccountPage ? '' : (document.querySelector('main') || document.body).innerText.slice(0, 3500);
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            question,
                            page: location.pathname,
                            title: document.title,
                            context: pageText,
                            history: history.slice(-6),
                        }),
                    });
                    const result = await response.json();
                    pending.textContent = result.answer || result.message || 'I could not get an answer right now. Please try again.';
                    history.push({ role: 'assistant', content: pending.textContent });
                    if (history.length > 8) history.splice(0, history.length - 8);
                } catch (_) {
                    pending.textContent = 'I could not connect just now. Please try again.';
                } finally {
                    send.disabled = false;
                    input.focus();
                    messages.scrollTop = messages.scrollHeight;
                }
            });
        }
    }

    // Exposes the navbar's real rendered height as --navbar-h so the
    // mobile dropdown (see @media (max-width:768px) in index.css) can
    // fill exactly the remaining screen instead of being capped at a
    // guessed vh value and getting cut short with page content peeking
    // in below it.
    function updateNavbarHeightVar() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            document.documentElement.style.setProperty('--navbar-h', navbar.offsetHeight + 'px');
        }
    }
    updateNavbarHeightVar();
    window.addEventListener('resize', updateNavbarHeightVar);

    // While the mobile menu is open, background scroll is fully locked
    // so the navbar bar can't scroll away and leave the fixed-position
    // dropdown (see index.css) floating with nothing above it. Plain
    // `overflow:hidden` on body isn't reliable on iOS Safari, so this
    // pins <body> itself with position:fixed at its current scroll
    // offset (the standard cross-browser scroll-lock technique) and
    // restores the exact scroll position on close.
    let lockedScrollY = 0;

    function lockBodyScroll() {
        lockedScrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = -lockedScrollY + 'px';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.classList.add('nav-menu-open');
    }

    function unlockBodyScroll() {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.classList.remove('nav-menu-open');
        window.scrollTo(0, lockedScrollY);
    }

    function closeNavbar(navbar) {
        navbar.classList.remove('nav-open');
        unlockBodyScroll();
        const toggle = navbar.querySelector('.navbar-hamburger');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.navbar-hamburger');
        if (toggle) {
            const navbar = toggle.closest('.navbar');
            if (!navbar) return;
            const isOpen = navbar.classList.toggle('nav-open');
            if (isOpen) {
                lockBodyScroll();
            } else {
                unlockBodyScroll();
            }
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            return;
        }

        const openNavbar = document.querySelector('.navbar.nav-open');
        if (openNavbar && !openNavbar.contains(e.target)) {
            closeNavbar(openNavbar);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const openNavbar = document.querySelector('.navbar.nav-open');
        if (openNavbar) closeNavbar(openNavbar);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            document.querySelectorAll('.navbar.nav-open').forEach(closeNavbar);
        }
    });
})();
