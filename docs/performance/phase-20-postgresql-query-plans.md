# Phase 20 — PostgreSQL catalog query-plan baseline

This benchmark records PostgreSQL execution plans for the catalog index and public
menu read paths. It is deliberately a test-only workload: it creates synthetic
businesses, menus, categories, and products through the isolated test database.

## Dataset

The feature test creates:

- 20 businesses and 20 published menus
- 400 categories (20 per menu)
- 60,000 products (150 per category)
- A mix of draft/published products and available/unavailable products

The test prints a `POSTGRES_CATALOG_QUERY_PLANS` JSON document containing the
dataset dimensions and, for each query, PostgreSQL's root plan node, execution
and planning times, actual root rows, shared buffer hits/reads, index names,
and node types.

## Queries measured

- Public active categories filtered by an `EXISTS` check for published and
  available products.
- Public product eager-load query with and without an explicit `menu_id`
  predicate. The category IDs are already scoped to one menu; the explicit
  predicate is measured as a defensive selectivity comparison.
- Catalog product listing ordered by category order, product order, and ID.
- Catalog substring search using the production query shape (`LOWER(name) LIKE '%term%'`), scoped to the selected menu and bounded to one page.
- Category product-count subqueries with and without an explicit `menu_id`
  predicate.

The query plans are observations, not performance assertions. Wall-clock
thresholds are intentionally not asserted in CI because shared runner load and
cache state make those values noisy. Compare plans, buffers, rows, and selected
indexes before proposing schema changes. The search plan is included to determine whether the current substring search needs a PostgreSQL-specific index or a different search strategy; no index is assumed in advance.

## Run

Run the benchmark against the dedicated PostgreSQL test database only:

```sh
./scripts/test-docker-pgsql.sh --filter=PostgresCatalogQueryPlanTest
```

The script forwards PHPUnit arguments after verifying that the target database
is not the development database. Run the full isolated PostgreSQL suite by
omitting the filter.

CI runs the same test in its ephemeral `websbz_test` PostgreSQL service.
SQLite skips this benchmark because SQLite cannot provide PostgreSQL query
plans or buffer statistics.


## Initial CI baseline (2026-11-16)

PostgreSQL 17, 60,000 synthetic products, warm-cache single-run measurements:

| Query shape | Without explicit menu scope | With explicit menu scope |
|---|---:|---:|
| Public product read | 7.350 ms, 1,094 shared hits, sequential scan | 1.095 ms, 78 shared hits, index scan |
| Category product counts | 68.112 ms, 21,823 shared hits, sequential scan in correlated aggregate | 0.672 ms, 227 shared hits, existing composite index |

The measurements justify adding the explicit `products.menu_id` predicate to the
public menu's existence/eager-load queries and catalog category product counts.
The result sets remain tenant/menu constrained; automated feature tests assert
the menu predicate is present. These figures are a CI baseline, not production
latency guarantees. No new index is justified by this result.


## Public-menu response cache

The public menu payload is cached for five minutes using the framework's configured
cache store. The cache key is namespaced by the internal menu ID, while every
request still resolves the active business and published menu from PostgreSQL
before reading cached content. This keeps suspended businesses and unpublished
menus inaccessible even if an old payload remains in cache.

Successful writes invalidate the affected menu cache after persistence:
- Category create, update, delete, and reorder.
- Product create, update, delete, and reorder.
- Menu publish and unpublish.
- Business profile updates, because the public payload includes the business name.

The cache stores only the public Inertia payload, not Eloquent model instances or
administrative fields. A feature test verifies that a cache hit skips category
and product reads and that a product update invalidates the payload immediately.

The cache uses the configured Laravel cache backend. Redis is not forced as a
new dependency; production should select a shared cache backend before running
multiple application replicas. Until then, each replica's cache remains local
to its configured store.


## Billing history query budget

The billing page now computes `hasPendingReview` as a correlated `EXISTS` expression
on the subscription query and performs one bounded query for the latest ten payment
records (with their target plans). This avoids eagerly loading the complete payment
history and then querying the same history again for display.

A feature regression creates twelve payment rows and asserts:
- Exactly two SQL statements reference the payments table: the subscription
  `EXISTS` and the bounded latest-ten query.
- The page still reports a pending review when the newest payment is pending.
- Only ten recent payment records are returned.


## Catalog substring-search baseline (PostgreSQL 17 CI)

On the 60,000-product synthetic dataset, the menu-scoped search query
(`LOWER(name) LIKE '%product 12%'`, category ordering, limit 20) completed in
0.766 ms in the warm-cache CI run. PostgreSQL returned 20 rows, recorded 415
shared buffer hits and no shared reads. The plan used the existing
`products_menu_name_idx` for the menu-scoped scan and the category key index
for category ordering; it did not require a new trigram index for this measured
workload.

This is one CI sample with 3,000 products in the target menu, not a production
latency guarantee. Revisit trigram/full-text search only if production query
monitoring shows substring search becoming a material bottleneck at larger
per-menu cardinalities.

## Safety

Do not point this test at the development or production database. The test uses
Laravel's `RefreshDatabase` trait and inserts 60,000 synthetic products.
The Docker script verifies that its configured test database name differs
from the running application's development database before invoking Artisan.
No production migration or index is introduced by this benchmark.
