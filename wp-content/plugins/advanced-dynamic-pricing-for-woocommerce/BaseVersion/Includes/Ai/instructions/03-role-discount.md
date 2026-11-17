## Role discount (`create_role_discount`)

A discount that only applies to customers belonging to a specific account role/group ("wholesale customers",
"subscriber", a custom VIP role) - not to everyone.

Fields:
- `role_name` (required unless `role_rows` is given): the role/group as the user described it ("wholesale customer", "editor"). It is
  matched against the site's registered roles on the backend - you don't need the exact slug.
- `discount_type` / `discount_value` (required unless `role_rows` is given): same as in product discounts.
- `filter_type` / `filter_value` (optional): limits the role discount to one filter target, see filter_type.
  Omit `filter_type` entirely for a store-wide role discount.
- `as_coupon` / `coupon_name` (optional): same as in product discounts.
- `role_rows` (optional array, INSTEAD OF `role_name` / `discount_type` / `discount_value`): several role
  discounts in ONE rule, each `{"roles": [...], "discount_type": ..., "discount_value": ...}` - "Subscribers
  5%, Editors and Shop managers $3 off". Use it whenever the user lists different discounts for different
  roles; do not draft one rule per role.
- `skip_bulk_for_roles` (optional boolean): true = when a role row matches, the rule's bulk tiers are not
  applied on top of it.

### Examples

User: "give wholesale customers 15% off everything"
->
{"reply": "Added a draft store-wide 15% discount for the wholesale customer role. Review it in the rules list and click Save to apply it.", "action": "create_role_discount", "role_name": "wholesale customer", "discount_type": "percentage", "discount_value": 15}

User: "$20 off the Accessories category for the Customer role"
->
{"reply": "Added a draft: $20 off the \"Accessories\" category for the Customer role. Review it and click Save.", "action": "create_role_discount", "role_name": "Customer", "filter_type": "category", "filter_value": "Accessories", "discount_type": "fixed_amount", "discount_value": 20}
