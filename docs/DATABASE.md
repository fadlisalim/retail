# Database structure

Rekasurya Store uses ~65 tables. Money is always `DECIMAL(15,2)` (never float),
weights are integers in **grams**, dimensions are `DECIMAL` centimetres. Timestamps
and soft deletes are used where relevant. Foreign keys, unique constraints, and
indexes are defined in `database/migrations/`.

Statuses are stored as strings (not DB enums) so they stay easy to extend; the
canonical list + labels live in `app/Enums/`.

## Domains

### Identity & access
| Table | Purpose |
|-------|---------|
| `users` | accounts (customers + staff via `is_staff`), WhatsApp, activity |
| `customer_profiles` | company, NPWP, customer type, term-payment approval |
| `customer_addresses` | multiple addresses per customer, default flag, coordinates |
| `regions` | self-referencing Indonesian regions (province→city→district→subdistrict) |
| `roles`, `permissions`, `role_user`, `permission_role` | RBAC (7 roles, granular perms) |
| `login_activities`, `audit_logs` | login log + privileged-action audit trail |

### Catalog
| Table | Purpose |
|-------|---------|
| `brands` | brands (incl. internal "Bezvolt") |
| `categories` + `category_slug_histories` | unlimited nested tree; old-slug redirects |
| `attribute_groups`, `attributes`, `attribute_values`, `attribute_group_category` | dynamic per-category specs |
| `products` + `product_slug_histories` | core product incl. pricing, flags, SEO, dimensions |
| `product_variants` | own SKU/price/stock/weight/dimensions/image |
| `product_images`, `product_documents`, `product_videos` | media & datasheets/manuals/certs |
| `product_attribute_values` | per-product dynamic attribute values (text or number) |
| `product_bundle_items` | PLTS package contents (component, qty, replaceable) |
| `product_condition_details` | surplus/open-box/used disclosure (reason, defects, warranty…) |

### Inventory (ledger)
| Table | Purpose |
|-------|---------|
| `warehouses` | one or more warehouses (default flag) |
| `warehouse_stocks` | per-(warehouse,product,variant) available/reserved/damaged/returned/incoming |
| `stock_movements` | immutable signed ledger with reason + reference (morph) |
| `stock_reservations` | temporary holds created at payment, auto-expire |

### Cart / wishlist / comparison
`carts`, `cart_items`, `wishlists`, `wishlist_items`, `product_comparisons`,
`product_views`, `recently_viewed_products` — all keyed by `user_id` **or** a guest
`session_token`, enabling merge-on-login.

### Orders & fulfilment
| Table | Purpose |
|-------|---------|
| `orders` | `order_number` (readable) + `public_token` (UUID, used in public URLs); full money breakdown; idempotency key |
| `order_items` | immutable line snapshots (sku/name/price/qty/tax) |
| `order_addresses` | shipping/billing snapshot |
| `order_status_histories` | who/when/notes/tracking/proof per transition |
| `payments`, `payment_transactions`, `payment_webhook_logs` | adapter-based payments + signed webhook log |
| `shipments`, `shipment_packages` | shipment + per-package billable weights |
| `invoices` | `invoice_number` + `public_token`, company/customer snapshots |

### Commerce extras
`coupons`, `coupon_usages`, `promotions`; `reviews`, `review_media`,
`review_helpfulness`, `review_reports`; `product_questions`, `product_answers`;
`quotations`, `quotation_items`, `quotation_revisions`, `quotation_attachments`;
`returns`, `return_items`.

### CMS & platform
`banners`, `pages`, `articles`, `faqs`, `newsletter_subscribers`, `settings`,
`notifications` (Laravel database channel).

## Key relationships (Eloquent)

- `Product` → `belongsTo` Category, Brand; `hasMany` variants, images, documents,
  videos, attributeValues, bundleItems, reviews, questions, warehouseStocks;
  `hasOne` conditionDetail.
- `Order` → `hasMany` items, addresses, statusHistories, payments, shipments,
  reservations; `hasOne` invoice, shippingAddress; `belongsTo` user.
- `User` → `hasMany` orders, reviews, quotations, addresses; `hasOne` profile,
  wishlist; `belongsToMany` roles (each role `belongsToMany` permissions).
- `Quotation` → `hasMany` items, revisions, attachments; `belongsTo` handler,
  convertedOrder.
- `StockMovement` → `morphTo` reference (Order/Return/etc.).

## Integrity rules enforced in code

- **Stock never negative** — `StockService` guards every decrement in a transaction
  with row locking; stock is a ledger, not a bare number.
- **Server-authoritative money** — `CartCalculator` recomputes every figure from
  live product data at checkout.
- **One review per purchased item** — unique `order_item_id` on `reviews`.
- **Non-guessable public URLs** — orders/invoices/quotations expose UUID tokens, not IDs.
