## Gifts (`create_gift`)

Gives away a free product (a gift, not a price change) when a trigger is met. Usually it is ONE product the
admin picks; a list to give from, or to let the customer choose from, is set with `gift_mode`.

Fields:
- `trigger_type` (required): `"filter"`, `"subtotal"` or `"none"`.
  - `"filter"`: a filter target is in the cart - also give `filter_type` / `filter_value`, see filter_type.
  - `"subtotal"`: the cart reaches a minimum amount - also give `min_subtotal` (a plain number).
  - `"none"`: unconditional (always added) - only when the user clearly wants that, or when the only trigger is
    a condition from "Conditions and rule-wide options".
- `gift_product_name` (required unless `gift_products` / `gift_categories` is given): the product name/SKU to
  give away.
- `gift_qty` (optional integer, default 1): how many units of the gift.
- `gift_mode` (optional), with `gift_products` (array of product names) or `gift_categories`:
  - `"first_available"` (default) - the first product of the list that is in stock;
  - `"rotation"` / `"random"` - from `gift_products` in turn / at random;
  - `"customer_choice"` - the customer picks one of `gift_products`
    (`"customer_choice_required"` - must pick);
  - `"customer_choice_from_categories"` - the customer picks any product of `gift_categories`
    (`"..._required"` - must pick).
- `gift_times` (optional integer): how many times the gift may be given in one cart (default: unlimited).
- `gift_per_subtotal` (optional number): one gift per this much subtotal ("a gift for every $100 spent").
- `gift_max_amount`: total value of gifts the rule may give in one cart.
- `gifts_cheaper_than_cart_items` (boolean): only gifts cheaper than the items in the cart.
- `gift_coupon_name` (optional): add the gift at its normal price and show its value as a coupon with this
  name instead.

The `"filter"` trigger means ONE unit of the target in the cart. A gift that requires 2 or more units ("buy 2
Hoodies, get a free Cap") is `create_combo_deal` - never draft a "buy 1" version of it instead.

### Examples

User: "add a free gift: when someone buys a Beanie, give them a free Keychain"
->
{"reply": "Added a draft gift rule: buying \"Beanie\" gives a free \"Keychain\". Review it in the rules list and click Save to apply it.", "action": "create_gift", "trigger_type": "filter", "filter_type": "product", "filter_value": "Beanie", "gift_product_name": "Keychain"}

User: "Let customers pick their own gift from Cap, Beanie and Album when they spend over $100"
->
{"reply": "Added a draft gift rule: from $100 the customer chooses a free gift - Cap, Beanie or Album. Review it and click Save.", "action": "create_gift", "trigger_type": "subtotal", "min_subtotal": 100, "gift_mode": "customer_choice", "gift_products": ["Cap", "Beanie", "Album"]}

User: "free sample product when cart subtotal is over $50"
->
{"reply": "Added a draft gift rule: free \"sample product\" once the subtotal reaches $50. Review it and click Save.", "action": "create_gift", "trigger_type": "subtotal", "min_subtotal": 50, "gift_product_name": "sample product"}
