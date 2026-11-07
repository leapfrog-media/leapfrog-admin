(function ($) {
    'use strict';
    $(function () {
        const profileUser = document.getElementById('user_id');
        const viewer = document.querySelector('input[name="checkuser_id"]');
        if (!profileUser || !viewer || profileUser.value !== viewer.value) return;
        // WordPress changes the stylesheet and saves the selection itself.
        // Update our scope immediately, including on older WordPress versions
        // that do not update the body's colour-scheme classes during previews.
        $('#color-picker').on('click.nacScheme', '.color-option', function () {
            const choice = this.querySelector('input[name="admin_color"]');
            if (choice) document.body.classList.toggle('admin-color-native-admin-colours', choice.value === 'native-admin-colours');
        });
    });
})(jQuery);
