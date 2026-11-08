# BeautyPals — A-18 / UC04 Product Search & Filter

> **Implementation mapping verified against user-supplied Laravel migrations (2026_01_01_000001–000003).**
> This branch is not merged or remotely pushed merely by applying this patch. Run tests on the Windows Docker clone before committing.

## 1. Actual application schema (not the obsolete MVP v2 SQL)

| Entity | Actual table and columns | Rule |
| --- | --- | --- |
| Product | `products(id, vendor_id, brand_id, category_id, name, slug, description, status)` | `status = approved` required |
| Store | `vendors(id, store_name, status)` | `status = approved` required |
| Variant / pricing | `skus(id, product_id, price, is_active)` | Active SKU supplies price in VND; product has no price column |
| Stock | `batches(sku_id, status, exp_date, qty_on_hand, qty_reserved)` | `status=active`, date not passed, unreserved qty positive |
| Brand | `brands(id, name)` | Optional, since `products.brand_id` nullable; no `is_active` |
| Category | `categories(id, parent_id, name, slug)` | Category descendants supported; no `is_active` |
| Image | `product_images(product_id, path, sort)` | Choose lowest sort, then lowest id |

**Do not** create `product_variants`, `vendor_listings`, or `inventory_batches` on the live project. These table names belong to a **different** reference schema and caused the prior 9/10 test failures.

## 2. API contract

**GET** `/api/catalog/products`, public read-only endpoint with throttle 60/minute.

| Parameter | Meaning | Default |
| --- | --- | --- |
| `q` | Partial name, description, slug, brand or category; `%`, `_`, `!` literal | Empty |
| `category_id` | Category ID filter, optionally with descendants | — |
| `include_descendants` | `0` = direct category only, `1` = include descendants | `1` |
| `brand_id` | Exact brand ID | — |
| `min_price` | Minimum matching active SKU's price (VND) | — |
| `max_price` | Maximum matching active SKU's price (VND) | — |
| `in_stock` | `1` requires eligible SKU with valid, free stock | `0` |
| `sort` | `newest`, `price_asc`, `price_desc`, `name_asc`, `name_desc` | `newest` |
| `per_page` | Result size from 1 to 48 | 12 |
| `page` | Page number 1–10000 | 1 |

Response uses Laravel paginator JSON: `data`, `total`, `per_page`, `current_page`, `last_page`, etc. The items contain `id`, `name`, `slug`, `description`, `vendor_id`, `vendor_name`, `brand_id`, `brand_name`, `category_id`, `category_name`, `price`, `in_stock`, `image_path`, `created_at`.

**Price semantics:** lowest active SKU price *within matching price criteria*. If `in_stock=1`, its price must belong to an in-stock SKU. In the default catalog, out-of-stock SKUs still contribute to the displayed starting price; checkout MUST recheck exact SKU price and inventory before order confirmation. `in_stock` is a discovery hint, not a stock reservation.

**Guest authorization:** guests may read catalog but cannot see drafts, waiting/rejected products, or suspended/unapproved vendors. Product-level visibility is filtered in the SQL query; no login bypass may expose other statuses. Endpoint must not expose user account data, tax codes, license files, purchase cost or reserved quantities.

## 3. Acceptance criteria and regression tests

- [ ] Request validation returns 422 for unsupported sort, invalid IDs, price inversions, negative values and excessive pagination.
- [ ] `approved` product + `approved` store + at least one active SKU is discoverable for a guest.
- [ ] `draft`, `pending`, `rejected`, `archived` products cannot appear.
- [ ] `pending`, `rejected`, `suspended` stores cannot expose products.
- [ ] No active SKUs => product is hidden; multiple SKUs => one product row.
- [ ] Price is from an active SKU and price filtering matches SKU prices, not a nonexistent `products.price`.
- [ ] With `in_stock=1`, expired, recalled, fully reserved and sold-out batches are excluded.
- [ ] Optional brand is allowed; brand filter and category descendant filter work.
- [ ] Keyword escaping prevents `%`/`_` wildcard expansion and SQL injection; query uses parameter binding.
- [ ] Stable pagination includes `id` tie-breaker.
- [ ] Full prior suite (39 tests before A18) still passes, together with new UC04 tests.
- [ ] Test database is isolated SQLite; no `migrate:fresh` or `--seed` on live MySQL as part of tests.
- [ ] Local MySQL API smoke test returns 200 after seeding dev catalog and verifies business requirements.
- [ ] Security review: GET is read-only, sorts allowlisted, rate limit active, output fields explicitly selected.

## 4. Apply fix on existing A-18 feature branch

This is a **patch for an already-created branch**, not a second branch installer. Download/extract the patch outside the Git clone. In Git Bash:

```bash
bash /d/Project_IS/BeautyPals_A18_RealSchema_Fix/apply-fix.sh /d/Project_IS/BeautyPals-main-project
```

The patch checks that the current branch is `feature/a18-product-search-filter`, verifies originals match the distributed A18 kit, backs them up OUTSIDE the repository, and replaces only `ProductSearchService.php`, `Uc04ProductSearchTest.php` and `docs/UC04_ACCEPTANCE.md`. It does not modify `routes/web.php`, database migrations, seeders or Docker settings.

```bash
cd /d/Project_IS/BeautyPals-main-project
docker compose up -d
docker compose exec app php artisan route:list --path=api/catalog
docker compose exec app php artisan test --filter=Uc04ProductSearchTest
docker compose exec app php artisan test
git diff --check
git status
```

If tests fail, **stop**, capture the first full error and check the code/schema before pushing. Do not remove approval filters to silence a test failure.

For a local dev MySQL smoke test (not using the testing SQLite database), make sure demo fixtures are intentionally seeded, then check:

```text
http://localhost:8081/api/catalog/products?q=Innisfree
http://localhost:8081/api/catalog/products?min_price=150000&max_price=500000&in_stock=1
http://localhost:8081/api/catalog/products?sort=price_asc&per_page=12&page=1
```

After successful verification, stage *only reviewed files*, commit, and push for a PR targeting `main`. Preserve `.env` and all real data. Confirm HTTP behavior and SQL query plans on MySQL, because SQLite and MySQL can differ in collations and case sensitivity, especially for Vietnamese text.
