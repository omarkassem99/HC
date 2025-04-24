<?php

namespace Chef\UI\Toast;

class ToastHelper {

    /**
     * Add a toast notice to the session
     *
     * @param string $message
     * @param string $type    Available types: 'success', 'error', 'info'.
     * @param int    $time    Optional display time in milliseconds. Default is 4000. Set to 0 to disable auto-dismiss.
     */
    public static function add_toast_notice($message, $type = 'success', $time = 4000) {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['toast_notices'])) {
            $_SESSION['toast_notices'] = [];
        }

        $_SESSION['toast_notices'][] = [
            'message' => $message,
            'type' => $type,
            'time' => $time
        ];
    }

    /**
     * Output the toast notices stored in the session
     */
    public static function output_toast_notices() {
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
