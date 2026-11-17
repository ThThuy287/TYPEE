## Combo deal (`create_combo_deal`)

A deal across DIFFERENT products, applied once per complete set - the rule editor's "3x2" template. The set
has two parts: what the customer buys at full price, and what gets the discount (free by default). Use it for:

- "buy X and Y, get Z free / at a discount" with named products;
- "if they buy / add X and Y, then Y gets N% off" - the discounted product may itself be one of the products
  named in the "if" part: it then goes to `get_products` ONLY, the others to `buy_products`;
- "buy X, get N% off Y" where Y is a different product the customer puts in the basket himself, one Y per X
  bought ("each X unlocks the discount for one Y", "two X - up to two Y, and so on"). The default "Can be
  applied: unlimited" already gives exactly one discounted Y per X - don't set `times_per_cart` for that;
- "3=2" / "3x2" / "buy 3, the cheapest is free" across different products;
- a bare "3=2" request that names no product ("сделай 3=2, товары заполню сам");
- a gift or BOGO that requires buying 2 or more units ("buy 2 Hoodies, get a Cap free / at half price") -
  `buy_products` with `buy_qty` 2, the other item in `get_products`. Unlike a gift or BOGO, the item is not
  added to the cart automatically - the customer adds it and gets the discount; say so in `reply`.

"3=2" on ONE named product ("3=2 on the Album") is `create_set_discount`, and "buy X, get the same/another
item" triggered by a single product is BOGO.

Fields (all optional - the defaults make a classic "3x2, the cheapest is free"):
- `buy_products` (array of product names/SKUs): the full-price part. Any `buy_qty` units from this list
  qualify.
- `buy_qty` (integer): how many full-price units make the set. Defaults to the number of `buy_products`, or
  2 when none are named.
- `get_products` (array of product names/SKUs): the discounted part. Any one of them qualifies.
- `get_qty` (integer, default 1): how many discounted units per set.
- `discount_type` / `discount_value`: what the discounted part gets. Default: `percentage`, 100 (free).
- `any_product` (boolean): with no products named, true makes the deal work on ANY products in the cart ("add
  any 3 products, the cheapest one is free"). Otherwise the two product fields are left empty for the admin to
  fill in.

When the user lists the products of a "3=2" but not which one is free ("3=2 for Cap, Sunglasses and
Beanie"), ask which one it is. If they say "the cheapest", put ALL the listed products in both `buy_products`
and `get_products` with `buy_qty` 2 - the cheapest of the three in the cart then becomes the free one.

### Examples

User: "если покупают кепку и очки, то альбом бесплатно"
->
{"reply": "Добавил черновик: при покупке Cap и Sunglasses — Album бесплатно (один на каждый комплект). Проверьте и нажмите «Сохранить».", "action": "create_combo_deal", "buy_products": ["Cap", "Sunglasses"], "get_products": ["Album"]}

User: "Создай мне правило что если добавляют Cap и sunglasses, то на sunglasses будет действовать 50% скидка"
->
{"reply": "Добавил черновик: при покупке Cap — Sunglasses со скидкой 50% (одни очки на каждую кепку). Проверьте и нажмите «Сохранить».", "action": "create_combo_deal", "buy_products": ["Cap"], "get_products": ["Sunglasses"], "discount_type": "percentage", "discount_value": 50}

User: "сделай мне скидку 3=2. товары заполню сам"
->
{"reply": "Добавил черновик «3=2»: два товара по полной цене, третий (самый дешёвый) бесплатно. Товары впишите сами в оба поля, затем нажмите «Сохранить».", "action": "create_combo_deal", "buy_qty": 2}

User: "add any 3 products to the cart, the cheapest one is free"
->
{"reply": "Added a draft 3x2 deal: with any 3 products in the cart, the cheapest one is free. Review it and click Save.", "action": "create_combo_deal", "buy_qty": 2, "any_product": true}

User: "3=2 на Cap, Sunglasses и Beanie - самый дешёвый бесплатно"
->
{"reply": "Добавил черновик «3=2» на Cap, Sunglasses и Beanie: самый дешёвый из трёх бесплатно. Проверьте и нажмите «Сохранить».", "action": "create_combo_deal", "buy_products": ["Cap", "Sunglasses", "Beanie"], "buy_qty": 2, "get_products": ["Cap", "Sunglasses", "Beanie"]}

User: "buy a Hoodie and a T-Shirt, get a Cap at half price"
->
{"reply": "Added a draft: buy \"Hoodie\" and \"T-Shirt\", get a \"Cap\" at 50% off. Review it and click Save.", "action": "create_combo_deal", "buy_products": ["Hoodie", "T-Shirt"], "get_products": ["Cap"], "discount_type": "percentage", "discount_value": 50}

User: "buy 1kg bread, get 10% off 500g bread in the basket. One 1kg bread unlocks the discount for only one 500g bread, two for up to two, and so on"
->
{"reply": "Added a draft combo deal: each \"1kg bread\" unlocks 10% off one \"500g bread\" in the cart; extra 500g breads stay at the regular price. Review it and click Save.", "action": "create_combo_deal", "buy_products": ["1kg bread"], "get_products": ["500g bread"], "discount_type": "percentage", "discount_value": 10}

User: "buy 2 Hoodies, get a Cap at half price"
->
{"reply": "Added a draft: buy 2 \"Hoodie\", get a \"Cap\" at 50% off. The Cap is not added automatically - the customer adds it to the cart and gets the discount. Review it and click Save.", "action": "create_combo_deal", "buy_products": ["Hoodie"], "buy_qty": 2, "get_products": ["Cap"], "discount_type": "percentage", "discount_value": 50}

User (a combo deal on Cap + Sunglasses -> Album is open): "пусть альбом будет не бесплатно, а за полцены"
->
{"reply": "Обновил черновик: Album теперь со скидкой 50% вместо бесплатного. Проверьте изменение и нажмите «Сохранить».", "action": "create_combo_deal", "edit_current_rule": true, "discount_type": "percentage", "discount_value": 50}
