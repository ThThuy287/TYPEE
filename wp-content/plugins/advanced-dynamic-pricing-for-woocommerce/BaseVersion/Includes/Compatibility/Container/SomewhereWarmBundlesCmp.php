<?php

namespace ADP\BaseVersion\Includes\Compatibility\Container;

use ADP\BaseVersion\Includes\CartProcessor\CartProcessor;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Core\Cart\CartItem\Type\Container\ContainerPriceTypeEnum;
use ADP\BaseVersion\Includes\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\Core\Cart\CartItem\Type\Container\ContainerCartItem;
use ADP\BaseVersion\Includes\Core\Cart\CartItem\Type\Container\ContainerPartCartItem;

defined('ABSPATH') or exit;

/**
 * Plugin Name: WooCommerce Product Bundles
 * Author: SomewhereWarm
 *
 * @see https://woocommerce.com/products/product-bundles/
 */
class SomewhereWarmBundlesCmp extends AbstractContainerCompatibility
{
    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    protected function getContext(): Context
    {
        return $this->context;
    }

    public function addFilters()
    {
        // type cast for "identical" comparison in "update_cart_action" method
        add_filter('woocommerce_stock_amount_cart_item', function ($qty, $cart_item_key) {
            return (float)$qty;
        }, 10, 2);

        if ($this->context->getCompatibilityOption("enable_wc_product_bundles_cmp", true)) {
            add_filter('adp_product_get_price', function ($price, $product, $variation, $qty, $trdPartyData, $facade) {
                if ($facade === null) {
                    return $price;
                }

                if ($this->isContainerFacade($facade)) {
                    if ($facade->getOriginalPrice() !== null) {
                        $price = $facade->getOriginalPrice();
                    } else {
                        $price = \WC_PB_Display::instance()->get_container_cart_item_price_amount(
                            $facade->getData(),
                            'price'
                        );
                    }
                } elseif ($this->isFacadeAPartOfContainer($facade)) {
                    $price = 0.0;
                }

                return $price;
            }, 10, 6);
        }

        add_filter('woocommerce_bundle_price_data', function($data, $product) {
            $discountPrice = adp_functions()->getDiscountedProductPrice($product, 1, true);
            if (!empty($discountPrice)) {
                if( is_array($discountPrice) ) {
                    $data['base_price'] = reset($discountPrice); //take min value
                } else {
                    $data['base_price'] = $discountPrice;
                }
            }

            return $data;
        }, 10, 2);
    }

    public function isActive(): bool
    {
        return class_exists("WC_Bundles") || class_exists("WC_Product_Bundle");
    }

    public function isFacadeAPartOfContainer(WcCartItemFacade $facade): bool
    {
        return function_exists('wc_pb_maybe_is_bundled_cart_item') && wc_pb_maybe_is_bundled_cart_item($facade->getData());
    }

    public function isContainerFacade(WcCartItemFacade $facade): bool
    {
        return function_exists('wc_pb_is_bundle_container_cart_item') && wc_pb_is_bundle_container_cart_item($facade->getData());
    }

    public function isContainerProduct(\WC_Product $wcProduct): bool
    {
        return $wcProduct instanceof \WC_Product_Bundle;
    }

    public function isFacadeAPartOfContainerFacade(WcCartItemFacade $partOfContainerFacade, WcCartItemFacade $bundle): bool
    {
        $thirdPartyData = $bundle->getThirdPartyData();

        return in_array($partOfContainerFacade->getKey(), $thirdPartyData['bundled_items'] ?? [], true);
    }

    public function getListOfPartsOfContainerFromContainerProduct(\WC_Product $product): array
    {
        if (!($product instanceof \WC_Product_Bundle)) {
            return [];
        }

        return array_map(
            function ($bundleItem) use ($product) {
                /** @var \WC_Bundled_Item $bundleItem */
                $bundledProduct = $bundleItem->get_product();

                $price = $bundleItem->get_price();

                return ContainerPartProduct::of(
                    $product,
                    $bundledProduct,
                    (float)$price,
                    (float)$bundleItem->get_quantity("min", array( 'check_optional' => true )),
                    $bundleItem->is_priced_individually()
                );
            },
            $product->get_bundled_items('edit')
        );
    }

    /**
     * @param WcCartItemFacade $facade
     * @return float
     */
    public function calculatePartOfContainerPrice(WcCartItemFacade $facade): float
    {
        $bundledProduct = $facade->getProduct();
        $this->probablySetBundledItem($bundledProduct, $facade);

        $bundledCartItem = $this->getBundledCartItem($bundledProduct);
        if ( $bundledCartItem !== null ) {
            $childItemPrice = floatval($bundledCartItem->get_price());
        } else {
            $childItemPrice = floatval($bundledProduct->get_price());
        }

        return $childItemPrice;
    }

