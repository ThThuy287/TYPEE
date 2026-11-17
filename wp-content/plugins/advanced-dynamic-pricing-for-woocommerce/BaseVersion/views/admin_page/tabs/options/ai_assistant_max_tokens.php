<?php
defined('ABSPATH') or exit;

$aiModel   = \ADP\BaseVersion\Includes\Ai\ModelSettings::describe();
$aiChoices = array(
    'auto' => __('Auto (2500 if the model takes it)', 'advanced-dynamic-pricing-for-woocommerce'),
    'off'  => __('Do not send', 'advanced-dynamic-pricing-for-woocommerce'),
    '1000' => '1000',
    '2500' => '2500',
    '4000' => '4000',
    '8000' => '8000',
);
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="ai_assistant_max_tokens">
            <?php esc_html_e('AI assistant: max tokens', 'advanced-dynamic-pricing-for-woocommerce') ?>
        </label>
    </th>
    <td class="forminp">
        <select name="ai_assistant_max_tokens" id="ai_assistant_max_tokens">
            <?php foreach ($aiChoices as $value => $label) : ?>
                <option <?php selected((string)$options['ai_assistant_max_tokens'], (string)$value) ?> value="<?php echo esc_attr($value) ?>">
                    <?php echo esc_html($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('The longest answer the model may write. Too low a limit can leave the answer empty.', 'advanced-dynamic-pricing-for-woocommerce') ?>
            <?php if ($aiModel['model'] && $aiModel['no_max_tokens']) : ?>
                <br>
                <?php esc_html_e('The model in use does not take this limit, so none is sent.', 'advanced-dynamic-pricing-for-woocommerce') ?>
            <?php endif; ?>
        </p>
    </td>
</tr>
