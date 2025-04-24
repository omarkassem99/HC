<?php

namespace Chef\UI\Modal;

class ModalHelper
{

    /**
     * Renders a reusable modal component with dynamic fields
     * @param string $modal_id - Unique ID for the modal.
     * @param string $entity - Entity type (e.g., operator, venue).
     * @param array $fields - Array of fields for the form (each field is an associative array).
     * @param string $action_type - Action to be performed (add/edit).
     */
    public static function render_modal($modal_id, $entity, $fields, $action_type = 'add', $wpnonce = '_wpnonce')
    {
?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="bidfood-modal" style="display:none;">
            <div class="bidfood-modal-content">
                <span class="bidfood-close">&times;</span>
                <h2><?php echo ucfirst($action_type) . ' ' . ucfirst($entity); ?></h2>

                <form method="post" id="<?php echo esc_attr($modal_id); ?>-form">

                    <!-- Nonce Field -->
                    <?php wp_nonce_field($entity . '_action', $wpnonce, true, true); ?>

                    <table class="form-table">
                        <tbody>
                            <?php foreach ($fields as $field): ?>
                                <?php
                                // Hidden field should not be visible
                                $style = isset($field['hidden']) && $field['hidden'] ? 'style="display:none;"' : '';
                                ?>
                                <tr <?php echo $style; ?> id="tr-<?php echo esc_attr($entity . '-' . $field['name']); ?>">
                                    <th><label for="modal-<?php echo esc_attr($entity . '-' . $field['name']); ?>"><?php echo esc_html(isset($field['label']) ? $field['label'] : ''); ?></label></th>
                                    <td>
                                        <?php if ($field['type'] === 'textarea'): ?>
                                            <textarea name="<?php echo esc_attr($field['name']); ?>" id="modal-<?php echo esc_attr($entity . '-' . $field['name']); ?>" rows="5" class="large-text" <?php echo !empty($field['required']) ? 'required' : ''; ?> <?php echo isset($field['readonly']) && $field['readonly'] ? 'readonly' : ''; ?>></textarea>
                                        <?php elseif ($field['type'] === 'select'): ?>
                                            <select name="<?php echo esc_attr($field['name']); ?>" id="modal-<?php echo esc_attr($entity . '-' . $field['name']); ?>" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                                <?php foreach ($field['options'] as $option_value => $option_label): ?>
                                                    <option value="<?php echo esc_attr($option_value); ?>" <?php echo (isset($field['value']) && $field['value'] === $option_value) ? 'selected' : ''; ?>><?php echo esc_html($option_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="<?php echo esc_attr($field['type']); ?>" name="<?php echo esc_attr($field['name']); ?>" <?php
                                                if (isset($field['value']) && $field['value']) {
                                                    echo 'value="' . esc_attr($field['value']) . '"';
                                                }
                                            ?> id="modal-<?php echo esc_attr($entity . '-' . $field['name']); ?>" class="regular-text" <?php echo !empty($field['required']) ? 'required' : ''; ?> <?php echo isset($field['readonly']) && $field['readonly'] ? 'readonly' : ''; ?>>
                                        <?php endif; ?>
                                        <?php if (!empty($field['help_text'])):?>
                                            <p class="description"><?php echo $field['help_text'] ?></p>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" name="<?php echo esc_attr($action_type); ?>_<?php echo esc_attr($entity); ?>" class="button button-primary">
                            <?php echo ucfirst($action_type) . ' ' . ucfirst($entity); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    <?php
    }

    /**
     * Renders confirmation modal for delete action
     */
    public static function render_delete_confirmation_modal($modal_id, $entity)
    {
    ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="bidfood-modal" style="display:none;">
            <div class="bidfood-modal-content">
                <span class="bidfood-close">&times;</span>
                <h2><?php _e('Confirm Delete', 'bidfood'); ?></h2>
                <p><?php _e('Are you sure you want to delete this record?', 'bidfood'); ?></p>
                <form method="post" id="<?php echo esc_attr($modal_id); ?>-form">
                    <input type="hidden" name="entity_id" id="delete-entity-id" value="">
                    <input type="hidden" name="action_type" id="delete-action-type" value="delete">
                    <input type="hidden" name="entity_type" id="delete-entity-type" value="<?php echo esc_attr($entity); ?>">

                    <!-- Nonce Field -->
                    <?php wp_nonce_field('delete_action', '_wpnonce_delete'); ?>

                    <p class="submit">
                        <button type="submit" id="delete-submit-btn" class="button button-primary"><?php _e('Delete', 'bidfood'); ?></button>
                        <button type="button" class="button bidfood-close"><?php _e('Cancel', 'bidfood'); ?></button>
                    </p>
                </form>
            </div>
        </div>
<?php
    }
}
