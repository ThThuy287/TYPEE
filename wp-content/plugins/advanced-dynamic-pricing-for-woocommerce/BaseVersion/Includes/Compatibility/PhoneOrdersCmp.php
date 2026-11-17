<?php

namespace ADP\BaseVersion\Includes\Compatibility;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\WC\WcNoFilterWorker;

defined('ABSPATH') or exit;

/**
 * Plugin Name: Phone Orders for WooCommerce
 * Author: AlgolPlus
 *
 * @see https://algolplus.com/plugins/downloads/phone-orders-woocommerce-pro/
 */
class PhoneOrdersCmp
{
    const CART_ITEM_COST_KEY = 'wpo_item_cost';
    const CART_ITEM_ID_KEY = 'wpo_key';
    const CART_ITEM_COST_UPDATED_MANUALLY_KEY = 'cost_updated_manually';
    const CART_ITEM_ALLOW_PO_DISCOUNT_KEY = 'allow_po_discount';
    const CART_ITEM_SKIP_KEY = 'wpo_skip_item';
    const CART_ITEM_COST_SIG_KEY = 'wpo_item_cost_sig';
    const SIGNING_KEY_OPTION = 'adp_wpo_price_signing_key';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var WcNoFilterWorker
     */
    protected $wcNoFilterWorker;

    /**
     * @param null $deprecated
     */
    public function __construct($deprecated = null)
    {
        $this->context          = adp_context();
        $this->wcNoFilterWorker = new WcNoFilterWorker();
    }

    public function withContext(Context $context)
    {
        $this->context = $context;
    }

    public function isActive()
    {
        return defined('WC_PHONE_ORDERS_VERSION') || class_exists('WC_Phone_Orders_Loader');
    }

    public static function getSigningKey()
    {
        $key = get_option(self::SIGNING_KEY_OPTION);
        if (!is_string($key) || strlen($key) < 32) {
            $key = wp_generate_password(64, true, true);
            if (!add_option(self::SIGNING_KEY_OPTION, $key, '', 'no')) {
                $key = get_option(self::SIGNING_KEY_OPTION);
            }
        }
        return $key;
    }

    public static function computeCostSignature($productId, $variationId, $cost)
    {
        $payload = (int)$productId . ':' . (int)$variationId . ':' . wc_format_decimal((string)$cost, wc_get_price_decimals());
        return hash_hmac('sha256', $payload, self::getSigningKey());
    }

    protected function hasValidCostSignature(WcCartItemFacade $facade)
    {
        $trdPartyData = $facade->getThirdPartyData();

        if (empty($trdPartyData[self::CART_ITEM_COST_SIG_KEY]) || !isset($trdPartyData[self::CART_ITEM_COST_KEY])) {
            return false;
        }

        $expected = self::computeCostSignature(
            $facade->getProductId(),
            $facade->getVariationId(),
            $trdPartyData[self::CART_ITEM_COST_KEY]
        );

        return hash_equals($expected, (string)$trdPartyData[self::CART_ITEM_COST_SIG_KEY]);
    }

    public function sanitizeWcCart($wcCart)
    {
        $needToUpdate = false;
        foreach ($wcCart->cart_contents as $cartKey => $wcCartItem) {
            $facade = new WcCartItemFacade($this->context, $wcCartItem, $cartKey);

            $trdPartyData = $facade->getThirdPartyData();

            $needToUpdate = isset(
                $trdPartyData[self::CART_ITEM_ID_KEY],
                $trdPartyData[self::CART_ITEM_COST_KEY],
                $trdPartyData[self::CART_ITEM_COST_UPDATED_MANUALLY_KEY],
                $trdPartyData[self::CART_ITEM_ALLOW_PO_DISCOUNT_KEY]
            );
        }

        if ( !$needToUpdate/* || $this->context->priceSettings->isIncludeTax()*/) {
            return;
        }

        $newCartContents = array();
        foreach ($wcCart->cart_contents as $cartKey => $wcCartItem) {
            $facade = new WcCartItemFacade($this->context, $wcCartItem, $cartKey);

            $facade->deleteThirdPartyData(self::CART_ITEM_ID_KEY);
            if ( ! $this->isCartItemCostUpdateManually($facade)) {
                $facade->deleteThirdPartyData(self::CART_ITEM_COST_KEY);
                $facade->deleteThirdPartyData(self::CART_ITEM_COST_UPDATED_MANUALLY_KEY);
                $facade->deleteThirdPartyData(self::CART_ITEM_ALLOW_PO_DISCOUNT_KEY);
            }

            $newCartKey = $facade::generateCartId(
                $facade->getProductId(),
                $facade->getVariationId(),
                $facade->getVariation(),
                $facade->getThirdPartyData()
            );

            if (isset($newCartContents[$newCartKey])) {
                $existingFacade = new WcCartItemFacade($this->context, $newCartContents[$newCartKey], $newCartKey);
                $existingFacade->setQty($existingFacade->getQty() + $facade->getQty());
                $newCartContents[$newCartKey] = $existingFacade->getData();
            } else {
                $facade->setKey($newCartKey);
                $newCartContents[$newCartKey] = $facade->getData();
            }
        }
        $wcCart->cart_contents = $newCartContents;
        $this->wcNoFilterWorker->calculateTotals($wcCart);
    }

    public function forceToSkipFreeCartItems($wcCart)
    {
        foreach ($wcCart->cart_contents as $cartKey => $wcCartItem) {
            $facade = new WcCartItemFacade($this->context, $wcCartItem, $cartKey);

            if ($facade->isFreeItem()) {
                $facade->setThirdPartyData(self::CART_ITEM_SKIP_KEY, true);
                $wcCart->cart_contents[$cartKey] = $facade->getData();
            }
        }
    }

    /**
     * @param WcCartItemFacade $facade
     *
     * @return true|false|null
     */
    public function isCartItemCostUpdateManually(WcCartItemFacade $facade)
    {
        if (!$this->isActive() || !$this->hasValidCostSignature($facade)) {
            return null;
        }

        $trdPartyData = $facade->getThirdPartyData();

        return isset($trdPartyData[self::CART_ITEM_COST_UPDATED_MANUALLY_KEY]) ? $trdPartyData[self::CART_ITEM_COST_UPDATED_MANUALLY_KEY] : null;
    }

    /**
     * @param WcCartItemFacade $facade
     *
     * @return float|null
     */
    public function getCartItemCustomPrice(WcCartItemFacade $facade)
    {
        if (!$this->isActive() || !$this->hasValidCostSignature($facade)) {
            return null;
        }

        $trdPartyData = $facade->getThirdPartyData();

        return isset($trdPartyData[self::CART_ITEM_COST_KEY]) ? $trdPartyData[self::CART_ITEM_COST_KEY] : null;
    }

    public function woocsModifyContext()
    {
        $woocsCmp = new WoocsCmp();
        if ($woocsCmp->isActive()) {
            $woocsCmp->modifyContext($this->context);
        }
    }

    public function allowCartItemWithPriceUpdatedManuallyToParticipateInCalculation()
    {
        return apply_filters(
            "adp_po_allow_cart_item_with_price_updated_manually_to_participate_in_calculations",
            false
        );
    }
}
