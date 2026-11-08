(function ($) {
    'use strict';
    // Drag items between and within lists. Every item has a (maybe empty) list
    // under it, which opens up while dragging so an item can be dropped inside.
    const form = document.getElementById('lfa-tree-form');
    if (!form) return;
    const root = form.querySelector('.lfa-tree-root > .lfa-tree');

    function refreshToggles() {
        form.querySelectorAll('.lfa-tree-item').forEach(item => {
            const list = item.querySelector(':scope > .lfa-tree');
            item.querySelector(':scope > .lfa-tree-row .lfa-tree-toggle').hidden = !list.children.length;
        });
    }

    $(form).find('.lfa-tree').sortable({
        connectWith: '#lfa-tree-form .lfa-tree',
        handle: '> .lfa-tree-row .lfa-drag',
        items: '> li',
        placeholder: 'lfa-tree-placeholder',
        tolerance: 'pointer',
        cursorAt: { left: 16, top: 12 },
        start: () => document.body.classList.add('lfa-tree-dragging'),
        stop: () => { document.body.classList.remove('lfa-tree-dragging'); refreshToggles(); window.lfaMarkDirty(form); },
    });

    form.addEventListener('click', event => {
        const toggle = event.target.closest('.lfa-tree-toggle');
        if (toggle) {
            const list = toggle.closest('.lfa-tree-item').querySelector(':scope > .lfa-tree');
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            list.classList.toggle('is-collapsed', !open);
            toggle.setAttribute('aria-expanded', String(open));
            return;
        }
        const all = event.target.closest('[data-lfa-tree-all]');
        if (all) {
            const open = all.dataset.lfaTreeAll === '1';
            form.querySelectorAll('.lfa-tree-toggle').forEach(button => {
                button.setAttribute('aria-expanded', String(open));
                button.closest('.lfa-tree-item').querySelector(':scope > .lfa-tree').classList.toggle('is-collapsed', !open);
            });
        }
    });

    form.addEventListener('submit', () => {
        const rows = [];
        (function walk(list, parent) {
            [...list.children].forEach((item, index) => {
                const id = Number(item.dataset.id);
                rows.push({ id: id, parent: parent, order: index });
                walk(item.querySelector(':scope > .lfa-tree'), id);
            });
        })(root, 0);
        document.getElementById('lfa-tree-json').value = JSON.stringify(rows);
    });
})(jQuery);
