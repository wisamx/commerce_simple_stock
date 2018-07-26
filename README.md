# Commerce Simple Stock

The module implements a simple stock management for Drupal 8 Commerce 2.

Usage:
- Add a field_stock to the variant type.
- You can add a view for showing Product Variants each with its stock value

Features:
- Hide the add to cart form when stock is 0 and show "Out of stock".
- Prevent adding the product to cart when its not available.
- Prevent checking out order items when its not available.
- Decrease product variant stock when an order is placed.
- Update product variant stock when an order item is updated/deleted.
- Provide an Inventory Control page for Bulk Stock updates.
