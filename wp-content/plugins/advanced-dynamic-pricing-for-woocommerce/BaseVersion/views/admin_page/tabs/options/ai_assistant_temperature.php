<?php
defined('ABSPATH') or exit;

$aiModel   = \ADP\BaseVersion\Includes\Ai\ModelSettings::describe();
$aiChoices = array(
    'auto' => __('Auto (0.2 if the model takes it)', 'advanced-dynamic-pricing-for-woocommerce'),
    'off'  => __('Do not send', 'advanced-dynamic-pricing-for-woocommerce'),
    '0'    => '0',
    '0.2'  => '0.2',
    '0.5'  => '0.5',
    '0.8'  => '0.8',
    '1'    => '1',
);
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="ai_assistant_temperature">
            <?php esc_html_e('AI assistant: temperature', 'advanced-dynamic-pricing-for-woocommerce') ?>
        </label>
    </th>
    <td class="forminp">
        <select name="ai_assistant_temperature" id="ai_assistant_temperature">
            <?php foreach ($aiChoices as $value => $label) : ?>
                <option <?php selected((string)$options['ai_assistant_temperature'], (string)$value) ?> value="<?php echo esc_attr($value) ?>">
                    <?php echo esc_html($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Lower values make the drafts more predictable.', 'advanced-dynamic-pricing-for-woocommerce') ?>
            <?php if ($aiModel['model']) : ?>
                <br>
                <?php
                /* translators: %s: AI provider and model, e.g. "openai/gpt-5" */
                echo esc_html(sprintf(__('Model in use: %s.', 'advanced-dynamic-pricing-for-woocommerce'), $aiModel['model']));
                if ($aiModel['no_temperature']) {
                    echo ' ' . esc_html__('It does not take a temperature, so none is sent.', 'advanced-dynamic-pricing-for-woocommerce');
                }
                ?>
            <?php endif; ?>
        </p>
    </td>
</tr>
