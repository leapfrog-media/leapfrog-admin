(function () {
    'use strict';
    // Profile picture picker on Users > Profile. Saved with Update Profile.
    const input = document.getElementById('lfa-avatar');
    const choose = document.getElementById('lfa-avatar-choose');
    const clear = document.getElementById('lfa-avatar-clear');
    const status = document.getElementById('lfa-avatar-status');
    if (!input || !choose) return;
    const preview = document.querySelector('.user-profile-picture img.avatar');
    const original = preview ? { src: preview.getAttribute('src'), srcset: preview.getAttribute('srcset') } : null;
    let frame;
    function show(url) {
        if (!preview) return;
        if (url) { preview.src = url; preview.removeAttribute('srcset'); }
        else if (original) { preview.src = original.src; if (original.srcset) preview.setAttribute('srcset', original.srcset); }
    }
    choose.addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({ title: 'Choose a profile picture', library: { type: 'image' }, button: { text: 'Use This Picture' }, multiple: false });
            frame.on('select', () => {
                const image = frame.state().get('selection').first().toJSON();
                input.value = image.id;
                show((image.sizes && image.sizes.thumbnail && image.sizes.thumbnail.url) || image.url);
                clear.hidden = false;
                status.textContent = 'Picture chosen. Click Update Profile to save it.';
            });
        }
        frame.open();
    });
    clear.addEventListener('click', () => {
        input.value = 0;
        clear.hidden = true;
        status.textContent = 'Click Update Profile to go back to Gravatar.';
        show('');
    });
})();
