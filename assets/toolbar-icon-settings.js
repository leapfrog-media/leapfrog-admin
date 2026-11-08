(function () {
    'use strict';
    const chooseIcon = document.getElementById('lfa-choose-toolbar-icon');
    if (!chooseIcon) return;
    let frame;
    chooseIcon.addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({ title: 'Choose Toolbar Icon', library: { type: 'image' }, button: { text: 'Use This Icon' }, multiple: false });
            frame.on('select', () => {
                const selected = frame.state().get('selection').first().toJSON();
                const url = selected.sizes?.thumbnail?.url || selected.url;
                document.getElementById('lfa-toolbar-icon-id').value = selected.id;
                const sample = document.getElementById('lfa-toolbar-icon-sample');
                sample.src = url; sample.hidden = false;
                document.getElementById('lfa-toolbar-icon-default').hidden = true;
                document.getElementById('lfa-save-toolbar-icon').disabled = false;
                document.getElementById('lfa-toolbar-icon-status').textContent = 'Image selected. Click Save Toolbar Icon to apply.';
            });
            frame.on('open', () => {
                const id = Number(document.getElementById('lfa-toolbar-icon-id').value);
                frame.state().get('selection').reset();
                if (id) {
                    const attachment = wp.media.attachment(id);
                    attachment.fetch();
                    frame.state().get('selection').add(attachment);
                }
            });
        }
        frame.open();
    });
})();
