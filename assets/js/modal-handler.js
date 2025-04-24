jQuery(document).ready(function($) {
    $('.open-modal').on('click', function(e) {
        e.preventDefault();

        const action = $(this).data('action');
        const modalId = $(this).data('modal');
        const modal = $('#' + modalId);
        const entity = $(this).data('entity');

        // Clear fields if action is "add"
        if (action === 'add') {
            modal.find('input:not([name="_wpnonce"]), textarea').val('');
        } else if (action === 'edit') {
            // Populate the fields with existing data
            $.each($(this).data(), function(key, value) {
                if (key.startsWith('field')) {
                    const fieldName = key.replace('field_', ''); // Extract actual field name
                    const fieldSelector = '#modal-' + entity + '-' + fieldName; // Construct the field ID
                    const fieldElement = modal.find(fieldSelector); // Get the field element in the modal

                    // Populate the field if it exists
                    fieldElement.val(value);
                }
            });
        }

        modal.show();
    });

    // Open the Delete Modal
    jQuery(document).ready(function($) {
        $('.delete-modal-trigger').on('click', function(e) {
            e.preventDefault();
    
            // Check if the event is firing
            console.log('Delete button clicked');
    
            // Get dynamic data from the delete button
            const entityId = $(this).data('id');
            const entityType = $(this).data('entity');
            const modalId = $(this).data('modal');
    
            // Debug: Check if entityId, entityType, and modalId are fetched correctly
            console.log('Entity ID:', entityId);
            console.log('Entity Type:', entityType);
            console.log('Modal ID:', modalId);
    
            // Check if the modal is found
            const modal = $('#' + modalId);
            if (modal.length === 0) {
                console.log('Modal not found: #' + modalId);
                return;
            }
    
            // Update the modal with dynamic data
            modal.find('#delete-entity-id').val(entityId);         // Set entity ID in hidden input
            modal.find('#delete-entity-type').val(entityType);     // Set entity type in hidden input
            modal.find('#delete-action-type').val('delete');       // Set action type to delete
    
            // Debug: Check if the values are being set
            console.log('Entity ID in modal:', modal.find('#delete-entity-id').val());
            console.log('Entity Type in modal:', modal.find('#delete-entity-type').val());
    
            // Show the delete confirmation modal
            modal.show();
            console.log('Modal shown');
        });
    });
    

    $('.bidfood-close').on('click', function() {
        $(this).closest('.bidfood-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('bidfood-modal')) {
            $(e.target).hide();
        }
    });
});
