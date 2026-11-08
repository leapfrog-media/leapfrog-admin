(function () {
    'use strict';
    // Unsaved-change warnings and confirm buttons come from page.js.
    const form = document.getElementById('lfa-role-form');
    if (!form) return;
    const search = document.getElementById('lfa-cap-search');
    const onlyTicked = document.getElementById('lfa-cap-ticked');
    const count = document.getElementById('lfa-cap-count');
    const groups = [...form.querySelectorAll('.lfa-cap-group')];

    function update() {
        const words = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let ticked = 0, total = 0;
        groups.forEach(group => {
            const boxes = [...group.querySelectorAll('.lfa-cap input')];
            let shown = 0;
            boxes.forEach(box => {
                const item = box.closest('.lfa-cap');
                const visible = words.every(word => item.dataset.lfaSearch.includes(word)) && (!onlyTicked.checked || box.checked);
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
                window.lfaMarkDirty(form);
                update();
            });
        });
    });

    form.addEventListener('change', update);
    search.addEventListener('input', update);
    // Enter in the search box filters instead of saving the role.
    search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    update();
})();
