## Bulk / tiered discount (`create_bulk_discount`)

Quantity-tiered pricing on ONE filter target - "the more you buy, the bigger the discount". Once the cart
quantity reaches a tier, that tier's discount applies to EVERY unit (no "complete groups only" behavior). A
single fixed group deal like "3=2" or "3 for $30" is NOT this - that's `create_set_discount` (its section
explains how to tell them apart).

Fields:
- `filter_type` / `filter_value` (required): see filter_type - `any` when no product/category is named.
- `discount_type` (required): `percentage`, `fixed_amount` or `fixed_price`. Applies to every tier.
- `ranges` (required): a non-empty array of tiers `{"from": <int>, "to": <int|null>, "value": <number>}`.
  - `from`/`to` are inclusive quantity boundaries; `"to": null` for the last, open-ended tier ("10 or more").
  - `value` is that tier's discount in the unit implied by `discount_type` (percent, amount, or price per
    item).
  - Ascending order, no overlaps. A tier with no discount is simply left out.
- `count_by` (optional): what a tier's quantity counts.
  - `each_product` (default): the units of ONE product - "buy 3 or more Beanies", "3 or more of the same item".
  - `all_matched`: all the items the filter matches, together - "any 3 or more items from the Accessories
    category", "5 or more products in total". Use it whenever the target is wider than one product and the
    user counts items across it.
  - `whole_cart`: everything in the cart, matched or not.
  - Use `all_matched` / `whole_cart` only when the user says the units are counted together ("in total",
    "combined", "any 5 items", "5 items in the cart"). A plain "buy 5 or more units" stays `each_product`,
    also for a store-wide rule.
  - `each_variation`: like `each_product`, but every variation of a variable product on its own.
- `bulk_mode` (optional): `"bulk"` (default) - the tier reached applies to EVERY unit; `"tier"` - each tier's
  discount applies only to the units inside that tier ("the first 5 at full price, units 6-10 at 10% off").
- `measure_by` (optional): what `from`/`to` measure - `"qty"` (default), `"sum"` (amount spent: "spend $100 on
  Beanies, get 10% off them") or `"weight"`.
- `table_message` (optional string): text shown above the bulk pricing table on the product page.

### Examples

User: "set up bulk pricing for the Beanie: 1-4 units no discount, 5-9 units 10% off, 10+ units 20% off"
->
{"reply": "Added a draft bulk pricing rule for \"Beanie\": 10% off for 5-9 units, 20% off for 10+. Review it in the rules list and click Save to apply it.", "action": "create_bulk_discount", "filter_type": "product", "filter_value": "Beanie", "discount_type": "percentage", "ranges": [{"from": 5, "to": 9, "value": 10}, {"from": 10, "to": null, "value": 20}]}

User: "can you write a rule that if you buy 3 items you get 5% off, and if you buy 5 items you get 10% off?"
->
{"reply": "Added a draft bulk pricing rule (any product): 5% off at 3+ units, 10% off at 5+. Review it and click Save.", "action": "create_bulk_discount", "filter_type": "any", "discount_type": "percentage", "ranges": [{"from": 3, "to": 4, "value": 5}, {"from": 5, "to": null, "value": 10}]}

User: "Buy 3 or more items from the Accessories category, get 10% off each"
->
{"reply": "Added a draft bulk pricing rule for the \"Accessories\" category: 10% off when the cart has 3 or more items from it. Review it and click Save.", "action": "create_bulk_discount", "filter_type": "category", "filter_value": "Accessories", "discount_type": "percentage", "count_by": "all_matched", "ranges": [{"from": 3, "to": null, "value": 10}]}

User: "buy 2 or more sunglasses, each one costs $25"
->
{"reply": "Added a draft: buy 2+ \"Sunglasses\", each priced at $25. Review it and click Save.", "action": "create_bulk_discount", "filter_type": "product", "filter_value": "Sunglasses", "discount_type": "fixed_price", "ranges": [{"from": 2, "to": null, "value": 25}]}
