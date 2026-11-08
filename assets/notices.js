(function ($) {
    'use strict';
    // Runs after WordPress's common.js has placed notices under the page title.
    $(function () {
        const node = document.getElementById('wp-admin-bar-lfa-notices');
        if (!node) return;
        const link = node.querySelector('.ab-item');
        const count = node.querySelector('.lfa-notices-count');

        const panel = document.createElement('div');
        panel.id = 'lfa-notices-panel';
        panel.hidden = true;
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-label', 'Notices');
        panel.innerHTML = '<div class="lfa-notices-head"><h2>Notices</h2><button type="button" class="button-link">Close</button></div><div class="lfa-notices-list"></div>';
        document.body.appendChild(panel);
        const list = panel.querySelector('.lfa-notices-list');

        // Messages straight after an action, inline messages, and ones inside forms stay put.
        const keep = el => el.matches('.inline, .below-h2, .hidden, #message, .settings-error, .lfa-notice-keep') || el.closest('form, .media-modal, #lfa-notices-panel');
        document.querySelectorAll('#wpbody-content div.notice, #wpbody-content div.error, #wpbody-content div.updated').forEach(notice => {
            if (notice.parentElement.closest('div.notice, div.error, div.updated')) return;
            if (keep(notice)) { notice.classList.add('lfa-notice-keep'); return; }
            list.appendChild(notice);
        });

        function refresh() {
            const notices = list.querySelectorAll(':scope > div');
            count.textContent = notices.length;
            node.classList.toggle('lfa-notices-empty', notices.length === 0);
            node.classList.toggle('lfa-notices-has-error', !!list.querySelector('.notice-error, div.error'));
            if (!notices.length) panel.hidden = true;
        }
        // Dismissing a notice removes it; keep the count right.
        new MutationObserver(refresh).observe(list, { childList: true });
        refresh();

        function close() {
            panel.hidden = true;
            link.setAttribute('aria-expanded', 'false');
        }
        link.setAttribute('aria-expanded', 'false');
        link.setAttribute('aria-controls', 'lfa-notices-panel');
        link.addEventListener('click', event => {
            event.preventDefault();
            panel.hidden = !panel.hidden;
            link.setAttribute('aria-expanded', String(!panel.hidden));
        });
        panel.querySelector('.lfa-notices-head button').addEventListener('click', () => { close(); link.focus(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { close(); link.focus(); } });
        document.addEventListener('click', event => {
            if (!panel.hidden && !panel.contains(event.target) && !node.contains(event.target)) close();
        });
    });
})(jQuery);
