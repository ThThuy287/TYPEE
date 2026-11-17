<?php

namespace ADP\BaseVersion\Includes\Ai;

use ADP\BaseVersion\Includes\Database\Repository\RuleRepository;
use ADP\Factory;

defined('ABSPATH') or exit;

/**
 * Reads saved rules back: the stored data, and a compact description of it with ids resolved
 * to names.
 */
class RuleDescriber
{
    /**
     * @return array the rule's non-empty editable blocks plus `target_names` (ids resolved to names)
     */
    public static function describeRule($rule)
    {
        return json_decode(self::summarizeRuleForContext($rule), true) ?: array();
    }

    /**
     * @param int $ruleId
     *
     * @return array|null Rule::getData() shape of the saved rule, or null if it no longer exists
     */
    public static function loadRule($ruleId)
    {
        $rows = (new RuleRepository())->getRules(array('id' => $ruleId));
        $rule = reset($rows);

        return $rule ? $rule->getData() : null;
    }

    /**
     * A compact JSON dump of the open rule's non-empty editable blocks, given to the AI as ground
     * truth for editing - without it, the AI can't reconstruct a correct FULL replacement for a
     * list-type field (e.g. adding one more tier to `bulk_adjustments.ranges` without dropping the
     * existing tiers, since it has no other way to know what they currently are).
     */
    public static function summarizeRuleForContext($rule)
    {
        $keys = array(
            'filters', 'conditions', 'product_adjustments', 'bulk_adjustments',
            'role_discounts', 'cart_adjustments', 'get_products', 'additional',
        );

        $summary = array();
        foreach ($keys as $key) {
            if ( ! empty($rule[$key])) {
                $summary[$key] = $rule[$key];
            }
        }

        // resolves the raw product/category/etc. ids embedded above into human-readable
        // names, same as the normal rule list's own wdp_data.titles - without this the AI
        // can only describe filter targets by numeric id, e.g. when the user simply asks
        // "what does this rule do". A few keys (users_list chief among them) come back
        // non-empty from getTitles() as a default autocomplete seed list regardless of
        // whether this rule actually uses that filter type - excluded here since they're
        // not about THIS rule and would otherwise read like part of its configuration.
        $irrelevantKeys = array('users_list', 'rules_list', 'coupons', 'subscriptions', 'usermeta', 'product_sellers', 'product_collections');
        $titles = self::filterTitlesHelper()->getTitles(array($rule));
        $titles = array_diff_key($titles, array_flip($irrelevantKeys));
        $summary['target_names'] = array_filter(array_map(function ($byId) {
            return array_values($byId);
        }, $titles));

        return wp_json_encode($summary);
    }

    /**
     * @return \ADP\BaseVersion\Includes\AdminExtensions\AdminPage\AdminPageFilterTitles
     */
    public static function filterTitlesHelper()
    {
        return Factory::get("AdminExtensions_AdminPage_AdminPageFilterTitles");
    }
}
