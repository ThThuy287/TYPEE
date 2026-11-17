<?php

namespace ADP\BaseVersion\Includes\Ai;

use ADP\BaseVersion\Includes\Helpers\Helpers;
use WC_Data_Store;

defined('ABSPATH') or exit;

/**
 * Turns the names a rule request mentions (a product, category, tag, SKU, taxonomy term,
 * attribute term, role, Product Collection, payment/shipping method...) into what the rule
 * actually stores for them.
 */
class TargetResolver
{
    /**
     * @return string[] titles of the Product Collections (a Pro feature; empty without it)
     */
    public static function productCollectionNames()
    {
        if ( ! ProFeatureDetector::isProActive() || ! class_exists('\ADP\ProVersion\Includes\Database\Repository\CollectionRepository')) {
            return array();
        }

        $names = array();
        foreach (\ADP\ProVersion\Includes\Database\Repository\CollectionRepository::searchProductCollections('', 100) as $collection) {
            $names[] = (string)$collection->title;
        }

        return $names;
    }

    // -----------------------------------------------------------------
    // Filter target resolution - shared by every action that targets a set of
    // products (product discount, bulk, role discount, role+bulk, gift trigger,
    // BOGO buy condition). Covers every filter `type` that works end-to-end
    // through the Free/Base admin UI (confirmed against ProductFiltering.php,
    // the actual filter-row evaluator): product, category, category by slug,
    // tag, SKU, any custom product taxonomy (e.g. Brand), and global attributes
    // (e.g. Color/Size). Deliberately excludes `product_sellers` (present in the
    // data model but has no working picker UI even in Free - Pro supplies the
    // missing template), and `product_custom_attributes`/`product_custom_fields`
    // (free-form site-specific key/value formats an LLM can't reliably target).
    // -----------------------------------------------------------------

