(function ($) {
    'use strict';
    const source = document.getElementById('lfa-menu-data');
    const form = document.getElementById('lfa-menu-form');
    if (!source || !form) return;
    const data = JSON.parse(source.textContent);
    const inherited = data.inherited;
    const layer = Object.assign({ items: {}, sub_order: {}, separators: [] }, data.layer);
    layer.items = Object.assign({}, layer.items);
    layer.sub_order = Object.assign({}, layer.sub_order);
    const tree = document.getElementById('lfa-menu-tree');
    const note = document.getElementById('lfa-order-note');
    const picker = document.getElementById('lfa-icon-picker');
    let orderTouched = !!layer.order;
    const subTouched = new Set(Object.keys(layer.sub_order));

    // Same placing rule as sort_by_order() in admin-menu.php.
    function sortByOrder(items, order) {
        const byId = new Map(items.map(item => [item.id, item]));
        const original = items.map(item => item.id);
        const result = (order || []).filter((id, i, all) => byId.has(id) && all.indexOf(id) === i);
        original.forEach((id, index) => {
            if (result.includes(id)) return;
            let position = 0;
            for (let j = index - 1; j >= 0; j--) {
                const found = result.indexOf(original[j]);
                if (found !== -1) { position = found + 1; break; }
            }
            result.splice(position, 0, id);
        });
        return result.map(id => byId.get(id));
    }

    function el(tag, attrs, children) {
        const node = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([key, value]) => {
            if (key === 'text') node.textContent = value;
            else if (key === 'class') node.className = value;
            else if (value !== undefined && value !== null && value !== false) node.setAttribute(key, value === true ? '' : value);
        });
        (children || []).forEach(child => child && node.appendChild(child));
        return node;
    }

    function iconNode(icon) {
        if (icon && icon.indexOf('dashicons-') === 0) return el('span', { class: 'dashicons ' + icon, 'aria-hidden': 'true' });
        if (icon && /^(data:image\/|https?:\/\/)/.test(icon)) return el('img', { src: icon, alt: '' });
        return el('span', { class: 'dashicons dashicons-admin-generic lfa-muted', 'aria-hidden': 'true' });
    }

    function inheritedTitle(item) { return inherited.titles[item.id] || item.title; }
    function inheritedIcon(item) { return inherited.icons[item.id] || item.icon; }

    function visibilitySelect(id, label) {
        const own = (layer.items[id] || {}).hidden;
        const select = el('select', { class: 'lfa-vis', 'aria-label': 'Show or hide ' + label });
        const options = data.everyone
            ? [['show', 'Shown'], ['hide', 'Hidden']]
            : [['', 'As above (' + (inherited.hidden[id] ? 'hidden' : 'shown') + ')'], ['show', 'Show'], ['hide', 'Hide']];
        options.forEach(([value, text]) => select.appendChild(el('option', { value: value, text: text })));
        select.value = own === true ? 'hide' : own === false ? (data.everyone ? 'show' : 'show') : (data.everyone ? 'show' : '');
        return select;
    }

    function hiddenNow(row) {
        const value = row.querySelector(':scope > .lfa-mi-row .lfa-vis').value;
        if (value === 'hide') return true;
        if (value === 'show') return false;
        return !!inherited.hidden[row.dataset.id];
    }

    function rowFor(item, isSub) {
        const li = el('li', { class: isSub ? 'lfa-mi lfa-si' : 'lfa-mi', 'data-id': item.id });
        const label = item.separator ? 'Separator' : (inheritedTitle(item) || item.id);
        const parts = [el('span', { class: 'lfa-drag dashicons dashicons-menu', title: 'Drag to move', 'aria-hidden': 'true' })];
        if (item.separator) {
            parts.push(el('span', { class: 'lfa-mi-separator', text: item.custom ? 'Separator (added here)' : 'Separator' }));
        } else {
            if (!isSub) {
                const iconButton = el('button', { type: 'button', class: 'lfa-mi-icon', title: 'Change icon', 'aria-label': 'Change icon for ' + label }, [iconNode((layer.items[item.id] || {}).icon || inheritedIcon(item))]);
                iconButton.addEventListener('click', () => openPicker(item, iconButton));
                parts.push(iconButton);
            }
            const input = el('input', { type: 'text', class: 'lfa-mi-title', maxlength: '60', placeholder: inheritedTitle(item) || item.id, 'aria-label': 'Name for ' + label });
            input.value = (layer.items[item.id] || {}).title || '';
            parts.push(input);
            if (inheritedTitle(item) !== item.title && item.title) parts.push(el('span', { class: 'lfa-mi-original', text: 'WordPress: ' + item.title }));
        }
        if (data.protected.includes(item.id)) parts.push(el('span', { class: 'lfa-tag', title: 'Saving is refused if this would hide it from you.', text: 'Stays for you' }));
        parts.push(visibilitySelect(item.id, label));
        if (item.custom && (layer.separators || []).includes(item.id)) {
            const remove = el('button', { type: 'button', class: 'button-link lfa-remove', text: 'Remove' });
            remove.addEventListener('click', () => {
                layer.separators = layer.separators.filter(id => id !== item.id);
                li.remove();
                orderTouched = true;
                window.lfaMarkDirty(form);
                refresh();
            });
            parts.push(remove);
        }
        const subs = item.subs || [];
        let list = null;
        if (subs.length) {
            const toggle = el('button', { type: 'button', class: 'button-link lfa-mi-toggle', 'aria-expanded': 'false', text: subs.length + (subs.length === 1 ? ' sub-item' : ' sub-items') });
            parts.push(toggle);
            list = el('ul', { class: 'lfa-mi-subs', hidden: true });
            const order = layer.sub_order[item.id] || inherited.sub_order[item.id];
            sortByOrder(subs, order).forEach(sub => list.appendChild(rowFor(sub, true)));
            toggle.addEventListener('click', () => {
                list.hidden = !list.hidden;
                toggle.setAttribute('aria-expanded', String(!list.hidden));
            });
        }
        li.appendChild(el('div', { class: 'lfa-mi-row' }, parts));
        if (list) li.appendChild(list);
        return li;
    }

    function topItems() {
        const separators = [...new Set([...(inherited.separators || []), ...(layer.separators || [])])]
            .map(id => ({ id: id, separator: true, custom: true, title: '' }));
        return data.original.concat(separators);
    }

    function render() {
        tree.textContent = '';
        sortByOrder(topItems(), layer.order || inherited.order).forEach(item => tree.appendChild(rowFor(item, false)));
        // Each list drags only its own rows, by their own handle.
        $(tree).sortable({ handle: '> .lfa-mi-row .lfa-drag', axis: 'y', items: '> li', update: () => { orderTouched = true; window.lfaMarkDirty(form); refresh(); } });
        $(tree).find('.lfa-mi-subs').sortable({ handle: '> .lfa-mi-row .lfa-drag', axis: 'y', items: '> li', update: event => { subTouched.add(event.target.closest('.lfa-mi').dataset.id); window.lfaMarkDirty(form); } });
        refresh();
    }

    function refresh() {
        tree.querySelectorAll('.lfa-mi').forEach(row => row.classList.toggle('is-hidden', hiddenNow(row)));
        note.textContent = '';
        if (orderTouched && !data.everyone) {
            note.append('This layer has its own order. ');
            const reset = el('button', { type: 'button', class: 'button-link', text: 'Use the order from above' });
            reset.addEventListener('click', () => { delete layer.order; orderTouched = false; window.lfaMarkDirty(form); render(); });
            note.appendChild(reset);
        } else if (orderTouched && data.everyone) {
            const reset = el('button', { type: 'button', class: 'button-link', text: 'Use WordPress’s order' });
            reset.addEventListener('click', () => { delete layer.order; orderTouched = false; window.lfaMarkDirty(form); render(); });
            note.appendChild(reset);
        }
    }

    tree.addEventListener('change', event => { if (event.target.classList.contains('lfa-vis')) refresh(); });

    document.getElementById('lfa-add-separator').addEventListener('click', () => {
        const id = 'separator-lfa-' + Math.random().toString(36).slice(2, 10).padEnd(8, '0');
        layer.separators = (layer.separators || []).concat(id);
        const row = rowFor({ id: id, separator: true, custom: true, title: '' }, false);
        tree.insertBefore(row, tree.firstChild);
        orderTouched = true;
        window.lfaMarkDirty(form);
        row.scrollIntoView({ block: 'center' });
        refresh();
    });

    const toggleAll = document.getElementById('lfa-toggle-subs');
    toggleAll.addEventListener('click', () => {
        const open = toggleAll.getAttribute('aria-expanded') !== 'true';
        tree.querySelectorAll('.lfa-mi-subs').forEach(list => { list.hidden = !open; });
        tree.querySelectorAll('.lfa-mi-toggle').forEach(button => button.setAttribute('aria-expanded', String(open)));
        toggleAll.setAttribute('aria-expanded', String(open));
        toggleAll.textContent = open ? 'Hide all sub-items' : 'Show all sub-items';
    });

    // Icon picker, placed on the page body so it lines up with the clicked icon.
    document.body.appendChild(picker);
    let pickerItem = null, pickerButton = null;
    const grid = picker.querySelector('.lfa-icon-grid');
    const search = picker.querySelector('input');
    function fillGrid() {
        const words = search.value.trim().toLowerCase();
        grid.textContent = '';
        data.dashicons.filter(name => !words || name.includes(words)).slice(0, 400).forEach(name => {
            const button = el('button', { type: 'button', class: 'lfa-icon-choice', title: name, 'aria-label': name, role: 'option' }, [el('span', { class: 'dashicons dashicons-' + name, 'aria-hidden': 'true' })]);
            button.addEventListener('click', () => setIcon('dashicons-' + name));
            grid.appendChild(button);
        });
    }
    function openPicker(item, button) {
        pickerItem = item; pickerButton = button;
        const box = button.getBoundingClientRect();
        picker.hidden = false;
        search.value = '';
        fillGrid();
        // Open upwards when there isn't room below the icon.
        const below = box.bottom + 4 + picker.offsetHeight <= window.innerHeight;
        picker.style.top = (window.scrollY + (below ? box.bottom + 4 : Math.max(4, box.top - 4 - picker.offsetHeight))) + 'px';
        picker.style.left = (window.scrollX + Math.min(box.left, window.innerWidth - picker.offsetWidth - 16)) + 'px';
        search.focus();
    }
    function closePicker() {
        picker.hidden = true;
        if (pickerButton) pickerButton.focus();
        pickerItem = pickerButton = null;
    }
    function setIcon(icon) {
        const entry = layer.items[pickerItem.id] = Object.assign({}, layer.items[pickerItem.id]);
        if (icon) entry.icon = icon; else delete entry.icon;
        pickerButton.textContent = '';
        pickerButton.appendChild(iconNode(icon || inheritedIcon(pickerItem)));
        window.lfaMarkDirty(form);
        closePicker();
    }
    search.addEventListener('input', fillGrid);
    picker.querySelector('[data-lfa-icon-reset]').addEventListener('click', () => setIcon(''));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !picker.hidden) closePicker(); });
    document.addEventListener('mousedown', event => {
        if (!picker.hidden && !picker.contains(event.target) && !event.target.closest('.lfa-mi-icon')) closePicker();
    });

    function currentOrder() {
        return [...tree.querySelectorAll(':scope > .lfa-mi')].map(row => row.dataset.id);
    }

    form.addEventListener('submit', event => {
        if (event.submitter && event.submitter.name === 'lfa_reset') return;
        const items = {};
        tree.querySelectorAll('.lfa-mi').forEach(row => {
            const id = row.dataset.id;
            const entry = {};
            const vis = row.querySelector(':scope > .lfa-mi-row .lfa-vis').value;
            if (vis === 'hide') entry.hidden = true;
            else if (vis === 'show' && !data.everyone) entry.hidden = false;
            const input = row.querySelector(':scope > .lfa-mi-row .lfa-mi-title');
            if (input && input.value.trim()) entry.title = input.value.trim();
            if ((layer.items[id] || {}).icon) entry.icon = layer.items[id].icon;
            if (Object.keys(entry).length) items[id] = entry;
        });
        const subOrder = {};
        subTouched.forEach(parent => {
            const list = tree.querySelector('.lfa-mi[data-id="' + CSS.escape(parent) + '"] > .lfa-mi-subs');
            if (list) subOrder[parent] = [...list.children].map(row => row.dataset.id);
        });
        document.getElementById('lfa-menu-json').value = JSON.stringify({
            order: orderTouched ? currentOrder() : [],
            items: items,
            sub_order: subOrder,
            separators: (layer.separators || []).filter(id => tree.querySelector('.lfa-mi[data-id="' + CSS.escape(id) + '"]')),
        });
    });

    render();
})(jQuery);
