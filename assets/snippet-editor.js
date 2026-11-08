(function ($) {
    'use strict';
    // The snippet edit screen: WordPress's code editor (CodeMirror), set to the snippet's type.
    const textarea = document.getElementById('lfa-snippet-code');
    const type = document.getElementById('lfa-snippet-type');
    const position = document.querySelector('.lfa-snippet-position');
    const help = document.getElementById('lfa-code-help');
    if (!textarea || !type) return;
    const modes = { php: 'application/x-httpd-php-open', css: 'text/css', js: 'text/javascript', html: 'text/html' };
    let editor = null;
    if (window.lfaSnippetEditor && window.wp && wp.codeEditor) {
        const settings = $.extend(true, {}, window.lfaSnippetEditor);
        settings.codemirror = $.extend({}, settings.codemirror, { mode: modes[type.value], lint: false, viewportMargin: Infinity });
        editor = wp.codeEditor.initialize(textarea, settings).codemirror;
    }
    function update() {
        if (editor) editor.setOption('mode', modes[type.value]);
        // Where in the page only matters for CSS, JavaScript and HTML.
        if (position) position.hidden = type.value === 'php';
        if (help) help.textContent = help.dataset[type.value] || '';
    }
    type.addEventListener('change', update);
    update();
    // Ctrl+S (or Cmd+S) saves the snippet.
    document.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (editor) editor.save();
            document.getElementById('publish').click();
        }
    });
})(jQuery);
