(function () {
    'use strict';
    // Confirm before undoing or deleting a role.
    document.querySelectorAll('[data-lfa-confirm]').forEach(button => {
        button.addEventListener('click', event => {
            if (!window.confirm(button.dataset.lfaConfirm)) event.preventDefault();
        });
    });

    const form = document.getElementById('lfa-role-form');
    if (!form) return;
    const search = document.getElementById('lfa-cap-search');
    const onlyTicked = document.getElementById('lfa-cap-ticked');
    const count = document.getElementById('lfa-cap-count');
    const unsaved = form.querySelector('.lfa-unsaved');
    const groups = [...form.querySelectorAll('.lfa-cap-group')];
    let dirty = false;

    function update() {
        const words = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let ticked = 0, total = 0;
        groups.forEach(group => {
            const boxes = [...group.querySelectorAll('.lfa-cap input')];
            let shown = 0;
            boxes.forEach(box => {
                const item = box.closest('.lfa-cap');
                const text = item.dataset.lfaSearch;
                const visible = words.every(word => text.includes(word)) && (!onlyTicked.checked || box.checked);
                item.hidden = !visible;
                if (visible) shown++;
            });
            const on = boxes.filter(box => box.checked).length;
            ticked += on; total += boxes.length;
            group.querySelector('.lfa-cap-group-count').textContent = on + ' of ' + boxes.length;
            group.hidden = shown === 0;
        });
        count.textContent = ticked + ' of ' + total + ' permissions ticked';
    }

    groups.forEach(group => {
        group.querySelectorAll('[data-lfa-tick]').forEach(button => {
            button.addEventListener('click', () => {
                const on = button.dataset.lfaTick === '1';
                group.querySelectorAll('.lfa-cap:not([hidden]) input:not(:disabled)').forEach(box => { box.checked = on; });
                markDirty();
                update();
            });
        });
    });

    function markDirty() {
        dirty = true;
        unsaved.hidden = false;
    }

    form.addEventListener('change', event => {
        if (event.target !== onlyTicked && event.target !== search) markDirty();
        update();
    });
    form.addEventListener('input', event => {
        if (event.target === search) update(); else if (event.target !== onlyTicked) markDirty();
    });
    // Enter in the search box filters instead of saving the role.
    search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    update();
})();
