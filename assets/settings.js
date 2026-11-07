(function ($) {
    'use strict';
    const preview = document.querySelector('.nac-colour-preview');
    if (!preview) return;
    const fields = document.querySelectorAll('[data-nac-key]');
    const values = Object.fromEntries([...fields].map(field => [field.dataset.nacKey, field.value]));
    const defaults = { navigation: '#262626', accent: '#ff6f60', action: '#b13f32', canvas: '#f6f5f3', active_plugin: '#f0f6fc' };
    function hex(value) {
        if (/^#[a-f\d]{3}$/i.test(value)) return '#' + [...value.slice(1)].map(c => c + c).join('');
        return /^#[a-f\d]{6}$/i.test(value) ? value.toLowerCase() : null;
    }
    function luminance(hex) {
        const c = hex.match(/[a-f\d]{2}/gi).map(n => parseInt(n, 16) / 255).map(n => n <= .04045 ? n / 12.92 : Math.pow((n + .055) / 1.055, 2.4));
        return c[0] * .2126 + c[1] * .7152 + c[2] * .0722;
    }
    function contrast(a, b) {
        a = luminance(a); b = luminance(b);
        return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
    }
    function ink(bg) { return contrast('#ffffff', bg) >= contrast('#161616', bg) ? '#ffffff' : '#161616'; }
    function shade(value, amount) {
        return '#' + value.match(/[a-f\d]{2}/gi).map(v => {
            const n = parseInt(v, 16);
            return Math.round(n + ((amount >= 0 ? 255 : 0) - n) * Math.abs(amount)).toString(16).padStart(2, '0');
        }).join('');
    }
    function render() {
        const c = {};
        Object.entries(defaults).forEach(([key, fallback]) => { c[key] = hex(values[key]) || fallback; });
        c.toolbar = hex(values.toolbar) || c.navigation;
        c.submenu = hex(values.submenu) || shade(c.navigation, -.22);
        c.link = hex(values.link) || c.action;
        for (let n = 0; n < 25 && (contrast(c.link, '#ffffff') < 4.5 || contrast(c.link, c.canvas) < 4.5); n++) c.link = shade(c.link, -.1);
        c.menu_text = hex(values.menu_text) || ink(c.navigation);
        c.menu_selected_text = hex(values.menu_selected_text) || ink(c.accent);
        c.menu_focus_text = hex(values.menu_focus_text) || ink(c.accent);
        c.toolbar_text = hex(values.toolbar_text) || ink(c.toolbar);
        c.toolbar_surface = shade(c.toolbar, -.22);
        c.toolbar_focus_text = hex(values.toolbar_focus_text) || ink(c.toolbar_surface);
        c.submenu_text = hex(values.submenu_text) || ink(c.submenu);
        const styles = { nav: c.navigation, 'nav-ink': c.menu_text, toolbar: c.toolbar, 'toolbar-ink': c.toolbar_text, submenu: c.submenu, 'submenu-ink': c.submenu_text, accent: c.accent, 'accent-ink': ink(c.accent), action: c.action, 'action-ink': ink(c.action), canvas: c.canvas, 'canvas-ink': ink(c.canvas), link: c.link, 'active-plugin': c.active_plugin, 'active-plugin-ink': ink(c.active_plugin) };
        Object.entries(styles).forEach(([key, value]) => preview.style.setProperty('--preview-' + key, value));
        preview.style.setProperty('--preview-menu-selected-text', c.menu_selected_text);
        preview.style.setProperty('--preview-menu-focus-text', c.menu_focus_text);
        preview.style.setProperty('--preview-toolbar-surface', c.toolbar_surface);
        preview.style.setProperty('--preview-toolbar-focus-text', c.toolbar_focus_text);
        [['menu_width', 140, 320, 160], ['menu_font_size', 11, 20, 13], ['button_radius', 0, 16, 3]].forEach(([key, min, max, fallback]) => {
            const value = Number(values[key]);
            preview.style.setProperty('--preview-' + key.replaceAll('_', '-'), (Number.isInteger(value) && value >= min && value <= max ? value : fallback) + 'px');
        });
        const warnings = [];
        if (luminance(c.canvas) < .7 || luminance(c.active_plugin) < .7) warnings.push('Use a light page and active plugin background so WordPress content stays readable.');
        if (contrast(c.menu_text, c.navigation) < 4.5 || contrast(c.toolbar_text, c.toolbar) < 4.5 || contrast(c.submenu_text, c.submenu) < 4.5) warnings.push('Some text has low contrast. Clear its colour to use Automatic.');
        if (contrast(c.toolbar_focus_text, c.toolbar_surface) < 4.5) warnings.push('Toolbar hover and focus text has low contrast. Clear its colour to use Automatic.');
        if (contrast(c.menu_selected_text, c.accent) < 4.5 || contrast(c.menu_focus_text, c.accent) < 4.5) warnings.push('Sidebar selected or focused text has low contrast. Clear its colour to use Automatic.');
        document.querySelector('.nac-preview-warning').textContent = warnings.join(' ');
    }
    fields.forEach(field => {
        const key = field.dataset.nacKey;
        if (field.type !== 'number') {
            $(field).wpColorPicker({
                change: function (event, ui) { values[key] = ui.color.toString(); render(); },
                clear: function () { values[key] = ''; render(); }
            });
            const label = document.querySelector('label[for="' + field.id + '"]');
            const container = field.closest('.wp-picker-container');
            const button = container.querySelector('.wp-color-result');
            if (label && button) button.setAttribute('aria-label', 'Choose colour: ' + label.textContent);
            if (field.dataset.defaultColor === '') {
                const auto = document.createElement('button');
                auto.type = 'button'; auto.className = 'button nac-auto'; auto.textContent = 'Automatic';
                auto.setAttribute('aria-label', 'Automatic colour: ' + label.textContent);
                auto.addEventListener('click', () => { field.value = ''; values[key] = ''; render(); $(field).trigger('change'); button.style.backgroundColor = ''; });
                container.after(auto);
            }
        }
        ['input', 'change'].forEach(event => field.addEventListener(event, () => { values[key] = field.value; render(); }));
    });
    render();
    const file = document.getElementById('nac-import-file');
    const json = document.getElementById('nac-import-json');
    const status = document.getElementById('nac-import-status');
    const submit = document.getElementById('nac-import-button');
    file.addEventListener('change', async () => {
        json.setCustomValidity('');
        if (!file.files.length) return;
        json.value = ''; submit.disabled = true;
        try {
            if (file.files[0].size > 65536) throw new Error('Choose a file smaller than 64 KB.');
            const text = await file.files[0].text();
            const data = JSON.parse(text);
            if (data.plugin !== 'native-admin-colours' || data.format_version !== 1 || !data.settings) throw new Error('Choose a Native Admin Colours export file.');
            json.value = text;
            status.textContent = 'File ready. Click Import and Apply to replace the current settings.';
        } catch (error) {
            status.textContent = error instanceof SyntaxError ? 'The file does not contain valid JSON.' : error.message;
            json.setCustomValidity(status.textContent);
        } finally { submit.disabled = false; }
    });
    json.addEventListener('input', () => { json.setCustomValidity(''); });
    const chooseIcon = document.getElementById('nac-choose-toolbar-icon');
    if (chooseIcon) {
        let frame;
        chooseIcon.addEventListener('click', () => {
            if (!frame) {
                frame = wp.media({ title: 'Choose Toolbar Icon', library: { type: 'image' }, button: { text: 'Use This Icon' }, multiple: false });
                frame.on('select', () => {
                    const selected = frame.state().get('selection').first().toJSON();
                    const url = selected.sizes?.thumbnail?.url || selected.url;
                    document.getElementById('nac-toolbar-icon-id').value = selected.id;
                    const sample = document.getElementById('nac-toolbar-icon-sample');
                    sample.src = url; sample.hidden = false;
                    document.getElementById('nac-toolbar-icon-default').hidden = true;
                    document.getElementById('nac-save-toolbar-icon').disabled = false;
                    document.getElementById('nac-toolbar-icon-status').textContent = 'Image selected. Click Save Toolbar Icon to apply.';
                });
                frame.on('open', () => {
                    const id = Number(document.getElementById('nac-toolbar-icon-id').value);
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
    }
})(jQuery);
