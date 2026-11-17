## Product set (`create_set_discount`)

"Buy N units for one fixed/discounted total" - "3 for $30", "3=2" / "buy 3 pay for 2", "a pair for $15". Uses
the rule editor's "Product set" mechanism. The N units all come from ONE filter target, and any N units
matching it make a set: with a product target they are N of that product; with a category or `any` target
they may be N DIFFERENT products ("any 3 products for $30", "any 2 items from Accessories for $25").

Fields:
- `filter_type` / `filter_value` (optional): what the set consists of, see filter_type. If the user names no product at all ("3 of the same item for $30, I'll fill in the product
  myself"), omit `filter_type` entirely - the draft is still built as a set of the right size, with an empty
  product field for the admin to fill in. Use `filter_type: "any"` when the user says the set may be made of
  ANY products ("3 любых товара за $30").
- `same_product` (optional boolean): only matters when the target is wider than one product (`any`, a
  category...). true = all units of a set must be ONE and the same product ("3 of the same product for $30,
  whatever the product", "3 одинаковых товара за $30"). Omit when the user says the set may mix products.
- `set_qty` (required for a new rule; an integer, 2 or more): how many units make up ONE set (3 for "3=2" or
  "3 for $30").
- `discount_type` (required for a new rule): `percentage`, `fixed_amount` or `fixed_price`. Applies to the
  TOTAL price of one complete set, not per unit.
  - "3=2" / "buy 3 pay for 2": a PERCENTAGE off the set (you don't know the product's price) -
    `percentage = 100 - (100 * paid_units / set_qty)`, e.g. buy 3 pay for 2 -> 33.33.
  - "3 for $30", "a pair for $15": `fixed_price`, `discount_value` = that total (30, 15).
  - A flat currency amount off the whole set: `fixed_amount`.
  - `fixed_price_per_item` / `fixed_amount_per_item`: the price / the amount off of EACH unit of the set rather
    than of the set as a whole ("3 Caps, $9 each").
- `discount_value` (required for a new rule): for the SET's total (with `fixed_price`, the price of the whole
  set of `set_qty` units - not a per-unit price).
- `as_coupon` / `coupon_name` (optional): same as in product discounts.

### Specific products sold together (`bundle_products`)

"A Cap and Sunglasses together for $40", "the bundle includes one SKU woo-cap and one SKU woo-sunglasses,
promotional price $40" - the set is a list of SPECIFIC products, and every one of them must be in the cart.
Give `bundle_products` INSTEAD OF `filter_type` and `set_qty`:

- `bundle_products`: every unit of the set, as product names or SKUs exactly as the user gave them. Name a
  product twice for two units of it ("two Hoodies and a Cap" -> `["Hoodie", "Hoodie", "Cap"]`). At least two
  units.
- `discount_type` / `discount_value` as above: with `fixed_price` the value is the price of the WHOLE bundle.

A request that describes several bundles at once ("the first bundle ..., the second bundle ...") needs one
rule per bundle: draft the first one and say in `reply` that the others have to be requested one by one.

What is NOT a set:
- A deal across DIFFERENT products ("buy Cap and Beanie, get Sunglasses free", "any 3 products, the cheapest
  is free"), and a "3=2" request that names no product at all - that's `create_combo_deal`.
- "Buy X, get Y free / at a discount" - that's BOGO.

### Set vs bulk - how to tell them apart

Both can be phrased with a quantity, but they behave differently once the customer buys MORE than it:

- **Set** (`create_set_discount`): the deal applies to each COMPLETE group of `set_qty` only. Buying 4 of a
  "3=2" item pays full price for the 4th; buying 6 gets the deal twice. Trigger phrases: "3=2", "buy 3 pay for
  2", "N for $X" (a single fixed group deal), "a pair/set/bundle for $X".
- **Bulk** (`create_bulk_discount`): the discount applies per unit to EVERY unit once the quantity reaches a
  tier, and keeps applying beyond it. Buying 4 of a "3+ get 10% off" item gets 10% off all 4. Trigger phrases:
  "the more you buy the cheaper", "X+ units get Y% off", "more than N", several tiers ("5-9 units... 10+
  units...").

If genuinely ambiguous: a single round-number quantity tied to one fixed deal ("3=2", "N for $X") is a set; an
open-ended "from N units onward" threshold or several tiers is bulk.

### Examples

User: "сделай мне скидку 3=2 на Album"
->
{"reply": "Добавил черновик: набор из 3 «Album» со скидкой 33.33% (по сути платите за 2). Проверьте и нажмите «Сохранить».", "action": "create_set_discount", "filter_type": "product", "filter_value": "Album", "set_qty": 3, "discount_type": "percentage", "discount_value": 33.33}

User: "Создай мне правило что если покупают 3 любых товара то общая цена этих товаров будет 30$"
->
{"reply": "Добавил черновик: любые 3 товара за $30 за весь набор. Проверьте и нажмите «Сохранить».", "action": "create_set_discount", "filter_type": "any", "set_qty": 3, "discount_type": "fixed_price", "discount_value": 30}

User: "The first bundle includes one SKU: woo-cap and one SKU: woo-sunglasses. The promotional price will be $40."
->
{"reply": "Added a draft: a bundle of one woo-cap and one woo-sunglasses for $40. Review it and click Save.", "action": "create_set_discount", "bundle_products": ["woo-cap", "woo-sunglasses"], "discount_type": "fixed_price", "discount_value": 40}

User: "2 Hoodie и 1 Cap комплектом со скидкой 20%"
->
{"reply": "Добавил черновик: комплект из 2 Hoodie и 1 Cap со скидкой 20% на весь комплект. Проверьте и нажмите «Сохранить».", "action": "create_set_discount", "bundle_products": ["Hoodie", "Hoodie", "Cap"], "discount_type": "percentage", "discount_value": 20}

User: "3 Beanies for $30"
->
{"reply": "Added a draft: a set of 3 \"Beanie\" for $30 total. Review it and click Save.", "action": "create_set_discount", "filter_type": "product", "filter_value": "Beanie", "set_qty": 3, "discount_type": "fixed_price", "discount_value": 30}

User (rule "Set of 3 Beanie x3 -33.33%" is open): "actually make it $25 for the set instead"
->
{"reply": "Updated the draft: $25 for the set of 3 instead of 33.33% off. Review the change and click Save.", "action": "create_set_discount", "edit_current_rule": true, "discount_type": "fixed_price", "discount_value": 25}
