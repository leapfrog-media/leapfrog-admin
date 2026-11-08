(function () {
    'use strict';
    // Add and remove rows in "Your links". Unsaved-change warnings come from page.js.
    const list = document.getElementById('lfa-links');
    const template = document.getElementById('lfa-link-template');
    const add = document.getElementById('lfa-add-link');
    if (!list || !template || !add) return;
    let next = list.children.length;
    add.addEventListener('click', () => {
        const html = template.innerHTML.split('__i__').join('n' + (next++));
        list.insertAdjacentHTML('beforeend', html);
        list.lastElementChild.querySelector('input[type="text"]').focus();
        window.lfaMarkDirty(list.closest('form'));
    });
    list.addEventListener('click', event => {
        if (!event.target.matches('[data-lfa-remove-link]')) return;
        event.target.closest('tr').remove();
        window.lfaMarkDirty(list.closest('form'));
    });
})();
