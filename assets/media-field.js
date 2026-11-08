(function ($) {
    'use strict';
    // Image fields (Media Library picker) and colour fields on the Login page tab.
    const form = document.querySelector('.lfa-settings form.lfa-track');
    document.querySelectorAll('.lfa-media-field').forEach(field => {
        const input = field.querySelector('input[type="hidden"]');
        const preview = field.querySelector('.lfa-media-preview');
        const choose = field.querySelector('[data-lfa-media-choose]');
        const clear = field.querySelector('[data-lfa-media-clear]');
        let frame;
        function set(id, url) {
            input.value = id || 0;
            preview.src = url || '';
            preview.hidden = !url;
            clear.hidden = !url;
            window.lfaMarkDirty(form);
        }
        if (choose) {
            choose.addEventListener('click', () => {
                if (!frame) {
                    frame = wp.media({ title: field.dataset.lfaMediaTitle, library: { type: 'image' }, button: { text: 'Use This Image' }, multiple: false });
                    frame.on('select', () => {
                        const image = frame.state().get('selection').first().toJSON();
                        set(image.id, (image.sizes && image.sizes.medium && image.sizes.medium.url) || image.url);
                    });
                }
                frame.open();
            });
        }
        clear.addEventListener('click', () => set(0, ''));
    });
    $('.lfa-colour-input').wpColorPicker({
        change: () => window.lfaMarkDirty(form),
        clear: () => window.lfaMarkDirty(form),
    });
})(jQuery);
