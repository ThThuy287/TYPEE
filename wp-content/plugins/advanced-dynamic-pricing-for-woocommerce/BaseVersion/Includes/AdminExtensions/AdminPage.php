<?php

namespace ADP\BaseVersion\Includes\AdminExtensions;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Ai\RuleAssistant;
use ADP\BaseVersion\Includes\AdminExtensions\AdminPage\AdminTabInterface;
use ADP\BaseVersion\Includes\AdminExtensions\AdminPage\Tabs\Options;

defined('ABSPATH') or exit;

class AdminPage
{
    const SLUG = 'wdp_settings';
    const TAB_REQUEST_KEY = 'tab';
    const CAPABILITY = 'manage_woocommerce';

    /**
     * @var AdminTabInterface[]
     */
    protected $tabs;

    /**
     * @var AdminTabInterface
     */
    protected $currentTab;

    /**
     * @var Context
     */
    protected $context;

    public function __construct($deprecated = null)
    {
        $this->context = adp_context();
    }

    public function withContext(Context $context)
    {
        $this->context = $context;
    }

    public function register()
    {
        $this->prepareTabs();
        $this->sortTabsByPriority();
        $this->detectCurrentTab();

        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
        add_filter('script_loader_src', array($this, 'doNotLoadExternalSelect2'), PHP_INT_MAX, 2);
        add_filter('script_loader_src', array($this, 'doNotLoadExternalPostbox'), PHP_INT_MAX, 2);
        add_filter('admin_footer_text', array( $this, 'adminFooterText' ), 1 );

        add_action('admin_enqueue_scripts', function () {
            if (adp_context()->isPluginAdminPage()) {
                wp_deregister_script('wc-enhanced-select');
                wp_deregister_script('selectWoo');
            }
        }, 100, 0);
    }

    public function registerAjax()
    {
        $this->prepareTabs();
        foreach ($this->tabs as $tab) {
            $tab->registerAjax();
        }
    }

    public function registerPage()
    {
        add_action('admin_menu', function () {
            add_submenu_page(
                'woocommerce',
                __('Pricing Rules', 'advanced-dynamic-pricing-for-woocommerce'),
                __('Pricing Rules', 'advanced-dynamic-pricing-for-woocommerce'),
                self::CAPABILITY,
                self::SLUG,
                array($this, 'showAdminPage'));
        });
    }

    public function showAdminPage()
    {
        $this->currentTab->handleSubmitAction();

        $tabs        = $this->tabs;
        $current_tab = $this->currentTab;
        $options     = $this->context->getSettings();
        include WC_ADP_PLUGIN_VIEWS_PATH . 'admin_page/admin_page.php';
    }

    public function renderCurrentTab()
    {
        $viewVariables = $this->currentTab->getViewVariables();
        if (is_array($viewVariables)) {
            extract($viewVariables);
        }

        $tabHandler = $this->currentTab;
        include WC_ADP_PLUGIN_VIEWS_PATH . $tabHandler::getRelativeViewPath();
    }

