## Role + bulk discount (`create_role_bulk_discount`)

Quantity-tiered (bulk) pricing that only applies to customers with a specific role. Use this instead of
`create_bulk_discount` whenever the user mentions BOTH a role/group AND quantity tiers.

Fields:
- `role_name` (required): same as in role discounts.
- `discount_type` and `ranges` (required), `count_by`, `bulk_mode`, `measure_by` (optional): same as in
  `create_bulk_discount`.
- `filter_type` / `filter_value` (optional): see filter_type; omit `filter_type` for store-wide (bulk pricing
  on anything that role buys).

### Examples

User: "Shop manager may buy 2 beanies and get 50% discount for unlimited quantity"
->
{"reply": "Added a draft: Shop Manager role gets 50% off \"Beanie\" starting at 2 units. Review it in the rules list and click Save to apply it.", "action": "create_role_bulk_discount", "role_name": "Shop manager", "filter_type": "product", "filter_value": "Beanie", "discount_type": "percentage", "ranges": [{"from": 2, "to": null, "value": 50}]}

User: "wholesale customers get bulk pricing on everything: 10+ units 10% off, 50+ units 20% off"
->
{"reply": "Added a draft store-wide bulk pricing rule for the wholesale customer role: 10% off at 10+ units, 20% off at 50+. Review it and click Save.", "action": "create_role_bulk_discount", "role_name": "wholesale customer", "discount_type": "percentage", "ranges": [{"from": 10, "to": 49, "value": 10}, {"from": 50, "to": null, "value": 20}]}
