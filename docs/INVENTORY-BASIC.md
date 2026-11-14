# NEXORA — Basic inventory scope

This first phase adds informational quantity/availability to catalog products. It is not a stock ledger or ERP inventory module.

## Data

- `products.quantity_available` is stored as `DECIMAL(14,3)`.
- New products start at zero.
- Negative quantities are rejected.
- The current interface shows a quantity with three decimal positions.

## Authorization

- `inventory.view` controls whether a user sees the quantity.
- `inventory.edit_quantity` controls whether a user can adjust the quantity.
- Quantity updates use a dedicated PATCH route and modify only `quantity_available`.
- The route does not require or grant `products.edit`.
- The default **Auxiliar de inventario** role can view products and update quantities, but cannot see or edit product prices or descriptions.
- The product editor's protected fields and existing Permission Engine rules remain in place.

## Out of scope

No purchase orders, stock movements, transfers, warehouses, costing, valuation, reserved quantities, reorder levels or financial inventory reporting are added. Those require a separate inventory specification.