    public function enqueueScripts()
    {
        if ($this->context->is($this->context::ADP_PLUGIN_PAGE)) {
            wp_dequeue_style('wwp-style');
        }

        $currentTab    = $this->currentTab;
        $baseVersionUrl = WC_ADP_PLUGIN_URL . "/BaseVersion/";

        // Enqueue script for handling the meta boxes
        wp_enqueue_script('wdp_postbox', $baseVersionUrl . 'assets/js/postbox.js',
            array('jquery', 'jquery-ui-sortable'), WC_ADP_VERSION, true);

        wp_enqueue_script('wdp_admin-notice', $baseVersionUrl . 'assets/js/admin-notice.js',
            array('jquery'), WC_ADP_VERSION, true);

        // jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');

        // jQuery UI Datepicker styles
        wp_enqueue_style('wdp_jquery-ui', $baseVersionUrl . 'assets/jquery-ui/jquery-ui.min.css', array(), '1.11.4');

        // Enqueue Select2 related scripts and styles
        wp_enqueue_script('wdp_select2', $baseVersionUrl . 'assets/js/select2/select2.full.min.js', array('jquery'),
            '4.0.3', true);
        wp_enqueue_style('wdp_select2', $baseVersionUrl . 'assets/css/select2/select2.css', array(), '4.0.3');

        if ($currentTab::getKey() !== Options::getKey()) {
            // Enqueue jquery mobile related scripts and styles (for flip switch)
            // styles below are replacing option sections styles
            wp_enqueue_script('jquery-mobile-scripts',
                $baseVersionUrl . 'assets/jquery.mobile/jquery.mobile.custom.min.js', array('jquery'), WC_ADP_VERSION, true);
            wp_enqueue_style('jquery-mobile-styles',
                $baseVersionUrl . 'assets/jquery.mobile/jquery.mobile.custom.structure.min.css', array(), WC_ADP_VERSION);
            wp_enqueue_style('jquery-mobile-theme-styles',
                $baseVersionUrl . 'assets/jquery.mobile/jquery.mobile.custom.theme.css', array(), WC_ADP_VERSION);
        }

        // Backend styles
        wp_enqueue_style('wdp_settings-styles', $baseVersionUrl . 'assets/css/settings.css', array(), WC_ADP_VERSION);

        // DateTime Picker
        wp_enqueue_script('wdp_datetimepicker-scripts',
            $baseVersionUrl . 'assets/datetimepicker/jquery.datetimepicker.full.min.js', array('jquery'), WC_ADP_VERSION, true);
        wp_enqueue_style('wdp_datetimepicker-styles',
            $baseVersionUrl . 'assets/datetimepicker/jquery.datetimepicker.min.css', array(), WC_ADP_VERSION);

        wp_enqueue_script('wdp_cache_recalculation', $baseVersionUrl . 'assets/js/cache-recalculation.js', array('jquery'), WC_ADP_VERSION, true);


        $wdp_data = array(
            'security'           => wp_create_nonce(Ajax::SECURITY_ACTION),
            'security_query_arg' => Ajax::SECURITY_QUERY_ARG,
        );
        wp_localize_script('wdp_cache_recalculation', 'wdp_cache_recalculation_data', $wdp_data);

        wp_enqueue_script('wdp_admin-footer-text-rated', $baseVersionUrl . 'assets/js/admin-footer-text-rated.js', array('jquery'), WC_ADP_VERSION, true);
        wp_localize_script('wdp_admin-footer-text-rated', 'wdp_admin_footer_text_rated_data', $wdp_data);

        $this->currentTab->enqueueScripts();

        $this->enqueueAiChat($currentTab::getKey());
    }

    /**
     * The AI assistant chat is available on every tab. Enqueued after the tab's own scripts so
     * that on the Rules tab it loads after rules.js (it calls window.wdpAddRule to insert
     * drafted rules). The tab key goes along with every message - the backend answers with the
     * rule-drafting instructions on the Rules tab and the settings reference everywhere else.
     */
    protected function enqueueAiChat($tabKey)
    {
        // Settings -> AI Assistant -> "Enable AI assistant": off means no tab, no chat, no scripts
        if ( ! adp_context()->getOption('enable_ai_assistant', true)) {
            return;
        }

        $baseVersionUrl = WC_ADP_PLUGIN_URL . "/BaseVersion/";
        $isRulesTab     = $tabKey === RuleAssistant::TAB_RULES;

        wp_enqueue_style('wdp_ai-chat-styles', $baseVersionUrl . 'assets/css/ai-chat.css', array(), WC_ADP_VERSION);
        wp_enqueue_script('wdp_ai-chat', $baseVersionUrl . 'assets/js/ai-chat.js', array('jquery'), WC_ADP_VERSION, true);

        wp_localize_script('wdp_ai-chat', 'wdp_ai_chat_data', array(
            'security'           => wp_create_nonce(Ajax::SECURITY_ACTION),
            'security_query_arg' => Ajax::SECURITY_QUERY_ARG,
            'tab'                => $tabKey,
            'rules_url'          => admin_url('admin.php?page=' . self::SLUG . '&tab=' . RuleAssistant::TAB_RULES . '&wdp_open_ai_chat=1'),
            'status'             => RuleAssistant::checkAvailability(),
            'connectors_url'     => admin_url('options-connectors.php'),
            'i18n'               => array(
                'title'                => __('AI assistant', 'advanced-dynamic-pricing-for-woocommerce'),
                'placeholder'          => $isRulesTab
                    ? __('Describe the discount you want', 'advanced-dynamic-pricing-for-woocommerce')
                    : __('Ask about the plugin settings', 'advanced-dynamic-pricing-for-woocommerce'),
                'send'                 => __('Send', 'advanced-dynamic-pricing-for-woocommerce'),
                'loading'              => __('Loading…', 'advanced-dynamic-pricing-for-woocommerce'),
                'empty'                => $isRulesTab
                    ? __('No messages yet. Ask something about your pricing rules.', 'advanced-dynamic-pricing-for-woocommerce')
                    : __('No messages yet. Ask where a setting is or what it does.', 'advanced-dynamic-pricing-for-woocommerce'),
                'typing'               => __('Thinking…', 'advanced-dynamic-pricing-for-woocommerce'),
                'error'                => __('Something went wrong. Please try again.', 'advanced-dynamic-pricing-for-woocommerce'),
                'maximize'             => __('Expand', 'advanced-dynamic-pricing-for-woocommerce'),
                'restore'              => __('Collapse', 'advanced-dynamic-pricing-for-woocommerce'),
                'minimize'             => __('Minimize', 'advanced-dynamic-pricing-for-woocommerce'),
                'new_rule'             => __('New rule', 'advanced-dynamic-pricing-for-woocommerce'),
                'remove_context'       => __('Remove this rule from the conversation', 'advanced-dynamic-pricing-for-woocommerce'),
                'wp_version_required'  => __('AI assistant requires Wordpress 7.0 or later', 'advanced-dynamic-pricing-for-woocommerce'),
                'not_configured'       => sprintf(
                    /* translators: 1: URL of the Settings -> Connectors page, 2: URL of the MCP server plugins search on wordpress.org */
                    __("No AI provider is configured yet. Connect one under <a href=\"%1\$s\">Settings → Connectors</a>.\n\nYou can configure MCP access too. Please install one of <a href=\"%2\$s\">these plugins</a>.", 'advanced-dynamic-pricing-for-woocommerce'),
                    admin_url('options-connectors.php'),
                    'https://wordpress.org/plugins/search/mcp+server/'
                ),
                'open_rules_tab'       => __('Open the Rules tab', 'advanced-dynamic-pricing-for-woocommerce'),
            ),
        ));
    }

