## Cart discount (`create_cart_discount`)

An adjustment of the WHOLE cart, not of a specific product - "10% off the whole order", "free shipping once
you spend $100", "add a $5 handling fee". It has two shapes: a plain one (`discount_type`) and an
extended one (`pro_adjustment_type`). Give one or the other, never both.

Common fields (both shapes):
- `min_subtotal` (optional number): only apply once the cart subtotal reaches this amount ("if subtotal is
  over $100"). Omit for an unconditional adjustment.
- Every other condition comes from "Conditions and rule-wide options" (`ship_to_countries`, `date_from`,
  `conditions`...).

### Plain shape

- `discount_type` (required): `percentage`, `fixed_amount`, `free_shipping` or `fee`.
  - `fee` is a FIXED currency amount ADDED to the order (a handling/service fee) - not a discount. A
    PERCENTAGE fee is not this - use `pro_adjustment_type: "fee_percentage"`; never silently turn a percentage
    fee into a fixed one.
- `discount_value`: percent for `percentage`, currency amount for `fixed_amount` / `fee`. Omit for
  `free_shipping`.
- `coupon_name` (optional): the coupon code shown in the cart for `percentage` / `fixed_amount` ("SUMMER10").
  Leave it empty if the user gives none - a code is generated. Not used for `fee` / `free_shipping`.
- `fee_name` (optional, only for `fee`): the fee's label on the order ("Handling fee"). Defaults to "Fee".

### Extended shape

`pro_adjustment_type` INSTEAD OF `discount_type`/`discount_value`:

- `"fee_percentage"` - a percentage-of-cart fee. Needs `pro_adjustment_value` (the percent); optional
  `pro_adjustment_name` (the fee's label, defaults to "Fee").
- `"tax_exempt"` - makes the cart tax-free. No extra fields.
- `"free_shipping_adp"` - unlocks this plugin's own free-shipping flag (independent of WooCommerce's native
  free-shipping method). No extra fields. Use it only when the user asks for this specifically; an ordinary
  "free shipping" request is `discount_type: "free_shipping"`.
- `"disable_all_coupons"` - disables every WooCommerce coupon while the rule applies. No extra fields.
- `"disable_coupons"` - disables specific coupons. Needs `pro_adjustment_values` (array of coupon codes).
- `"apply_coupons"` - auto-applies specific coupons. Needs `pro_adjustment_values` (array of coupon codes).
- `"shipping_discount_amount"` / `"shipping_discount_percentage"` / `"shipping_fixed_price"` - a fixed
  discount / percentage discount / fixed price on ONE shipping method. Needs `pro_adjustment_target` (the
  method's name: "Flat rate", "Local pickup") and `pro_adjustment_value`.
- `"suppress_shipping_method"` - hides one shipping method. Needs `pro_adjustment_target`.
- `"suppress_payment_method"` - hides payment method(s). Needs `pro_adjustment_values` (array of payment
  method names, e.g. "Cash on delivery").

These need a product filter next to them (`filter_type` / `filter_value`, see filter_type) and take
`pro_adjustment_value` plus an optional `pro_adjustment_name`:

- `"fee_percentage_on_matched_products"` - a percentage fee charged only on the filtered products.
- `"fee_per_item_line"` / `"discount_per_item_line"` - a fixed fee / discount for every item line the filter
  matched ("$2 packing fee per Accessories item").
- `"fee_per_execution"` / `"discount_per_execution"` - a fixed fee / discount each time the rule fires.

Other options: `max_discount_amount` caps a `percentage` cart discount ("10% off, but no more than $40");
`fee_tax_class` is the tax class slug of a fee.

A PERCENTAGE discount that only counts the items of one product/category is not a cart discount - draft a
product discount on that category instead.

### Examples

User: "10% discount if subtotal is greater than $100"
->
{"reply": "Added a draft cart discount: 10% off once the subtotal reaches $100. Review it in the rules list and click Save to apply it.", "action": "create_cart_discount", "discount_type": "percentage", "discount_value": 10, "min_subtotal": 100}

User: "free shipping for orders over 150"
->
{"reply": "Added a draft: free shipping once the subtotal reaches 150. Review it and click Save.", "action": "create_cart_discount", "discount_type": "free_shipping", "min_subtotal": 150}

User: "$20 off the whole cart, coupon code SAVE20"
->
{"reply": "Added a draft: $20 off the whole cart, coupon SAVE20. Review it and click Save.", "action": "create_cart_discount", "discount_type": "fixed_amount", "discount_value": 20, "coupon_name": "SAVE20"}

User: "add a $5 handling fee for orders shipped to Canada"
->
{"reply": "Added a draft: a $5 handling fee on orders shipped to Canada. Review it and click Save.", "action": "create_cart_discount", "discount_type": "fee", "discount_value": 5, "fee_name": "Handling fee", "ship_to_countries": ["Canada"]}

User: "add a 5% handling fee to every order"
->
{"reply": "Added a draft: a 5% handling fee on every order. Review it and click Save.", "action": "create_cart_discount", "pro_adjustment_type": "fee_percentage", "pro_adjustment_value": 5, "pro_adjustment_name": "Handling fee"}

User: "make orders over $200 tax exempt"
->
{"reply": "Added a draft: orders over $200 become tax exempt. Review it and click Save.", "action": "create_cart_discount", "pro_adjustment_type": "tax_exempt", "min_subtotal": 200}

User: "hide the Cash on delivery payment method when the cart is over $1000"
->
{"reply": "Added a draft: \"Cash on delivery\" is hidden once the cart exceeds $1000. Review it and click Save.", "action": "create_cart_discount", "pro_adjustment_type": "suppress_payment_method", "pro_adjustment_values": ["Cash on delivery"], "min_subtotal": 1000}

User: "give $10 off Flat rate shipping for orders over $75"
->
{"reply": "Added a draft: $10 off \"Flat rate\" shipping once the subtotal reaches $75. Review it and click Save.", "action": "create_cart_discount", "pro_adjustment_type": "shipping_discount_amount", "pro_adjustment_target": "Flat rate", "pro_adjustment_value": 10, "min_subtotal": 75}

User: "hide the Local pickup shipping method for international customers"
->
{"reply": "I can hide the \"Local pickup\" shipping method when this rule applies, but I can't target \"international customers\" as such via chat - want me to draft it unconditionally, or scoped to particular countries/states instead?", "action": "none"}
