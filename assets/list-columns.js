(function ($) {
    'use strict';
    // Drag rows to reorder columns; the hidden order[] inputs follow the rows.
    const list = $('#lfa-columns');
    if (!list.length) return;
    list.sortable({
        items: '> tr:has(.lfa-drag)',
        handle: '.lfa-drag',
        axis: 'y',
        helper: (event, row) => { row.children().each(function () { $(this).width($(this).width()); }); return row; },
        update: () => window.lfaMarkDirty(list.closest('form')[0]),
    });
})(jQuery);