    protected function detectCurrentTab()
    {
        $currentTabKey = null;
        //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST[self::TAB_REQUEST_KEY])) {
            //phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
            $currentTabKey = $_REQUEST[self::TAB_REQUEST_KEY];
        }

        if ( ! isset($this->tabs[$currentTabKey])) {
            $currentTabKey = key($this->tabs);
        }

        $this->currentTab = $this->tabs[$currentTabKey];
    }

    protected function prepareTabs()
    {
        $tabsNamespace = __NAMESPACE__ . "\AdminPage\Tabs\\";
        foreach (glob(dirname(__FILE__) . "/AdminPage/Tabs/*") as $filename) {
            $tab       = str_replace(".php", "", basename($filename));
            $classname = $tabsNamespace . $tab;

            if (class_exists($classname)) {
                $tabHandler = new $classname($this->context);
                /**
                 * @var $tabHandler AdminTabInterface
                 */

                $this->tabs[$tabHandler::getKey()] = $tabHandler;
            }
        }
    }

    protected function sortTabsByPriority()
    {
        uasort($this->tabs, function ($tab1, $tab2) {
            /**
             * @var $tab1 AdminTabInterface
             * @var $tab2 AdminTabInterface
             */

            if ($tab1::getHeaderDisplayPriority() <= $tab2::getHeaderDisplayPriority()) {
                return -1;
            } else {
                return 1;
            }
        });
    }

    public function doNotLoadExternalSelect2($src, $handle)
    {
        // don't load ANY select2.js / select2.min.js  and OUTDATED select2.full.js
        if ( ! preg_match('/\/select2\.full\.js\?ver=[1-3]/', $src) && ! preg_match('/\/select2\.min\.js/',
                $src) && ! preg_match('/\/select2\.js/', $src) && ! strpos($src, 'woo-advanced-discounts/admin/js/wad-select2.js')) {
            return $src;
        }

        return "";
    }

    public function doNotLoadExternalPostbox($src, $handle)
    {

        if ( ! preg_match('/postbox\.?(min|full)?\.js(\?ver=[0-9.]+)?/', $src) || $handle === "wdp_postbox") {
            return $src;
        }

        return "";
    }

    public function adminFooterText( $footer_text )
    {
        if ( ! adp_context()->isPluginAdminPage()) {
            return $footer_text;
        }

        if ( ! $this->context->getOption('admin_footer_text_rated') ) {
            $footer_text = sprintf(
                /* translators: Wdp rating link*/
                __( 'If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'advanced-dynamic-pricing-for-woocommerce' ),
                sprintf( '<strong>%s</strong>', 'Advanced Dynamic Pricing for WooCommerce' ),
                '<a href="https://wordpress.org/support/plugin/advanced-dynamic-pricing-for-woocommerce/reviews?rate=5#new-post" target="_blank" class="wdp-rating-link" aria-label="' . esc_attr__( 'five star', 'advanced-dynamic-pricing-for-woocommerce' ) . '" data-rated="' . esc_attr__( 'Thanks!', 'advanced-dynamic-pricing-for-woocommerce' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
            );
        } else {
            $footer_text = __( 'Thank you for selling with Advanced Dynamic Pricing for WooCommerce.', 'advanced-dynamic-pricing-for-woocommerce' );
        }

        return $footer_text;
    }
}
