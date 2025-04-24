jQuery(document).ready(function ($) {
    // Disable upload button until files are selected
    $('#product-images-input').on('change', function () {
        $('#upload-product-images-btn').prop('disabled', this.files.length === 0);

        if (this.files.length > 0) {
            $('#progress-bar').css('width', '0%');
            $('#progress-text').text(`0 / ${this.files.length}`);
            $('#progress-container').show(); // Show progress bar
        } else {
            $('#progress-container').hide(); // Hide if no files are selected
        }
    });

    // Hide progress container initially
    $('#progress-container').hide();

    $('#upload-form').on('submit', function (event) {
        event.preventDefault();
        $('#upload-product-images-btn').prop('disabled', true); // Disable button after submission
        $('#error-messages-list').empty(); // Clear old error messages
        $('#error-messages-container').hide(); // Hide errors initially
        $('#progress-container').show(); // Show progress bar on submit

        var files = $('#product-images-input')[0].files;
        var totalFiles = files.length;
        var uploadedFiles = 0;
        var successCount = 0;
        var failedCount = 0;

        $('#progress-bar').css('width', '0%');
        $('#progress-text').text(`0 / ${totalFiles}`);

        // Upload files one by one
        $.each(files, function (index, file) {
            var formData = new FormData();
            formData.append('action', 'handle_product_images_upload');
            formData.append('security', ProductsImagesData.nonce);
            formData.append('file', file);

            $.ajax({
                url: ProductsImagesData.ajax_url,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    uploadedFiles++;
                    var progress = Math.round((uploadedFiles / totalFiles) * 100);
                    $('#progress-bar').css('width', progress + '%');
                    $('#progress-text').text(`${uploadedFiles} / ${totalFiles}`);

                    if (response.success) {
                        // Empty input field
                        $('#product-images-input').val('');
                        successCount++; // Increment success count
                        showToast(response.data.message, 'success', 4000); // Display success message


                        // Hide progress bar after success message
                        if (uploadedFiles === totalFiles) {
                            setTimeout(function () {
                                localStorage.removeItem('uploadedFiles');
                                $('#progress-container').hide();
                            }, 5000);
                        }
                    } else {
                        // Empty input field
                        $('#product-images-input').val('');
                        appendErrorMessage(response.data.message); // Append error
                        failedCount++;

                        showToast(response.data.message, 'error', 1000); // Display error message


                        if (uploadedFiles === totalFiles) {
                            displayFinalMessage(successCount, failedCount);
                            setTimeout(function () {
                                localStorage.removeItem('uploadedFiles');
                                $('#progress-container').hide();
                            }, 5000);
                        }
                    }
                },
                error: function (jqXHR, textStatus) {
                    uploadedFiles++;
                    var progress = Math.round((uploadedFiles / totalFiles) * 100);
                    $('#progress-bar').css('width', progress + '%');
                    $('#progress-text').text(`${uploadedFiles} / ${totalFiles}`);
                    showToast(textStatus, 'error', 5000); // Display error message
                    if (uploadedFiles === totalFiles) {
                        displayFinalMessage(successCount, failedCount);
                    }
                }
            });
        });
    });
    function appendErrorMessage(message) {
        $('#error-messages-container').show(); // Show error container
        $('#error-messages-list').append(`<li style="padding: 5px 0; border-bottom: 1px solid #f5c6cb;">⚠️ ${message}</li>`);
    };
    function displayFinalMessage(successCount, failedCount) {
        setTimeout(function () {
            showToast(`Upload Completed: ${successCount} Successful, ${failedCount} Failed`, 'info', 5000);
            $('#progress-container').hide();
            $('#product-images-input').val('');
        }, 5000);
    }
    // Close button event
    $('#close-error-messages').on('click', function () {
        $('#error-messages-container').hide();
    });
});