    /**
     * @param WcCartItemFacade $facade
     * @param array<int, WcCartItemFacade> $children
     * @return float
     */
    public function calculateContainerPrice(WcCartItemFacade $facade, array $children): float
    {
        $bundleProduct = $facade->getProduct();
        $basePrice = floatval($bundleProduct->get_price());
        $childItemsPrice = 0.0;
        foreach ($children as $child) {
            $childProduct = $child->getProduct();
            $childItemQty = $childProduct->is_sold_individually() ? 1 : $child->getQty() / $facade->getQty();
            $childItemPrice = $this->calculatePartOfContainerPrice($child) * $childItemQty;
            $childItemsPrice += $childItemPrice;
        }

        return $basePrice + $childItemsPrice;
    }

    /**
     * @param WcCartItemFacade $facade
     * @param array<int, WcCartItemFacade> $children
     * @return float
     */
    public function calculateContainerBasePrice(WcCartItemFacade $facade, array $children): float
    {
        return floatval(CartProcessor::getProductPriceDependsOnPriceMode($facade->getProduct()));
    }

    public function getContainerPriceTypeByParentFacade(WcCartItemFacade $facade): ?ContainerPriceTypeEnum
    {
        $product = $facade->getProduct();

        if (!($product instanceof \WC_Product_Bundle)) {
            return null;
        }

        if ($product->contains('priced_individually')) {
            return ContainerPriceTypeEnum::BASE_PLUS_SUM_OF_SUB_ITEMS();
        } else {
            return ContainerPriceTypeEnum::FIXED();
        }
    }

    public function isPartOfContainerFacadePricedIndividually(WcCartItemFacade $facade): ?bool
    {
        if(apply_filters('adp_true_bundle_individ_price', false)){
            return true;
        }

        $product = $facade->getProduct();
        $this->probablySetBundledItem($product, $facade);

        $item = $this->getBundledCartItem($product);
        if (!($item instanceof \WC_Bundled_Item)) {
            return false;
        }

        return $item->is_priced_individually();
    }

    public function overrideContainerReferenceForPartOfContainerFacadeAfterPossibleDuplicates(
        WcCartItemFacade $partOfContainerFacade,
        WcCartItemFacade $containerFacade
    ) {
        $partOfContainerFacade->setThirdPartyData('bundled_by', $containerFacade->getKey());

        $parentFacadeThirdPartyData = $containerFacade->getThirdPartyData();
        $bundledItems = $parentFacadeThirdPartyData['bundled_items'] ?? null;
        if ( $bundledItems === null ) {
            return;
        }

        $i = array_search($partOfContainerFacade->getOriginalKey(), $bundledItems);
        if ( $i !== false ) {
            $bundledItems = array_replace(
                $bundledItems,
                [$i => $partOfContainerFacade->getKey()]
            );

            $containerFacade->setThirdPartyData('bundled_items', $bundledItems);
        }
    }

    public function probablySetBundledItem(&$product, $facade) {
        $data = $facade->getThirdPartyData();
        if($product AND !empty($data['bundled_item_id']) AND $child = wc_pb_get_bundled_item($data['bundled_item_id']) )
            $this->setBundledCartItem($product, $child);
    }

    /**
     * WC_Product doesn't declare a "bundled_cart_item" property, so writing it directly
     * as $product->bundled_cart_item = ... creates a dynamic property, which is deprecated
     * since PHP 8.2. Store it in the already-declared WC_Data::$changes array instead,
     * same approach used elsewhere in this plugin (e.g. ProductExtension::setCustomPrice).
     *
     * @param \WC_Product $product
     * @param \WC_Bundled_Item $bundledItem
     */
    private function setBundledCartItem(\WC_Product $product, \WC_Bundled_Item $bundledItem)
    {
        try {
            $reflection = new \ReflectionClass($product);
            $property = $reflection->getProperty('changes');
            if (\PHP_VERSION_ID < 80100) {
                $property->setAccessible(true);
            }
            $changes = $product->get_changes();
            $changes['bundled_cart_item'] = $bundledItem;
            $property->setValue($product, $changes);
        } catch (\ReflectionException $exception) {
            // no-op: fall back to no bundled item being available
        }
    }

    /**
     * @param \WC_Product $product
     * @return \WC_Bundled_Item|null
     */
    private function getBundledCartItem(\WC_Product $product)
    {
        $changes = $product->get_changes();

        return $changes['bundled_cart_item'] ?? null;
    }

    public function adaptContainerCartItem(
        WcCartItemFacade $facade,
        array $children,
        int $pos
    ): ContainerCartItem {
        $containerItem = parent::adaptContainerCartItem($facade, $children, $pos);

        return $containerItem->setItems(
            array_map(
                function ($subContainerItem) use ($facade) {
                    /** @var ContainerPartCartItem $subContainerItem */
                    return $this->modifyPartOfContainerItemQty($subContainerItem, $facade);
                },
                array_map([$this, 'adaptContainerPartCartItem'], $children)
            )
        );
    }

    /**
     * @param ContainerPartCartItem $subContainerItem
     * @param WcCartItemFacade $parentFacade
     * @return ContainerPartCartItem
     */
    protected function modifyPartOfContainerItemQty(
        ContainerPartCartItem $subContainerItem,
        WcCartItemFacade $parentFacade
    ): ContainerPartCartItem {
        if ($subContainerItem->isPricedIndividually()){
            $subContainerItem->setQty($subContainerItem->getQty() / $parentFacade->getQty());
        }

        return $subContainerItem;
    }
}
