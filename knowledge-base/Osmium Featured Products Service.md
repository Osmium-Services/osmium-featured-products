# Osmium Featured Products service

A hand-picked list of up to five featured products, in an order set by drag and drop. Repo
`Osmium-Services/osmium-featured-products`, v0.2.2<!--v:osmium-featured-products--> (crown icon), category shop. Extracted 2026-10-10 from core's Best
Sellers, renamed because the list is curated, not data-driven. See [[Core and Services]].

## What it does

- **Admin** (top-level Featured Products item): five slots to fill, reorder and clear; a product picker listing only
  products that are available, priced, not deleted, and have a live page and an uploaded thumbnail. A slot that has gone
  bad is marked (Unavailable, Deleted, No price, No page, No image).
- **Edit lock kept**, because an admin with several users needs it: opening a slot locks the page for others and
  snapshots the table; Save releases it, Restore copies the snapshot back; 30 minutes idle releases the lock. The service
  registers its tables with core through `AdminResourceLock::register()`. Restore refuses when the list was empty at the
  start of the edit.
- **Dashboard card** `Featured products` when a slot is empty or broken (core's sidebar badges are gone: service menu items
  cannot show a badge).
- **Theme hook `featured.products`:** the service returns `['productIds' => [...]]` in display order; core's
  `shop/controllers/featured-products.php` builds the product cards and gives the theme `featuredProducts`. A theme includes
  that controller where it wants the block.
- Tables `featured_products`, `featured_products_lock`, `featured_products_snapshot`.

## Notes

The picker's inline `onclick` was replaced with `data-call`, because the CSP blocks inline handlers. Dev data from before the
move (`osm_productbestsellers*`) was left in the dev database.

## Not tested

A real theme including the controller, and an install from the Store with two admins editing at once.