    /**
     * @param string $filterType one of: any, product, category, category_slug, tag,
     *                           sku, taxonomy, attribute
     * @param string|string[] $value the name/slug/SKU the user mentioned, or several of them
     *                           (ignored for `any`)
     * @param array  $extra      the full decoded AI reply - reads `taxonomy_name`
     *                           (for `taxonomy`) / `attribute_name` (for `attribute`)
     *
     * @return array{found: bool, row?: array, label: string}|null null = invalid
     *         input (unknown filter_type, or a value/extra field the type needs is
     *         missing) - the caller should abort and draft nothing. Otherwise
     *         found=true includes the ready-to-use filterRow() and a human label;
     *         found=false means the type was understood but nothing matched, so
     *         the caller can still draft everything else and leave this picker empty.
     */
    public static function resolveFilterTarget($filterType, $value, $extra = array())
    {
        // several targets of the same kind ("the brands A, B and C") make ONE filter row whose
        // value lists them all - the engine matches a product that fits any of them
        if (is_array($value)) {
            $value = array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string)$item) : '';
            }, $value), 'strlen'));
            if (count($value) > 1 && $filterType !== 'any') {
                $row    = null;
                $labels = array();
                foreach ($value as $item) {
                    $target = self::resolveOneFilterTarget($filterType, $item, $extra);
                    if ( ! $target || ! $target['found']) {
                        // the caller reports the first one that is wrong; the rest are fine or not looked at yet
                        return $target;
                    }
                    $labels[] = html_entity_decode($target['label'], ENT_QUOTES);
                    if ($row === null) {
                        $row = $target['row'];
                    } else {
                        $row['value'] = array_values(array_unique(array_merge($row['value'], $target['row']['value'])));
                    }
                }

                return array('found' => true, 'row' => $row, 'label' => implode(', ', $labels));
            }
            $value = $value ? $value[0] : '';
        }

        $target = self::resolveOneFilterTarget($filterType, $value, $extra);
        if ($target && isset($target['label'])) {
            // term names come back HTML-escaped ("Oli &amp; Carol"); a label is plain text
            $target['label'] = html_entity_decode($target['label'], ENT_QUOTES);
        }

        return $target;
    }

    private static function resolveOneFilterTarget($filterType, $value, $extra = array())
    {
        if ($filterType === 'any') {
            // store-wide / "any product" - the user didn't name a specific target.
            // No value needed; ProductFiltering::checkProductSuitability() short-circuits
            // to match every product for this type.
            return array(
                'found' => true,
                'row'   => self::filterRow('any', array()),
                'label' => __('any product', 'advanced-dynamic-pricing-for-woocommerce'),
            );
        }

        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        switch ($filterType) {
            case 'product':
                $product = self::findProduct($value);

                return $product
                    ? array('found' => true, 'row' => self::filterRow('products', $product->get_id()), 'label' => $product->get_name())
                    : array('found' => false, 'label' => $value);

            case 'category':
                $term = self::findCategory($value);

                return $term
                    ? array('found' => true, 'row' => self::filterRow('product_categories', $term->term_id), 'label' => $term->name)
                    : array('found' => false, 'label' => $value);

            case 'category_slug':
                $term = self::findCategory($value);

                return $term
                    ? array('found' => true, 'row' => self::filterRow('product_category_slug', $term->slug), 'label' => $term->name)
                    : array('found' => false, 'label' => $value);

            case 'tag':
                $term = self::findTermInTaxonomy('product_tag', $value);

                return $term
                    ? array('found' => true, 'row' => self::filterRow('product_tags', $term->term_id), 'label' => $term->name)
                    : array('found' => false, 'label' => $value);

            case 'sku':
                $productId = wc_get_product_id_by_sku($value);

                return $productId
                    ? array('found' => true, 'row' => self::filterRow('product_sku', $value), 'label' => 'SKU: ' . $value)
                    : array('found' => false, 'label' => 'SKU: ' . $value);

            case 'taxonomy':
                $taxonomy = self::findCustomTaxonomy($extra['taxonomy_name'] ?? '');
                if ( ! $taxonomy) {
                    return null;
                }
                $term = self::findTermInTaxonomy($taxonomy, $value);

                return $term
                    ? array('found' => true, 'row' => self::filterRow($taxonomy, $term->term_id), 'label' => $term->name)
                    : array('found' => false, 'label' => $value);

            case 'attribute':
                $attributeLabel = $extra['attribute_name'] ?? '';
                $attrTaxonomy   = self::findAttributeTaxonomy($attributeLabel);
                if ( ! $attrTaxonomy) {
                    return null;
                }
                $term = self::findTermInTaxonomy($attrTaxonomy, $value);

                return $term
                    ? array('found' => true, 'row' => self::filterRow($attrTaxonomy, $term->term_id), 'label' => $attributeLabel . ': ' . $term->name)
                    : array('found' => false, 'label' => $attributeLabel . ': ' . $value);

            case 'custom_field':
                // a product meta field, stored as it is compared: "key=value"
                return strpos($value, '=') !== false
                    ? array('found' => true, 'row' => self::filterRow('product_custom_fields', $value), 'label' => $value)
                    : null;

            case 'custom_attribute':
                // an attribute typed on the product itself (not a global one): "name:value"
                return strpos($value, ':') !== false
                    ? array('found' => true, 'row' => self::filterRow('product_custom_attributes', $value), 'label' => $value)
                    : null;

            case 'seller':
                $user = get_user_by('login', $value) ?: (get_user_by('email', $value) ?: get_user_by('slug', sanitize_title($value)));

                return $user
                    ? array('found' => true, 'row' => self::filterRow('product_sellers', $user->ID), 'label' => $user->display_name)
                    : array('found' => false, 'label' => $value);

            case 'collection':
                // Pro-only - a named, admin-managed Product Collection (see
                // 09-conditions.md). Resolved unconditionally regardless of
                // isProActive() - licensing is checked programmatically after the fact, see
                // detectProFeature().
                $collection = self::findProductCollection($value);

                return $collection
                    ? array('found' => true, 'row' => self::filterRow('product_collections', (string)$collection->id), 'label' => $collection->title)
                    : array('found' => false, 'label' => $value);

            default:
                return null;
        }
    }

    /**
     * @return \ADP\ProVersion\Includes\Database\Models\Collection|null
     */
    public static function findProductCollection($name)
    {
        $name = trim($name);
        if ($name === '' || ! class_exists('\ADP\ProVersion\Includes\Database\Repository\CollectionRepository')) {
            return null;
        }

        $matches = \ADP\ProVersion\Includes\Database\Repository\CollectionRepository::searchProductCollections($name, 5);
        if (empty($matches)) {
            return null;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
        foreach ($matches as $collection) {
            $title = function_exists('mb_strtolower') ? mb_strtolower($collection->title) : strtolower($collection->title);
            if ($title === $needle) {
                return $collection;
            }
        }

        return reset($matches);
    }

    /**
     * Fuzzy-matches $name (exact, then partial, case-insensitive) against the 'text' field of
     * a Helpers::<$helperMethod>() list (each item shaped {id, text} - see Helpers.php), and
     * returns the matching item's 'id'. Used for every Pro condition whose values come from a
     * fixed WooCommerce/plugin list (shipping zones, states, countries, currencies, payment
     * gateways) rather than a product/term lookup.
     */
    public static function resolveHelperListValue($helperMethod, $name)
    {
        if ( ! method_exists(Helpers::class, $helperMethod)) {
            return null;
        }

        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $list = call_user_func(array(Helpers::class, $helperMethod));
        if ( ! is_array($list)) {
            return null;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);

        // the stored value itself (a country's ISO code, a currency code, a method id) - the one
        // spelling that does not depend on the language the site's lists are shown in
        foreach ($list as $item) {
            if (strtolower((string)($item['id'] ?? '')) === $needle) {
                return $item['id'];
            }
        }

        foreach ($list as $item) {
            $text = function_exists('mb_strtolower') ? mb_strtolower((string)($item['text'] ?? '')) : strtolower((string)($item['text'] ?? ''));
            if ($text === $needle) {
                return $item['id'];
            }
        }

        // Helpers::getStates() (and similar) append a "(Country)" suffix to every entry
        // whenever the shop's default country differs from the entry's own country - e.g.
        // "California(United States (US))" - which means a plain state name never
        // exact-matches above. Truncating at the first "(" before comparing catches that
        // case (the suffix can itself contain parens, e.g. "(US)" nested inside "(United
        // States (US))", so stripping only a trailing balanced "(...)" isn't enough), and is
        // checked BEFORE the broad substring pass below so a generic name like "California"
        // doesn't instead match an unrelated entry that merely contains it as a substring
        // (e.g. "Baja California(Mexico)").
        foreach ($list as $item) {
            $text = function_exists('mb_strtolower') ? mb_strtolower((string)($item['text'] ?? '')) : strtolower((string)($item['text'] ?? ''));
            $parenPos = strpos($text, '(');
            $stripped = trim($parenPos !== false ? substr($text, 0, $parenPos) : $text);
            if ($stripped !== '' && $stripped === $needle) {
                return $item['id'];
            }
        }

        foreach ($list as $item) {
            $text = function_exists('mb_strtolower') ? mb_strtolower((string)($item['text'] ?? '')) : strtolower((string)($item['text'] ?? ''));
            if ($text !== '' && strpos($text, $needle) !== false) {
                return $item['id'];
            }
        }

        return null;
    }

    public static function filterRow($filterType, $value)
    {
        return array(
            'qty'             => '1',
            'type'            => $filterType,
            'method'          => 'in_list',
            'value'           => is_array($value) ? $value : array((string)$value),
            'product_exclude' => array('on_wc_sale' => '', 'already_affected' => '', 'backorder' => ''),
            'excludes'        => array(),
        );
    }

    /**
     * @param string $name product title or SKU
     *
     * @return \WC_Product|null
     */
    public static function findProduct($name)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $productId = wc_get_product_id_by_sku($name);
        if ($productId) {
            return wc_get_product($productId);
        }

        // "85", "#85", "Beanie (#85)" - the model copies the id from the open rule's description;
        // searching that as text would match any product with "85" in its name
        if (preg_match('/^#?(\d+)$/', $name, $m) || preg_match('/\(#(\d+)\)\s*$/', $name, $m)) {
            $product = wc_get_product((int)$m[1]);
            if ($product) {
                return $product;
            }
        }

        $product = self::searchProduct($name);

        // "2 free Caps", "4 Albums" - the model tends to pass the name as the user wrote it
        if ( ! $product && preg_match('/^(.{3,}?)(es|s)$/iu', $name, $m)) {
            $product = self::searchProduct($m[1]);
            if ( ! $product && strtolower($m[2]) === 'es') {
                $product = self::searchProduct($m[1] . 'e');
            }
        }

        return $product;
    }

    /**
     * A product whose title contains $name - the one titled exactly $name if there is one, as
     * a partial match ("Cap" in "Captain's hat") may come first.
     *
     * @return \WC_Product|null
     */
    private static function searchProduct($name)
    {
        /** @var \WC_Product_Data_Store_CPT $dataStore */
        $dataStore = WC_Data_Store::load('product');
        $ids       = $dataStore->search_products($name, '', true, false, 20);
        if (empty($ids)) {
            return null;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $title = function_exists('mb_strtolower') ? mb_strtolower($product->get_name()) : strtolower($product->get_name());
                if ($title === $needle) {
                    return $product;
                }
            }
        }

        foreach ($ids as $id) {
            if ($id && ($product = wc_get_product($id))) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @param string $name category name
     *
     * @return \WP_Term|null
     */
    public static function findCategory($name)
    {
        return self::findTermInTaxonomy('product_cat', $name);
    }

    /**
     * @param string $taxonomy registered taxonomy name (e.g. 'product_cat', 'product_tag', 'pa_color')
     * @param string $name     term name to search for
     *
     * @return \WP_Term|null
     */
    public static function findTermInTaxonomy($taxonomy, $name)
    {
        $name = trim($name);
        if ($name === '' || ! taxonomy_exists($taxonomy)) {
            return null;
        }

        $term = get_term_by('name', $name, $taxonomy);
        if ($term) {
            return $term;
        }

        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'search'     => $name,
            'number'     => 1,
            'hide_empty' => false,
        ));

        return ! empty($terms) && ! is_wp_error($terms) ? reset($terms) : null;
    }

    /**
     * Matches a custom product taxonomy (e.g. "Brand") by its label or slug - the
     * same taxonomy list the normal rule list's filter dropdown offers (any
     * product-attached taxonomy besides the built-in category/tag).
     *
     * @param string $label taxonomy label or slug as the user described it
     *
     * @return string|null taxonomy name (e.g. 'product_brand')
     */
    public static function findCustomTaxonomy($label)
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $needle     = strtolower($label);
        $taxonomies = Helpers::getCustomProductTaxonomies();

        foreach ($taxonomies as $name => $tax) {
            if (strtolower($name) === $needle || strtolower($tax->label) === $needle) {
                return $name;
            }
        }

        foreach ($taxonomies as $name => $tax) {
            if (stripos($tax->label, $label) !== false || stripos($label, $tax->label) !== false) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Matches a global WooCommerce product attribute (e.g. "Color") by its label
     * or slug, returning the attribute's taxonomy name (e.g. 'pa_color') - only
     * registered global attributes are matched, not per-product custom attributes.
     *
     * @param string $label attribute label or slug as the user described it
     *
     * @return string|null attribute taxonomy name (e.g. 'pa_color')
     */
    public static function findAttributeTaxonomy($label)
    {
        $label = trim($label);
        if ($label === '' || ! function_exists('wc_get_attribute_taxonomies')) {
            return null;
        }

        $needle     = strtolower($label);
        $attributes = wc_get_attribute_taxonomies();

        foreach ($attributes as $attribute) {
            if (strtolower($attribute->attribute_name) === $needle || strtolower($attribute->attribute_label) === $needle) {
                return wc_attribute_taxonomy_name($attribute->attribute_name);
            }
        }

        foreach ($attributes as $attribute) {
            if (stripos($attribute->attribute_label, $label) !== false) {
                return wc_attribute_taxonomy_name($attribute->attribute_name);
            }
        }

        return null;
    }

    /**
     * @param string $name WP role display name or slug (e.g. "Wholesale Customer" or "wholesale_customer")
     *
     * @return string|null role slug
     */
    public static function findRole($name)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $roles = wp_roles()->roles;

        if (isset($roles[$name])) {
            return $name;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);

        foreach ($roles as $slug => $details) {
            $label = translate_user_role($details['name']);
            if ((function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label)) === $needle
                || strtolower($slug) === strtolower($name)) {
                return $slug;
            }
        }

        foreach ($roles as $slug => $details) {
            $label = function_exists('mb_strtolower') ? mb_strtolower(translate_user_role($details['name'])) : strtolower(translate_user_role($details['name']));
            if (strpos($label, $needle) !== false || strpos($needle, $label) !== false) {
                return $slug;
            }
        }

        return null;
    }
}
