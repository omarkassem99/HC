jQuery(document).ready(function ($) {

    const userCb = document.querySelectorAll('.select_order');

    $('#select_all').change(function () {
        if (this.checked) {
            userCb.forEach(cb => {
                cb.checked = true;
            });
        } else {
            userCb.forEach(cb => {
                cb.checked = false;
            });
        }
    })
});