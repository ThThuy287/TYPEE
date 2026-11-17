<?php
defined('ABSPATH') or exit;
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <?php esc_html_e('Enable AI assistant', 'advanced-dynamic-pricing-for-woocommerce') ?>
    </th>
    <td class="forminp forminp-checkbox">
        <fieldset>
            <label for="enable_ai_assistant">
                <input <?php checked($options['enable_ai_assistant']) ?>
                    name="enable_ai_assistant" id="enable_ai_assistant" type="checkbox">
                <?php esc_html_e('Show the "AI assistant" tab and its chat on the plugin pages',
                    'advanced-dynamic-pricing-for-woocommerce') ?>
            </label>
        </fieldset>
    </td>
</tr>
