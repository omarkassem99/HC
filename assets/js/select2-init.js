jQuery(document).ready(function ($) {
    $('.user-select2').select2({
        placeholder: "Select Users",
        allowClear: true,
        width: 'auto',
        closeOnSelect: false,
        dropdownCssClass: 'scrollable-dropdown', // Apply scrolling to dropdown

        templateSelection: function (selection) {
                return selection.text 
            }
    });

    // Add "Select All" functionality
    $('.user-select2').each(function () {
        const $select = $(this);

        $select.on('select2:open', function () {
            if (!$('.select2-select-all').length) {
                $('.select2-results').prepend(
                    `<li class="select2-results__option select2-select-all" role="option" style="font-weight: bold; cursor: pointer;">
                        Select All
                    </li>`
                );

                // Handle "Select All" click
                $('.select2-select-all').on('click', function () {
                    const allOptions = $select.find('option').map(function () {
                        return $(this).val();
                    }).get();
                    $select.val(allOptions).trigger('change'); // Select all options
                    $select.select2('close'); // Close the dropdown
                });
            }
        });
    });
});
