<?php

namespace Bidfood\Admin;

class SettingsPage {

    public static function render() {
        ?>
        <div class="wrap">
            <h1><?php _e('Bidfood Settings', 'bidfood'); ?></h1>
            <form method="post" action="">
                <p><?php _e('Click the button below:', 'bidfood'); ?></p>
                <button type="button" class="button-primary"><?php _e('Dummy Button', 'bidfood'); ?></button>
            </form>
        </div>
        <?php
    }
}
