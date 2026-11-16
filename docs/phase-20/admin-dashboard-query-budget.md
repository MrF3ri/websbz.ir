# Phase 20 — Platform Admin dashboard query budget

`AdminDashboardQueryBudgetTest` measures SELECT statements emitted by the `/admin` dashboard with one payment and again after the same tenant subscription has 31 payments.

The regression gate requires the higher-volume request to use no more than one additional SELECT query. This is a query-count guard against N+1 regressions in the dashboard's recent-payment relation loading; it is not a latency benchmark or representative production-load certification.

The dashboard currently performs bounded aggregate metric queries and eager-loads the business and target plan for its recent payment list. The test is intended to preserve that bounded query shape as payment history grows.