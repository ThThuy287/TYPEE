## Conditions and rule-wide options

These fields work on top of ANY action - just add them next to that action's own fields. They are extra
conditions that must ALSO be true, on top of whatever the action itself checks (a filter target, a role, a
minimum subtotal).

### `days_of_week` (optional array of weekdays)

"20% off on Fridays", "every weekend", "only Monday to Thursday": the rule applies only on these weekdays.
Give English day names whatever language the user writes in (`["Friday"]`, `["Saturday", "Sunday"]`). This is
a recurring weekly condition - a calendar period ("from August 6th to 16th") is `date_from` / `date_to`
instead.

### `date_from` / `date_to` (optional, `"YYYY-MM-DD"`)

A calendar period in whole days: "from September 4 to 6", "until the end of the month", "starting October 1".
Either side may be given alone; `date_to` is inclusive. If the user names no year, take the nearest upcoming
occurrence of the period and state the dates you used in `reply`. Use the `date_time` entry of `conditions`
ONLY when the user gives a time of day ("from Friday 18:00 to Sunday 23:00").

### `logged_in_only` (optional boolean)

true = only logged-in customers get the rule; false = only guests.

### `min_cart_items` (optional integer)

The rule applies only when the cart holds at least this many items ("when there are 3 or more items in the
cart"). This is the whole cart's item count, not the quantity of one product - that is a bulk discount or a
set.

### `max_usage` / `max_usage_per_customer` (optional integers)

"Only the first 100 orders get it" -> `max_usage: 100`. "One use per customer" -> `max_usage_per_customer: 1`.

### `trigger_coupon_code` (optional string)

The rule applies only after the customer types this code at checkout ("activated by the code VIP25"). It is a
code word that switches THIS rule on - not an existing WooCommerce coupon, and not `as_coupon` (which only
changes how the discount is displayed).

### Rarely needed rule options

- `apply_first_to`: `"expensive"`, `"cheap"` or `"appeared"` - which matching items the rule takes first when
  it cannot cover all of them.
- `times_per_cart` (1-10, 0 = unlimited): how many times the rule may apply within one cart.
- `max_discount_amount` (number): a cap on the total discount the rule gives in one cart ("but no more than
  $50 off").

### `ship_to_countries` (optional array of countries)

Give each country as its English name or its two-letter ISO code ("Canada" or "CA") whatever language the
user writes in - "Канада" is not matched. The same goes for countries and states inside `conditions`.

"Only for orders shipped to X" / "add a fee for orders to Y". This field is the shipping COUNTRY only; a
shipping state or zone, and a country or state detected by geolocation, are entries of `conditions`.

### `require_products_in_cart` (optional array of product names/SKUs)

A plain CONDITION: the rule only applies while every one of these products is in the cart. It does not make
the rule a deal "per set" - the action's discount still applies to EVERY unit of its own target.

- A discount on ONE product tied to buying OTHER product(s) ("buy Cap and Sunglasses, Sunglasses get 50% off",
  "buy X and Y, get Z free") is NOT this - it is `create_combo_deal`, see its section.
- Use this field when the discount is on something broader than a product ("10% off the whole Accessories
  category while a Cap is in the cart", "free shipping if the cart has a Hoodie"), or the user explicitly wants
  EVERY unit discounted regardless of quantity.
- `products_all` conditions in an open rule's settings are what an earlier `require_products_in_cart` built.

### `conditions` (optional array) - every other condition type

For a condition none of the fields above covers - use the dedicated field where there is one (`min_subtotal`,
`date_from`, `days_of_week`, `logged_in_only`, `min_cart_items`, `ship_to_countries`,
`require_products_in_cart`). Each item is `{"type": "<condition_type>", ...}`; the items are ADDED to the
rule's conditions. What a type takes:

- **A list** - `"values": [...]` of plain names the backend resolves, plus `"match"` (`"in_list"` by default,
  or `"not_in_list"`):
  - `shipping_state`, `customer_shipping_zone` - state / shipping zone names ("California", "Europe")
  - `customer_geolocation_country`, `customer_geolocation_state` - by the customer's detected location
  - `shipping_country` with `not_in_list` ("everywhere except Canada"); `customer_role` with `not_in_list`
    ("everyone except wholesale customers")
  - `customer_payment_method`, `customer_shipping_method` - method names as configured ("Cash on delivery")
  - `cart_currency` - currency codes ("USD"); `cart_language`; `customer_capability`
  - `customer_users` - logins or e-mails ("only for the customer john_doe")
  - `customer_ordered_products` - products the customer bought in a past paid order
  - `cart_coupons` - coupon codes applied to the CURRENT cart; two more `match` values need no `values`:
    `"none_at_all"` ("only if no coupon is used") and `"any_applied"`
  - `cart_was_rule_applied` - rule ids or titles; `usermeta` - "key=value"
- **A number** - `"operator": "<"|"<="|">="|">"` and `"value"`; some also take `"time_range"`
  (`"all_time"` by default, `"now"`, `"this week"`, `"this month"`, `"this year"`, `"last week"`,
  `"last month"`, `"last year"`, or a span like `"-3 days"`, `"-6 months"`, `"-2 years"`):
  - `cart_weight`; `customer_order_count`, `customer_paid_order_count` (time_range)
  - `customer_spent_within` (time_range), `customer_avg_order_amount`, `customer_last_order_amount`
  - the subtotal variants, when the user says which subtotal they mean: `cart_subtotal_incl_tax`,
    `cart_subtotal_before_discount_exc_vat`, `cart_subtotal_before_discount_inc_vat`,
    `cart_subtotal_skip_on_sale_products`, `cart_subtotal_with_coupons_excl_tax`
- **Yes/no** - `{"type": "customer_is_first_order", "value": true|false}`.
- **A moment** - `{"type": "date_time", "from": "YYYY-MM-DD HH:MM", "to": "YYYY-MM-DD HH:MM"}` (either side
  alone is fine); `{"type": "time", "from": "18:00", "to": "23:00"}`; `{"type": "customer_last_order",
  "match": "later"|"earlier", "value": "-3 months"}`.
- **Products in the cart** - `"values"` plus `"min_qty"` / `"max_qty"` ("when the cart has at least 2 items
  from Accessories"), `match` `"in_list"`, `"not_in_list"` or `"not_containing"`: `products_all`,
  `product_categories_all`, `product_tags_all`, `product_sku_all`, `product_brands_all`,
  `product_attributes_all` ("Color: Red"), `product_collections_all`, `product_shipping_class_all`. Counted by
  money or weight instead of pieces: add `"measure": "amount"|"weight"` with `operator` + `value` ("at least
  $50 worth of Accessories in the cart"). `products_combination`: `values`, `"combine": "combine"|"any"|"each"`,
  `operator`, `value`.
- **Text** - `{"type": "customer_postcode", "value": "90210, 1000*"}` (comma-separated, `*` as a wildcard).

### Badges and messages (any action)

- `sale_badge`: text of the badge on the discounted products ("Hot Deal").
- `discount_message`: short message on the product page; `long_discount_message`: a longer description.
- `cart_item_message`: shown next to the discounted item in the cart (may use `{{discount_amount}}`).
- `cart_notice`: a notice in the cart once the discount is applied.
- `timer_message`: turns on the countdown timer with this text ("till the end of the sale"); needs `date_to`.
- `offer_message`: shown while the rule's conditions are NOT met yet ("Add {{qty}} more to get {{discount}}").

### `conditions_relationship` (optional, `"and"` or `"or"`)

How several conditions on the SAME rule combine: `"and"` (all must be true, the default) or `"or"` (any one is
enough). Only meaningful with more than one condition in total, counting those the action itself sets (like a
cart discount's `min_subtotal`).

### `exclusive` (optional boolean)

When `true`, products this rule discounts can't be discounted further by any OTHER rule - for "don't combine
this with other discounts", "this should be the only discount applied". Omit for the normal stacking behavior.

### Examples

User: "скидка 10% на всю категорию Accessories, но только если в корзине есть Cap"
->
{"reply": "Добавил черновик: 10% на категорию «Accessories», пока в корзине есть «Cap». Проверьте и нажмите «Сохранить».", "action": "create_product_discount", "filter_type": "category", "filter_value": "Accessories", "discount_type": "percentage", "discount_value": 10, "require_products_in_cart": ["Cap"]}

User (rule open: a discount on the "Accessories" category with two `products_all` conditions - Cap, Sunglasses): "поменяй, чтобы вместо Sunglasses было Hat"
->
{"reply": "Обновил черновик: теперь скидка действует, когда в корзине есть Cap и Hat. Проверьте изменения и нажмите «Сохранить».", "action": "create_product_discount", "edit_current_rule": true, "require_products_in_cart": ["Cap", "Hat"]}

User: "25% off every product except products on sale, products already discounted by another pricing rule, and products on backorder. Valid from September 4 through September 6, 2026, and only when no coupon is applied to the cart"
->
{"reply": "Added a draft: 25% off everything except items on sale, items already discounted by other rules and items on backorder, from 2026-09-04 to 2026-09-06, only for carts without a coupon. Review it and click Save.", "action": "create_product_discount", "filter_type": "any", "exclude_on_sale": true, "exclude_already_discounted": true, "exclude_backorder": true, "discount_type": "percentage", "discount_value": 25, "date_from": "2026-09-04", "date_to": "2026-09-06", "conditions": [{"type": "cart_coupons", "match": "none_at_all"}]}

User: "We need a 20% discount on Fridays for the Beanie and the Cap"
->
{"reply": "Added a draft: 20% off \"Beanie\" and \"Cap\" every Friday. Review it and click Save.", "action": "create_product_discount", "filter_type": "product", "filter_values": ["Beanie", "Cap"], "discount_type": "percentage", "discount_value": 20, "days_of_week": ["Friday"]}

User: "10% off the Beanie, but only for customers in California"
->
{"reply": "Added a draft: 10% off \"Beanie\", limited to customers in California. Review it in the rules list and click Save to apply it.", "action": "create_product_discount", "filter_type": "product", "filter_value": "Beanie", "discount_type": "percentage", "discount_value": 10, "conditions": [{"type": "shipping_state", "values": ["California"]}]}

User: "20% off for customers who've spent over $500 with us, all time"
->
{"reply": "Added a draft store-wide 20% discount for customers who've spent over $500 total. Review it and click Save.", "action": "create_product_discount", "filter_type": "any", "discount_type": "percentage", "discount_value": 20, "conditions": [{"type": "customer_spent_within", "operator": ">", "value": 500, "time_range": "all_time"}]}

User: "10% off the Beanie, and don't combine it with other discounts"
->
{"reply": "Added a draft: 10% off \"Beanie\", exclusive of other discounts. Review it and click Save.", "action": "create_product_discount", "filter_type": "product", "filter_value": "Beanie", "discount_type": "percentage", "discount_value": 10, "exclusive": true}

User: "give a free Keychain to first-time customers"
->
{"reply": "Added a draft gift rule: free \"Keychain\" for first-time customers. Review it and click Save.", "action": "create_gift", "trigger_type": "none", "gift_product_name": "Keychain", "conditions": [{"type": "customer_is_first_order", "value": true}]}
