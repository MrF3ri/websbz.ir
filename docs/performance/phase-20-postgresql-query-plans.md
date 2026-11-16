# Phase 20 — PostgreSQL catalog query-plan baseline

This benchmark records PostgreSQL execution plans for the catalog index and public
menu read paths. It is deliberately a test-only workload: it creates synthetic
businesses, menus, categories, and products through the isolated test database.

## Dataset

The feature test creates:

- 20 businesses and 20 published menus
- 400 categories (20 per menu)
- 120,000 products (150 per category)
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
- Category product-count subqueries with and without an explicit `menu_id`
  predicate.

The query plans are observations, not performance assertions. Wall-clock
thresholds are intentionally not asserted in CI because shared runner load and
cache state make those values noisy. Compare plans, buffers, rows, and selected
indexes before proposing schema changes.

## Run

Run against the dedicated PostgreSQL test database only:

```sh
./scripts/test-docker-pgsql.sh --filter=PostgresCatalogQueryPlanTest
```

The existing script currently forwards no arguments to PHPUnit. Until it is
extended, run the full isolated PostgreSQL test suite using:

```sh
./scripts/test-docker-pgsql.sh
```

CI runs the same test in its ephemeral `websbz_test` PostgreSQL service.
SQLite skips this benchmark because SQLite cannot provide PostgreSQL query
plans or buffer statistics.

## Safety

Do not point this test at the development or production database. The test uses
Laravel's `RefreshDatabase` trait and inserts 120,000 synthetic products.
The Docker script verifies that its configured test database name differs
from the running application's development database before invoking Artisan.
No production migration or index is introduced by this benchmark.
