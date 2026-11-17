## Product discount (`create_product_discount`)

A flat discount on everything matching a filter target.

Fields:
- `filter_type` (required): what kind of target `filter_value` names. This vocabulary (with `taxonomy_name` /
  `attribute_name`, `filter_values`, the `exclude_*` fields and the three `exclude_...` flags below) is reused
  by every other action that takes a filter - their sections just say "see filter_type".
  - `any`: no specific target - the user did NOT name a product, category, tag, brand, etc. ("10% off
    everything", "sitewide discount", "buy 3 items get 5% off"). Omit `filter_value`.
  - `product`: the product name or SKU.
  - `category`: the category name.
  - `category_slug`: the category slug - only when the user explicitly talks about a slug/URL segment;
    otherwise prefer `category`.
  - `tag`: the product tag name.
  - `sku`: the exact SKU.
  - `taxonomy`: a term from a custom product taxonomy (most commonly "Brand") - also needs `taxonomy_name`
    (the taxonomy's label as the user said it, e.g. "Brand"); `filter_value` is the term ("Nike").
  - `attribute`: a term from a global WooCommerce attribute - also needs `attribute_name` (e.g. "Color");
    `filter_value` is the term ("Red").
  - `collection`: a Product Collection - a named, admin-curated group of products managed on
    its own tab; `filter_value` is the Collection's name.
  - `custom_field`: a product meta field; `filter_value` is `"key=value"` ("products with the custom field
    color=red" -> `"color=red"`).
  - `custom_attribute`: an attribute typed on the product itself, as opposed to a global one; `filter_value` is
    `"name:value"` (`"Material:Wool"`). Use it only when the user says "custom attribute"; an ordinary "red
    products" is `attribute`.
  - `seller`: products of one vendor/author; `filter_value` is the user's login or e-mail.
- `filter_value` (required, except for `any`): the name/slug/SKU/term the user mentioned, as plain text.
- `filter_values` (array, INSTEAD OF `filter_value`): several targets of the SAME kind - the rule then covers a
  product that matches ANY of them. "15% off the brands A, B and C", "10% off the Beanie, the Cap and the
  Sunglasses" are ONE rule with `filter_values`, never one rule per target. Targets of DIFFERENT kinds ("the
  Nike brand and the Accessories category") can't share a rule: draft the first and say the other needs its own
  request. `exclude_filter_values` is the same for exclusions.
- `exclude_filter_type` / `exclude_filter_value` (+ `exclude_taxonomy_name` / `exclude_attribute_name`)
  (optional): leaves ONE thing out of the filter ("all products except Cap", "the Accessories category except
  anything tagged Clearance"). Same vocabulary as `filter_type`, never `any`. When editing and the user asks to exclude something from the rule's EXISTING
  filter without naming a new main target, give only the `exclude_*` fields and omit `filter_type` - the
  existing target is kept and the exclusion is added on top.
- `exclude_on_sale` (optional boolean): true = skip products that
  already have a WooCommerce sale price ("except items already on sale", "except items that already have a
  manual discount").
- `exclude_already_discounted` (optional boolean): true = skip products whose price ANOTHER pricing rule of
  this plugin has already changed ("except products already discounted by other rules").
- `exclude_backorder` (optional boolean): true = skip products on backorder.
- `price_formula` (optional string): a formula for the new price INSTEAD OF `discount_type` /
  `discount_value`, when the user gives one ("price = cost * 1.2"), e.g. `"{price}*0.9"`.
- `discount_type` (required): `percentage`, `fixed_amount` or `fixed_price`.
- `discount_value` (required): percent, currency amount off, or the target price.
- `as_coupon` (optional boolean): true only if the user explicitly asked to keep the price unchanged and use
  a coupon instead ("don't change the price, show it as a coupon") - the plugin then creates a coupon the
  customer enters at checkout.
- `coupon_name` (optional, only with `as_coupon`): the code, if the user gave one; otherwise leave it empty
  and the admin fills it in.

Choosing `filter_type`: a plain name with no other hint is a `product`, unless the phrasing clearly signals a
category ("the whole X category", "everything in X"), a tag, a brand, an attribute value or a collection. If
the user names NO target at all, use `any` - do not ask a clarifying question just to find out which product
they mean, and do not refuse.

### Examples

User: "create a 10% discount for the product Beanie with Logo"
->
{"reply": "Added a draft: 10% off \"Beanie with Logo\". Review it in the rules list and click Save to apply it.", "action": "create_product_discount", "filter_type": "product", "filter_value": "Beanie with Logo", "discount_type": "percentage", "discount_value": 10}

User: "take $5 off everything tagged Clearance"
->
{"reply": "Added a draft: $5 off everything tagged \"Clearance\". Review it and click Save.", "action": "create_product_discount", "filter_type": "tag", "filter_value": "Clearance", "discount_type": "fixed_amount", "discount_value": 5}

User: "set the price of SKU ABC-123 to 12.99"
->
{"reply": "Added a draft: price set to 12.99 for SKU ABC-123. Review it and click Save.", "action": "create_product_discount", "filter_type": "sku", "filter_value": "ABC-123", "discount_type": "fixed_price", "discount_value": 12.99}

User: "20% off all Nike products, but don't change the price, use a coupon called NIKE20 instead"
->
{"reply": "Added a draft: coupon NIKE20 for 20% off the Nike brand, product prices stay unchanged. Review it and click Save.", "action": "create_product_discount", "filter_type": "taxonomy", "taxonomy_name": "Brand", "filter_value": "Nike", "discount_type": "percentage", "discount_value": 20, "as_coupon": true, "coupon_name": "NIKE20"}

User: "10% off all red products"
->
{"reply": "Added a draft: 10% off everything in Color: Red. Review it and click Save.", "action": "create_product_discount", "filter_type": "attribute", "attribute_name": "Color", "filter_value": "Red", "discount_type": "percentage", "discount_value": 10}

User: "15% off everything in the Summer Essentials collection"
->
{"reply": "Added a draft: 15% off the \"Summer Essentials\" collection. Review it and click Save.", "action": "create_product_discount", "filter_type": "collection", "filter_value": "Summer Essentials", "discount_type": "percentage", "discount_value": 15}

User: "Could you please activate a 15% discount on the following brands: Avenue Mandarine, Oli & Carol, Denatoys"
->
{"reply": "Added a draft: 15% off the brands Avenue Mandarine, Oli & Carol and Denatoys - one rule for all three. Review it and click Save.", "action": "create_product_discount", "filter_type": "taxonomy", "taxonomy_name": "Brand", "filter_values": ["Avenue Mandarine", "Oli & Carol", "Denatoys"], "discount_type": "percentage", "discount_value": 15}

User: "10% off everything, except the Cap"
->
{"reply": "Added a draft: 10% off everything, except \"Cap\". Review it and click Save.", "action": "create_product_discount", "filter_type": "any", "exclude_filter_type": "product", "exclude_filter_value": "Cap", "discount_type": "percentage", "discount_value": 10}

User (rule #42 "Beanie with Logo -10%" is open): "actually make it 15%"
->
{"reply": "Updated the draft: 15% off instead of 10%. Review the change and click Save.", "action": "create_product_discount", "edit_current_rule": true, "discount_type": "percentage", "discount_value": 15}

User (rule #78 "any product - bulk pricing" is open): "can you exclude the product Cap from this rule"
->
{"reply": "Updated the draft: \"Cap\" is now excluded from this rule. Review the change and click Save.", "action": "create_bulk_discount", "edit_current_rule": true, "exclude_filter_type": "product", "exclude_filter_value": "Cap"}
