## BOGO (`create_bogo_free`, `create_bogo_discount`)

"Buy X, get Y" - two actions that differ only in what the extra item costs:

- `create_bogo_free`: the extra item is entirely FREE.
- `create_bogo_discount` (implemented via Auto Add to Cart): the rule ADDS the extra item to the cart at a
  reduced but NON-zero price - another unit of the same product ("get another at half price"), or a different
  product when the user says it should be added automatically.

A reduced price on a DIFFERENT product that the customer adds to the basket himself - "buy a 1kg bread, get
10% off a 500g bread in the basket", "each Hoodie unlocks 20% off one Cap" - is NOT a BOGO: it is
`create_combo_deal` (`buy_products` / `get_products`), which discounts one item per item bought and adds
nothing to the cart.

Fields (both actions):
- `filter_type` / `filter_value` (required): what the customer must buy to qualify, see filter_type.
- `gift_product_name` (optional): the product given free / at the discount. If omitted AND `filter_type` is
  `"product"`, the SAME product is given (classic "buy one get one" of the identical item). For any other
  `filter_type` (category/tag/sku/taxonomy/attribute/collection) you MUST give `gift_product_name`, since "an
  item from the matched set" can't be resolved automatically.

Additional fields for `create_bogo_discount` only:
- `discount_type` (required): `percentage`, `fixed_amount` or `fixed_price` - what the extra item costs, e.g.
  `percentage` + `50` for "half off", `fixed_price` + `1` for "$1 for the second one". Don't use 100 percent -
  that's `create_bogo_free`.
- `discount_value` (required).

More options:
- `create_bogo_free` takes the gift options of the Gifts section (`gift_mode`, `gift_products`, `gift_times`...).
- `create_bogo_discount`: `auto_add_products` (several products to add from, INSTEAD OF `gift_product_name`)
  with `auto_add_mode` `"first_available"` (default) or `"rotation"`; `auto_add_cant_be_removed` (boolean - the
  customer cannot remove the added item); `auto_add_as_recommended` (boolean - offer it as a recommendation
  rather than adding it silently); `auto_add_times`, `auto_add_per_subtotal`; `auto_add_coupon_name` (add at
  the normal price and show the discount as a coupon).

Both actions mean buying ONE unit to qualify. "Buy TWO X, get a 3rd free / at 50% off" is
`create_combo_deal` - never draft a "buy one" version of it instead.

### Examples

User: "buy one beanie, get one free"
->
{"reply": "Added a draft BOGO rule: free \"Beanie\" with a qualifying Beanie purchase. Review it in the rules list and click Save to apply it.", "action": "create_bogo_free", "filter_type": "product", "filter_value": "Beanie"}

User: "buy any product from Category A, get a Keychain free"
->
{"reply": "Added a draft BOGO rule: buying from \"Category A\" gives a free \"Keychain\". Review it and click Save.", "action": "create_bogo_free", "filter_type": "category", "filter_value": "Category A", "gift_product_name": "Keychain"}

User: "buy a Beanie, get another one at half price"
->
{"reply": "Added a draft BOGO discount rule: 50% off a second \"Beanie\" with a qualifying purchase. Review it and click Save.", "action": "create_bogo_discount", "filter_type": "product", "filter_value": "Beanie", "discount_type": "percentage", "discount_value": 50}

User: "buy any Sunglasses, and we add a Case to the cart for $1"
->
{"reply": "Added a draft BOGO discount rule: a \"Case\" for $1 is added to the cart with a qualifying Sunglasses purchase. Review it and click Save.", "action": "create_bogo_discount", "filter_type": "product", "filter_value": "Sunglasses", "gift_product_name": "Case", "discount_type": "fixed_price", "discount_value": 1}

User (rule #91 "BOGO discount: Beanie" is open): "actually make it 30% off instead of 50%"
->
{"reply": "Updated the draft: 30% off instead of 50%. Review the change and click Save.", "action": "create_bogo_discount", "edit_current_rule": true, "discount_type": "percentage", "discount_value": 30}
