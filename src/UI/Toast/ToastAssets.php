<?php

namespace Chef\UI\Toast;

class ToastAssets {

    /**
     * Enqueue the styles and scripts for the toast notifications
     */
    public static function enqueue_global_toast_assets() {
        // Enqueue the CSS file
        wp_enqueue_style(
            'toast-css',
            plugins_url('assets/toast.css', __FILE__),
            [],
            '1.0'
        );

        // Enqueue the JS file
        wp_enqueue_script(
            'toast-js',
            plugins_url('assets/toast.js', __FILE__),
            [],
            '1.0',
            true // Load in footer
        );

        // Inline JS for outputting the stored toasts from the session
        if (!session_id()) {
            session_start();
        }

        if (!empty($_SESSION['toast_notices'])) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const toasts = <?php echo json_encode($_SESSION['toast_notices']); ?>;
                    toasts.forEach(toast => {
                        showToast(toast.message, toast.type, toast.time);
                    });
                });
            </script>
            <?php
            unset($_SESSION['toast_notices']);
        }
    }
}
