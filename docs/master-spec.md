# ERRATA — CURRENT STATE OVERRIDES (read first; these supersede conflicting text below)
Date: 2026-11-15. Where this block conflicts with the original spec, report the conflict; do not silently choose.

1. Git: Work branches are created from `main`, named feat/phase-N-name. `chore/phase-1-foundation` and `feat/phase-7-catalog` are obsolete (do not delete without approval). Local main == origin/main; no uncommitted local code exists.
2. Environment (verified locally): PHP 8.5.12, Composer 2.11.2, Node 26.13.1, npm 12.2.2, Docker 29.9.3, Compose 5.7.1. CI uses Node 22 and PHP 8.5; align Node in the baseline task.
3. Part 1.5 is historical. Verified present: Business/BusinessMember, CurrentBusiness (scoped), TenantMiddleware, Fortify auth (2FA, passkeys), RTL dashboard base, Plan/PlanFeature/Subscription, EntitlementService + EnforcementService (EnforcementService decides, EntitlementService reads the snapshot), docs/architecture/entitlement-enforcement-contract.md, partial Phase 7 billing (Payment, SubscriptionEvent, StartTrial/InitiatePayment/ProcessPaymentCallback actions; no routes/UI). NOT present: Docker/Compose (never committed), Catalog/Menu/Branch/Media/PublicMenu/QR, docs/architecture.md, docs/roadmap.md, docs/decisions/, BusinessFactory, users.public_id.
4. Quality gates verified locally (SQLite in-memory): 85 tests pass, PHPStan level 7 OK (needs --memory-limit=1G), Pint pass, vue-tsc OK, vite build OK. SQLite cannot prove lockForUpdate/concurrency; PostgreSQL tests are required for that.
5. Phase numbering is unchanged: Phase 7 = Subscription Lifecycle & Billing, Phase 8 = Menu & Catalog. Work order: Baseline stabilization (Docker + PostgreSQL, CI with PostgreSQL, BusinessFactory, tenancy IDOR tests, users.public_id, Inertia entitlement shared props, docs/ADRs) -> Phase 8 -> finish Phase 7.
6. Decisions: users.public_id is required (ULID, never used for authorization). Catalog must use composite FKs to prevent cross-business relations. Template-count entitlements are deferred to Phase 11 (new feature key + ADR there). Small technical decisions already made (-1 = unlimited, features_snapshot/plan_version columns, EnforcementService, payments.target_plan_id) are accepted and need ADRs. Known cleanup: replace the hardcoded form.post('/onboarding') with the generated route helper; dashboard placeholder stats must become real data later.
7. Payment method [APPROVED by owner 2026-11-15; ADR required]: the first provider is MANUAL CARD-TO-CARD. A business owner renews by transferring to the platform's registered card (stored in admin-editable platform settings, never hardcoded), then uploads a receipt in the system. The platform admin reviews the receipt and approves or rejects it; approval renews the subscription. Implemented as a provider behind PaymentGatewayInterface; an online gateway may be added later. The spec rule "verify server-to-server" applies to online gateways; for the manual provider the equivalent control is authenticated, audited admin approval recorded in subscription_events, idempotent and executed under a DB row lock. Receipts go to private storage (not the public disk) with strict validation, a unique bank tracking number, and access only for the platform admin and the owning business. Payment states: pending_review, approved, rejected.
8. Actors: (a) platform admin = the owner only (single admin; no other operators planned), (b) business owner/staff (BusinessRole inside a tenant), (c) anonymous public customer who may only view the public menu and has no account. Platform admin is NOT a BusinessRole: implement as a simple non-fillable flag (e.g. users.is_platform_admin, default false) granted only via an artisan command/seeder, never via UI or registration, enforced through one Gate/middleware on /admin routes so it can later be replaced by a role table without touching callers.
9. Communication: all reports and explanations to the owner are in Persian.
10. Workflow: the agent works on a branch created from main and opens a PR; it NEVER merges. CI results plus the owner's local run are the verification gate. The owner merges after the Lead's review.
11. Trial: exactly 72 hours (3 days), available on all three plans. The owner selects the plan during onboarding; the subscription snapshot is taken from the selected plan. After the trial, payment is required to continue.
12. Dates: every date shown to users must be Jalali (Persian calendar, Asia/Tehran for display). Every date stored in the database, sent in APIs/Inertia props, or used in logic is Gregorian ISO 8601 in UTC. Conversion happens only at the presentation edge through ONE central helper per side (Vue composable using Intl 'fa-IR-u-ca-persian'; PHP helper using intl persian calendar). Date inputs show a Jalali picker but submit Gregorian ISO values; the server validates only Gregorian. Leap years must be correct and covered by tests (e.g. Jalali 1403 has Esfand 30, 1404 does not).




# MASTER PROJECT EXECUTION PROMPT
## websbz.ir — Multi-Tenant QR Digital Menu SaaS

**Role:** Act as a Principal Software Architect, Senior Laravel Engineer, Senior Vue/TypeScript Engineer, Database Architect, DevOps Engineer, and Application Security Engineer.

**Mission:** Lead the architecture, implementation, testing, documentation, and production preparation of `websbz.ir`, a commercial multi-tenant SaaS platform for cafés and restaurants to create and manage digital menus accessible through QR codes.

You are not being asked to generate a theoretical tutorial or a one-shot code sample. You are responsible for progressing an existing software repository toward a production-ready product, in controlled, verifiable implementation phases.

All explanations, progress reports, architectural discussions, and questions to the user must be in **Persian**. Use English for source code, identifiers, comments where appropriate, commit messages, and technical naming.

---

# PART 1 — PROJECT CONTEXT AND CURRENT STATE

## 1.1 Product overview

The product is a SaaS platform where café and restaurant owners can:

- Register and create a business.
- Manage a digital menu.
- Organize products and categories.
- Manage one or more branches depending on subscription.
- Customize the appearance of their menu according to their subscription.
- Generate and download QR codes.
- Share their menu through a public URL.
- Upgrade or renew their subscription.
- Manage their business through a web-based dashboard.

Customers scan a QR code and view the restaurant's public digital menu without installing an application.

The system must support multiple businesses with strict data isolation.

## 1.2 Existing repository

Repository:

`MrF3ri/websbz.ir`

GitHub repository:

`https://github.com/MrF3ri/websbz.ir`

Current development branch intended:

`chore/phase-1-foundation`

Initial commit:

`4c8046b`

Initial commit message:

`chore(setup): resolve vite native bindings, fix permissions and complete frontend build`

The initial report states that both `main` and `chore/phase-1-foundation` pointed to this commit, but the active branch was `main`.

Do not assume this is still the current state. Inspect Git before making changes.

## 1.3 Existing technical stack

The following technologies are already installed or configured according to the project status report:

| Layer | Technology |
|---|---|
| Backend | Laravel 13.40.0 |
| PHP | 8.5.12 |
| Frontend | Vue 3 |
| Language | TypeScript |
| SPA integration | Inertia.js 3 |
| CSS | Tailwind CSS 4 |
| Authentication foundation | Laravel Fortify / Laravel Vue Starter Kit |
| Database | PostgreSQL |
| Build system | Vite Plus |
| UI components | Reka UI and Starter Kit components |

Existing environment information:

- Operating system: Garuda Linux
- PHP: 8.5.12
- Node.js: 26.10.0
- npm: 12.1.0
- Docker: 29.8.1
- Docker Compose: 5.5.1
- Git: 2.55.0

These versions are historical reported values, not guaranteed current values. Verify them.

## 1.4 Existing database status

The initial report confirms that PostgreSQL connectivity was tested successfully and five initial migrations were executed:

- users
- cache
- jobs
- passkeys
- two-factor authentication columns

Do not reset or destroy the existing database without explicit approval.

## 1.5 Existing architecture status

The project has an initial Laravel Vue Starter Kit structure.

The following have NOT been confirmed as implemented:

- Docker Compose runtime
- Custom Modular Monolith structure
- Business module
- BusinessMember module
- Multi-tenancy
- Tenant resolution
- CurrentBusiness context
- Tenant middleware
- Tenant authorization policies
- Tenant isolation tests
- Product and category modules
- Branch management
- Subscription and payment modules
- Public menu
- QR generation
- Production deployment

The current status must be verified from the actual repository, not inferred from this report.

---

# PART 2 — NON-NEGOTIABLE ARCHITECTURAL DECISIONS

## 2.1 Application architecture

Use:

**Modular Monolith**

Do not introduce Microservices.

The application should be deployed as a coherent Laravel application, while domain responsibilities remain separated into modules.

Recommended domain modules:

- Identity
- Business
- Menu
- Catalog
- Branch
- Media
- Subscription
- Billing
- Domain
- QRCode
- PublicMenu
- Review
- Social
- Notification
- Backup

Do not create every module immediately if it has no implementation responsibility yet.

Build modules incrementally.

## 2.2 Multi-tenancy

Use:

**Shared Database / Shared Schema**

Every business is a tenant.

All tenant-owned records must be associated with the correct business, directly or through a verified ownership relationship.

The application must prevent cross-tenant data access.

Important decisions:

- Internal database primary keys: BIGINT.
- Public identifiers: ULID.
- Public IDs must have unique database constraints.
- Business slugs must be globally unique.
- ULID is not an authorization mechanism.
- Authorization must always be enforced on the server.
- Never trust a `business_id` received from the frontend without verifying membership and permission.

The initial architecture decision is:

**Session-based Tenant Context.**

Use a dedicated `CurrentBusiness` service.

Register it using Laravel's scoped service container lifecycle rather than an application-wide singleton.

The context must be safely reset between requests, jobs, and tests.

Design the system so a user can belong to multiple businesses through a many-to-many membership relationship. The initial onboarding flow may create one business and does not need a full business-switching UI.

## 2.3 Backend conventions

Use:

- Eloquent ORM
- Form Requests for validation
- Policies for authorization
- Services or Actions for business operations
- Database transactions for multi-step writes
- Enums for stable finite states
- Events and listeners where they provide meaningful decoupling
- Queues for asynchronous work when justified

Avoid:

- Fat controllers
- Business logic in Vue components
- Unnecessary Repository Pattern
- Excessive abstraction
- Unnecessary global scopes
- Hardcoded plan limits throughout the codebase
- Duplicated authorization rules

## 2.4 Frontend conventions

Use:

- Vue 3 Composition API
- TypeScript
- Inertia.js
- Tailwind CSS 4
- Reusable components
- Domain-oriented page organization
- Server-side validation
- Proper loading, empty, success, and error states

The administrative dashboard must support Persian and RTL correctly.

Public menus must support Persian, English, and Arabic where enabled by the subscription.

---

# PART 3 — PRODUCT REQUIREMENTS AND COMMERCIAL RULES

These requirements are approved product decisions. Do not silently modify them.

## 3.1 Subscription pricing

All prices are in Iranian Toman.

| Plan | Monthly price |
|---|---:|
| Economic | 350,000 Toman |
| Professional | 750,000 Toman |
| Top | 1,500,000 Toman |

Subscription rules:

- Monthly billing.
- Three-day free trial.
- Ticket-based support during business hours.
- Free HTTPS for all plans.
- Every customer has one account and one primary business in the initial product experience.
- Every business has one primary menu.
- QR code is static in the sense that it points to a stable menu URL and can be downloaded.
- Every business has a dedicated public menu URL.
- A web-based administration panel is included.
- The initial onboarding flow creates one business.
- The backend membership architecture supports multiple memberships.

## 3.2 Economic plan

Monthly price: 350,000 Toman.

Approved features:

1. Maximum 20 products.
2. Maximum 2 categories.
3. Selection from 3 predefined templates.
4. Business name displayed in the menu header.
5. Optional generic icon, such as a coffee cup.
6. Product prices.
7. Product descriptions.
8. Product and category sorting.
9. One branch.
10. Web administration dashboard.
11. Menu editing from mobile devices.
12. Business phone number and address.
13. Dedicated menu URL.
14. Downloadable QR code.

Explicit restrictions:

- No product images.
- No custom business logo.
- No custom color selection.
- No custom font selection.
- No product discounts.
- No product availability toggle.
- No map location.
- No analytics.
- No PDF export.
- No multilingual menus.
- No social media or WhatsApp links.
- No additional customization beyond approved features.

Do not display unavailable features as negative marketing statements such as "No PDF".

## 3.3 Professional plan

Monthly price: 750,000 Toman.

It includes all Economic features, plus:

1. Unlimited products.
2. Maximum 5 categories.
3. One image per product.
4. Custom brand color selection.
5. Font selection.
6. Multiple predefined templates.
7. Business logo.
8. Product discounts.
9. Product availability control.
10. Business location on a map.
11. Dark and light mode.
12. Product labels: Bestseller, New, and Special.
13. Image slider.
14. Advertising banner.
15. Business introduction page.
16. Social media links.
17. Direct phone and WhatsApp links.

Do not add PDF export, analytics, daily reports, or monthly reports to this plan. These were previously rejected.

## 3.4 Top plan

Monthly price: 1,500,000 Toman.

It includes all Professional features, plus:

1. Unlimited categories.
2. Custom menu design.
3. Custom domain.
4. Three languages: Persian, English, Arabic.
5. Maximum two branches.
6. Product videos.
7. Instagram integration and post display.
8. Product reviews and ratings.
9. Product likes.
10. Excel export of product information.
11. Data backup.
12. Change notifications and customer communication.
13. Initial setup by the service team.

Product image limits:

- Economic: 0 images.
- Professional: 1 image.
- Top: maximum 5 images.

## 3.5 Common features

These should be implemented as shared capabilities rather than duplicated plan features:

- HTTPS.
- Monthly subscription infrastructure.
- Three-day trial.
- Ticket-based support during business hours.
- Stable public menu URL.
- Downloadable QR code.

## 3.6 Plan enforcement

All feature restrictions must be enforced in the backend.

Frontend visibility is not sufficient.

Use a centralized entitlement/feature system.

Do not scatter checks such as:

`if ($business->plan === 'professional')`

throughout controllers and Vue pages.

Prefer feature keys such as:

- `max_products`
- `max_categories`
- `max_product_images`
- `max_branches`
- `max_languages`
- `custom_domain`
- `custom_design`
- `product_discount`
- `product_availability_toggle`
- `product_video`
- `product_reviews`
- `product_likes`
- `instagram_integration`
- `excel_export`
- `backup`
- `notifications`
- `initial_setup`
- `map_location`
- `social_links`
- `whatsapp_link`
- `business_logo`
- `custom_colors`
- `font_selection`
- `dark_mode`
- `product_labels`
- `image_slider`
- `advertising_banner`
- `business_introduction`

Feature values must support booleans, numeric limits, lists, and structured configuration.

The entitlement service must be the single source of truth for feature access.

---

# PART 4 — DATABASE AND DOMAIN MODEL

Design the database incrementally, respecting the existing migrations.

Do not create all tables prematurely.

Use appropriate indexes, foreign keys, unique constraints, and transactions.

## 4.1 Identity and business

### users

- id
- public_id
- name
- email
- password
- email_verified_at
- timestamps

Preserve the Starter Kit's authentication requirements.

### businesses

- id
- public_id
- name
- slug
- status
- timestamps

### business_members

- id
- business_id
- user_id
- role
- timestamps

Unique constraint:

`(business_id, user_id)`

Initial role:

`owner`

Future roles may include:

- owner
- admin
- manager
- editor

Do not implement a complete team invitation system unless scheduled in a later phase.

## 4.2 Menus and catalog

### menus

- id
- business_id
- title
- slug
- status
- default_locale
- timestamps

One primary menu per business.

### categories

- id
- menu_id
- name
- sort_order
- is_active
- timestamps

### products

- id
- menu_id
- category_id
- name
- description
- base_price
- discount_price
- is_available
- sort_order
- timestamps

For the initial implementation, each product belongs to one category.

If a future requirement makes products belong to multiple categories, evaluate a pivot-table migration separately.

Use a consistent currency unit across the entire system: Toman.

Use integer amounts rather than floating-point money values.

Validate discount prices against base prices.

Ensure categories and products belong to the same menu.

## 4.3 Branches

### branches

- id
- business_id
- name
- address
- phone
- latitude
- longitude
- is_default
- timestamps

Every business must have a default branch.

### product_branch_settings

- id
- product_id
- branch_id
- price
- discount_price
- is_available
- timestamps

Unique constraint:

`(product_id, branch_id)`

Branch behavior is **MIXED**.

This means:

- The product catalog is shared.
- Product identity and basic information are shared.
- Each branch may override product price.
- Each branch may override discounted price.
- Each branch may override availability.
- Branch-specific overrides must not affect other branches.

Important inheritance rule:

A branch without an explicit override inherits the product's base value.

Do not copy the first branch's values into the second branch.

Use nullable override fields.

If an override is NULL, use the product-level default.

If no branch settings record exists, use product defaults.

The default branch always exists.

Economic and Professional: maximum one branch.

Top: maximum two branches.

Do not implement branch-specific catalogs unless explicitly approved later.

## 4.4 Media

### media

- id
- business_id
- disk
- path
- mime_type
- size
- width
- height
- timestamps

### product_images

- id
- product_id
- media_id
- sort_order
- timestamps

Storage:

Use S3-compatible object storage for production.

Use a local development disk during development if appropriate.

Never store actual binary image files directly in PostgreSQL.

Validate MIME type, file size, dimensions, and ownership.

Enforce plan image limits in backend upload services.

## 4.5 Translations

### menu_translations

- menu_id
- locale
- title
- description

### category_translations

- category_id
- locale
- name

### product_translations

- product_id
- locale
- name
- description

Supported languages:

- fa
- en
- ar

Only Top supports all three languages.

Keep translation data separate from prices and branch-specific availability.

Use unique constraints on entity and locale combinations.

Design a consistent fallback mechanism when a translation is missing.

## 4.6 Plans and subscriptions

### plans

- id
- code
- name
- monthly_price
- is_active
- timestamps

### plan_features

- id
- plan_id
- feature_key
- value
- timestamps

### subscriptions

- id
- business_id
- plan_id
- status
- trial_ends_at
- current_period_starts_at
- current_period_ends_at
- timestamps

### payments

- id
- subscription_id
- amount
- status
- gateway_reference
- paid_at
- timestamps

### subscription_events

- id
- subscription_id
- event_type
- metadata
- created_at

Subscription requirements:

- Three-day trial.
- Monthly billing.
- Upgrade and downgrade support.
- Renewal.
- Expiration.
- Payment history.
- Subscription event history.
- Payment gateway integration through an abstraction layer.
- Never delete customer data merely because a subscription expires.

Plan feature values should be snapshotted or versioned appropriately when a subscription is created or changed, so later changes to plan definitions do not unexpectedly alter an existing subscription.

Design the exact snapshot strategy and explain it before implementation.

## 4.7 Domains and QR codes

### domains

- id
- business_id
- hostname
- is_primary
- verification_status
- ssl_status
- verified_at
- timestamps

### qr_codes

- id
- menu_id
- branch_id (nullable)
- target_url
- file_path
- timestamps

Economic and Professional use shared platform subdomains.

Top supports custom domains.

QR codes should point to stable URLs so changing a domain or routing implementation does not invalidate existing printed QR codes.

Support branch-specific QR codes where appropriate.

## 4.8 Additional Top-plan tables

Implement these in their corresponding phases:

- product_reviews
- product_likes
- notifications
- notification_deliveries
- backups
- social_connections
- social_posts
- audit_logs

Design data ownership and deletion policies carefully.

---

# PART 5 — COMPLETE IMPLEMENTATION ROADMAP

Implement the following phases in order.

Do not skip a phase without explaining why and obtaining approval when the change affects architecture or scope.

## PHASE 0 — Repository Audit and Development Baseline

Objectives:

1. Inspect repository and Git status.
2. Inspect current branch and remote configuration.
3. Inspect Laravel and PHP versions.
4. Inspect Composer and npm dependencies.
5. Inspect database configuration and migrations.
6. Inspect existing Fortify and authentication functionality.
7. Inspect frontend page resolver and layouts.
8. Inspect current test suite.
9. Identify unfinished or broken functionality.
10. Produce a concise technical audit.

Deliverables:

- Current repository status.
- Existing functionality inventory.
- Identified technical risks.
- Proposed implementation sequence.
- No destructive modifications.

Acceptance criteria:

- The actual state is documented.
- Existing work is preserved.
- No assumptions are treated as verified facts.

## PHASE 1 — Development Infrastructure and Docker Foundation

Objectives:

Create a repeatable local development environment.

Tasks:

1. Create or complete Dockerfile.
2. Configure a PHP runtime compatible with Laravel 13.
3. Use PHP 8.5 where the required extensions and dependencies are compatible.
4. Install required PHP extensions.
5. Include PostgreSQL PDO support.
6. Configure PHP runtime settings.
7. Create Docker Compose.
8. Configure PostgreSQL service.
9. Configure Redis if required.
10. Configure application container.
11. Configure frontend build/development workflow.
12. Configure volumes and networks.
13. Configure health checks.
14. Configure persistent database storage.
15. Configure environment variables.
16. Configure permissions and writable Laravel directories.
17. Document local development commands.
18. Verify Laravel can connect to PostgreSQL from inside Docker.
19. Verify migrations.
20. Verify frontend build.

Do not force all development tools into production containers.

Acceptance criteria:

- A new developer can start the application using documented Docker commands.
- PostgreSQL is reachable from Laravel.
- Existing migrations execute.
- Frontend assets build successfully.
- No secrets are committed.
- Existing local development workflow is not unnecessarily broken.

## PHASE 2 — Core Business Module

Objectives:

Implement business ownership and membership.

Tasks:

1. Create Business module structure.
2. Create Business model.
3. Create BusinessMember model.
4. Create migrations.
5. Create BusinessStatus enum.
6. Create BusinessRole enum.
7. Define model relationships.
8. Implement business creation action/service.
9. Implement business creation Form Request.
10. Implement business slug generation.
11. Handle slug uniqueness.
12. Create owner membership transactionally.
13. Implement BusinessPolicy.
14. Add database constraints.
15. Create factories.
16. Create seeders where useful.
17. Add automated tests.

Initial onboarding:

- User registers.
- User logs in.
- If no business exists, redirect to business creation.
- User submits business name.
- System creates business and owner membership in one transaction.
- User is redirected to the business dashboard.

Acceptance criteria:

- Business creation works.
- Owner membership is created.
- Failed transactions do not leave orphaned data.
- Business ownership is enforced.
- Multiple memberships are supported by the data model.

## PHASE 3 — Multi-Tenant Context and Isolation

Objectives:

Implement reliable tenant resolution and data access control.

Tasks:

1. Implement CurrentBusiness service.
2. Register it with scoped lifecycle.
3. Implement tenant resolution middleware.
4. Define the active business resolution strategy.
5. Validate membership before setting active business.
6. Store the active business context safely in the session.
7. Handle invalid or stale active business context.
8. Create authorization policies.
9. Create tenant-aware query patterns.
10. Ensure tenant context is not retained across jobs or tests.
11. Define behavior for unauthenticated and unauthorized requests.
12. Ensure public menu routes are separated from authenticated tenant routes.
13. Add audit logging for sensitive operations where justified.
14. Test cross-tenant access.

Important security tests:

- User A cannot access Business B.
- Changing ULID cannot bypass authorization.
- Changing a route parameter cannot bypass authorization.
- Changing a submitted business_id cannot bypass authorization.
- Direct HTTP requests cannot bypass frontend restrictions.
- A missing tenant context cannot accidentally expose all tenant records.
- A stale session context is handled safely.

Acceptance criteria:

No cross-tenant data leakage through any tested route, controller, service, or query.

## PHASE 4 — Authentication and Account Experience Stabilization

Objectives:

Complete and verify the existing authentication experience.

Tasks:

1. Verify registration.
2. Verify login.
3. Verify logout.
4. Verify password reset.
5. Verify email verification.
6. Review two-factor authentication support.
7. Review passkey functionality already present.
8. Review session security.
9. Configure authentication rate limits.
10. Verify protected routes.
11. Implement correct redirects after authentication.
12. Implement business onboarding redirects.
13. Add necessary Persian RTL authentication pages.
14. Test authentication flows.

Do not replace the existing authentication system without a clear technical reason.

Acceptance criteria:

All supported authentication flows work and have automated tests.

## PHASE 5 — Business Dashboard and Account Settings

Objectives:

Create the initial administrative experience.

Tasks:

1. Create dashboard layout.
2. Create sidebar/navigation.
3. Create responsive mobile navigation.
4. Show business information.
5. Show current account information.
6. Add business profile editing.
7. Add contact details.
8. Add address information.
9. Add business logo upload support only when entitled.
10. Create account settings.
11. Create loading and empty states.
12. Add validation and notifications.
13. Ensure RTL and responsive behavior.

Keep the dashboard modular and ready for additional modules.

## PHASE 6 — Plan and Entitlement Engine

Objectives:

Build the central subscription feature system before enforcing product limitations.

Tasks:

1. Create Plan model.
2. Create PlanFeature model.3. Create Subscription model.
4. Create subscription status enum.
5. Create feature value representation.
6. Create centralized EntitlementService.
7. Create plan configuration and seeders.
8. Define all three approved plans.
9. Implement feature checks.
10. Implement numerical limits.
11. Implement feature middleware or policy integration where appropriate.
12. Implement limit validation services.
13. Implement subscription snapshot/version strategy.
14. Expose safe entitlement information to Vue through Inertia shared props.
15. Add tests for every plan limit.

Initial plan configuration:

| Feature | Economic | Professional | Top |
|---|---:|---:|---:|
| Monthly price (Toman) | 350000 | 750000 | 1500000 |
| Products | 20 | Unlimited | Unlimited |
| Categories | 2 | 5 | Unlimited |
| Product images | 0 | 1 | 5 |
| Branches | 1 | 1 | 2 |
| Languages | 1 | 1 | 3 |
| Custom domain | No | No | Yes |
| Custom design | No | No | Yes |
| Product discounts | No | Yes | Yes |
| Availability control | No | Yes | Yes |
| Product videos | No | No | Yes |
| Reviews | No | No | Yes |
| Likes | No | No | Yes |
| Instagram integration | No | No | Yes |
| Excel export | No | No | Yes |
| Backups | No | No | Yes |

Add the remaining approved feature keys from Part 3.

Do not infer features not approved in the product specification.

Acceptance criteria:

- Economic cannot create product 21.
- Economic cannot create category 3.
- Economic cannot upload product images.
- Professional cannot upload a second image for one product.
- Top cannot upload a sixth image for one product.
- Professional cannot create a second branch.
- Top cannot create a third branch.
- All restrictions are enforced on the server.

## PHASE 7 — Subscription Lifecycle and Billing

Objectives:

Implement commercial subscription behavior.

Tasks:

1. Implement trial creation.
2. Set trial duration to exactly three days.
3. Implement subscription activation.
4. Implement monthly period calculations.
5. Implement renewal.
6. Implement expiration.
7. Implement upgrade.
8. Implement downgrade.
9. Define feature behavior after plan changes.
10. Preserve customer data after expiration.
11. Implement payment records.
12. Implement payment gateway abstraction.
13. Implement payment initiation.
14. Implement payment callback verification.
15. Prevent duplicate payment processing.
16. Implement idempotent payment handling.
17. Implement subscription event history.
18. Implement invoices or payment receipts where required.
19. Implement failed payment handling.
20. Add comprehensive tests.

Payment gateway selection must be discussed before integrating a specific provider.

Do not invent gateway credentials or assume an account exists.

Important:

Never trust the browser's payment success result. Verify payment with the payment provider server-to-server.

Acceptance criteria:

Subscription status and payment records remain consistent even when callbacks are duplicated or delayed.

## PHASE 8 — Menu and Catalog Management

Objectives:

Implement the main menu, categories, and products.

Tasks:

1. Create Menu module.
2. Create Category module.
3. Create Product module.
4. Create migrations and models.
5. Define relationships.
6. Enforce one primary menu per business.
7. Implement menu creation.
8. Implement category CRUD.
9. Implement product CRUD.
10. Implement product sorting.
11. Implement category sorting.
12. Implement product search in the admin panel.
13. Implement product validation.
14. Implement price storage in Toman.
15. Implement discount validation.
16. Implement publication status.
17. Enforce product/category limits through EntitlementService.
18. Add pagination where appropriate.
19. Implement bulk sorting only if justified.
20. Add tenant isolation tests.

Product requirements:

- A product belongs to one category in the initial version.
- A product belongs to one menu.
- Categories belong to one menu.
- Product and category must belong to the same menu.
- Prices use integer Toman amounts.
- Product ordering must be stable.
- Product availability is controlled only where the plan permits it.

Acceptance criteria:

All CRUD operations work, tenant isolation is preserved, and subscription limits are enforced.

## PHASE 9 — Branch Management and Mixed Product Settings

Objectives:

Implement branch support using the approved Mixed model.

Tasks:

1. Create Branch module.
2. Create branch CRUD.
3. Automatically create a default branch when the business setup requires it.
4. Ensure every business has one default branch.
5. Implement branch-specific phone/address/location.
6. Implement product_branch_settings.
7. Implement nullable price override.
8. Implement nullable discount override.
9. Implement nullable availability override.
10. Implement fallback to product base values.
11. Implement branch-specific menu selection.
12. Implement branch-specific QR support.
13. Enforce branch limits using EntitlementService.
14. Add tests for inheritance and overrides.

Inheritance rules:

- If an override is NULL, inherit the product's base value.
- If no branch settings record exists, use product defaults.
- Branch two does not copy branch one's price.
- Branch two does not modify branch one's values.
- A branch override must not affect another branch.

Acceptance criteria:

Changing one branch's price or availability cannot alter any other branch's effective values.

## PHASE 10 — Media and Product Image Management

Objectives:

Implement safe media upload and management.

Tasks:

1. Create Media module.
2. Create media metadata table.
3. Create product_images relation.
4. Configure local development storage.
5. Design production S3-compatible storage.
6. Implement upload service.
7. Implement MIME validation.
8. Implement size validation.
9. Implement dimension validation.
10. Implement safe file naming.
11. Implement image deletion.
12. Implement sorting.
13. Implement responsive image delivery.
14. Enforce per-plan image limits.
15. Ensure media ownership is validated.
16. Prevent unauthorized file access.
17. Add upload tests.

Image limits:

- Economic: zero.
- Professional: one per product.
- Top: maximum five per product.

Do not implement product video storage in this phase unless its dedicated Top-plan phase is approved and scheduled.

## PHASE 11 — Menu Customization and Visual Templates

Objectives:

Implement approved appearance customization.

Economic:

- Three predefined templates.
- Business name in header.
- Optional generic icon.

Professional:

- Multiple predefined templates.
- Custom brand color.
- Font selection.
- Business logo.
- Light/dark mode.
- Product labels.
- Image slider.
- Advertising banner.
- Business introduction page.

Top:

- Custom menu design.

Tasks:

1. Define menu theme schema.
2. Create template registry.
3. Implement theme selection.
4. Implement color settings.
5. Implement typography settings.
6. Implement logo management.
7. Implement dark/light mode.
8. Implement product labels.
9. Implement image slider.
10. Implement banner management.
11. Implement business introduction page.
12. Implement custom design workflow for Top.
13. Enforce entitlements.
14. Ensure public menu rendering is responsive.

Avoid allowing arbitrary unsafe HTML or JavaScript in theme configuration.

Custom design should be implemented as a controlled configuration or a clearly defined design workflow.

## PHASE 12 — Localization and Translation Management

Objectives:

Support multilingual public menus for Top.

Tasks:

1. Define supported locale configuration.
2. Implement menu translations.
3. Implement category translations.
4. Implement product translations.
5. Implement language switcher.
6. Implement Persian RTL.
7. Implement English LTR.
8. Implement Arabic RTL.
9. Implement missing translation fallback.
10. Implement language availability checks.
11. Enforce maximum languages through entitlements.
12. Add localization tests.

Only Top supports:

- fa
- en
- ar

Do not enable multilingual menu features for Economic or Professional.

## PHASE 13 — Public Menu and Customer Experience

Objectives:

Build the public-facing digital menu.

Tasks:

1. Design stable public URL structure.
2. Implement public menu controller and routes.
3. Resolve the correct business/menu.
4. Resolve branch context when required.
5. Render active categories.
6. Render published products.
7. Render effective branch prices.
8. Render discounts where entitled.
9. Render availability where entitled.
10. Render images.
11. Render templates and themes.
12. Render labels.
13. Render banners and sliders.
14. Render business introduction.
15. Render contact details.
16. Render location information where entitled.
17. Render social links where entitled.
18. Render WhatsApp link where entitled.
19. Implement responsive mobile UI.
20. Optimize page loading.
21. Implement appropriate metadata and SEO.
22. Prevent private administrative information from leaking.

Public menu requirements:

- No customer login is required to view a menu.
- Menu should be optimized for mobile devices.
- Public routes must not expose tenant-private records.
- Menu must respect subscription status and entitlements.
- Public menu URL should remain stable.

## PHASE 14 — QR Code Generation and URL Management

Objectives:

Implement reliable downloadable QR codes.

Tasks:

1. Select and evaluate a maintained QR generation library.
2. Implement QR code generation service.
3. Generate QR for the primary menu.
4. Support branch-specific QR codes where appropriate.
5. Generate downloadable PNG.
6. Consider SVG output if supported safely.
7. Store QR metadata.
8. Use stable URLs.
9. Implement QR regeneration.
10. Ensure existing printed QR codes remain functional when the domain configuration changes.
11. Add authorization to QR management.
12. Add tests.

Acceptance criteria:

Every business can download a valid QR code pointing to its public menu.

## PHASE 15 — Top Plan Advanced Features

Implement these independently and enforce Top-plan entitlement.

### 15.1 Product video

- Video upload or supported video source.
- Validation and safe storage.
- Public playback.
- Resource limits.
- Entitlement enforcement.

### 15.2 Product reviews and ratings

- Review submission.
- Rating validation.
- Moderation strategy.
- Spam prevention.
- Public display.
- Tenant isolation.
- Entitlement enforcement.

### 15.3 Product likes

- Like/unlike behavior.
- Anonymous or session-based identity strategy.
- Duplicate prevention.
- Abuse protection.
- Entitlement enforcement.

Discuss the customer identity and privacy model before implementation.

### 15.4 Instagram integration

- Official API-based integration where available.
- OAuth where required.
- Secure token storage.
- Token refresh strategy.
- Post retrieval and display.
- Disconnect flow.
- Error handling.
- API limitation handling.

Do not scrape Instagram or bypass platform restrictions.

Verify the currently supported Instagram API capabilities before implementing.

### 15.5 Excel export

- Export product information.
- Validate business ownership.
- Respect plan access.
- Generate safe downloadable files.
- Use queued processing for large exports if needed.

### 15.6 Backup

- Define backup scope.
- Implement scheduled backups.
- Encrypt sensitive backups.
- Store backups securely.
- Define retention.
- Implement restore procedures.
- Test restoration.

A backup feature is incomplete without a tested restore procedure.

### 15.7 Notifications and customer communication

- Notification data model.
- Delivery tracking.
- Change notifications.
- Customer communication preferences.
- Delivery failure handling.
- Queue processing.
- Rate limiting.
- Consent and privacy considerations.

Do not send unsolicited marketing messages.

### 15.8 Initial setup service

Implement a workflow to track initial setup performed by the service team.

Define:

- Setup status.
- Assigned operator.
- Required information.
- Completion timestamp.
- Internal notes.
- Audit trail.

Do not invent a staffing or support workflow beyond what is necessary.

## PHASE 16 — Custom Domains and HTTPS

Objectives:

Implement Top-plan custom domain support.

Tasks:

1. Create domain management interface.
2. Implement domain registration.
3. Implement DNS verification.
4. Implement domain ownership checks.
5. Implement hostname normalization.
6. Prevent domain takeover.
7. Implement domain-to-business resolution.
8. Implement primary domain selection.
9. Implement SSL provisioning strategy.
10. Track SSL status.
11. Implement renewal monitoring.
12. Handle domain removal.
13. Ensure platform subdomains remain available for Economic and Professional.
14. Ensure QR codes continue to resolve.

Discuss infrastructure requirements before selecting an automated SSL provider.

HTTPS must be available across all plans.

## PHASE 17 — Social Links, Contact and Location

Implement approved Professional features:

- Social media links.
- Direct phone link.
- WhatsApp link.
- Business location.
- Map display.

Requirements:

- Validate URLs.
- Prevent unsafe schemes.
- Normalize phone numbers.
- Validate geographic coordinates.
- Respect feature access.
- Keep branch location separate from business-level defaults.

## PHASE 18 — Notifications, Audit Logs and Operational Events

Objectives:

Implement reliable operational visibility.

Tasks:

1. Create audit log schema.
2. Track sensitive administrative changes.
3. Track subscription lifecycle events.
4. Track domain verification events.
5. Track backup events.
6. Track notification deliveries.
7. Define retention policies.
8. Prevent logging secrets or sensitive data.
9. Add administrative visibility where justified.

Do not turn audit logs into an unapproved analytics product.

## PHASE 19 — Testing and Quality Assurance

Create a comprehensive test strategy.

### Unit tests

Test:

- Business ownership.
- Business status transitions.
- Entitlement resolution.
- Subscription lifecycle.
- Price inheritance.
- Discount validation.
- Translation fallback.
- Branch overrides.
- QR URL resolution.

### Feature tests

Test:

- Registration.
- Login.
- Password reset.
- Business creation.
- Business settings.
- Category CRUD.
- Product CRUD.
- Branch CRUD.
- Media upload.
- Subscription creation.
- Payment callbacks.
- Public menu rendering.
- QR generation.
- Custom domain resolution.
- Reviews.
- Likes.
- Export.

### Tenant security tests

Test every major tenant-owned resource against cross-tenant access.

### Plan restriction tests

Verify all numerical and boolean restrictions.

### Critical business scenarios

1. Economic cannot create product 21.
2. Economic cannot create category 3.
3. Economic cannot upload product images.
4. Professional cannot upload a second image per product.
5. Top cannot upload a sixth image per product.
6. Professional cannot create branch two.
7. Top cannot create branch three.
8. Branch two overrides cannot modify branch one.
9. Missing branch override inherits product defaults.
10. Subscription expiration does not delete data.
11. Plan upgrade does not delete existing customer data.
12. Plan downgrade does not silently delete products, images, or categories.
13. Cross-tenant access is rejected.
14. Duplicate payment callbacks do not create duplicate successful payments.
15. Public menus do not expose administrative data.

Use factories and realistic fixtures.

Run the test suite after meaningful changes.

Never claim a test passed unless it actually ran and passed.

## PHASE 20 — Performance and Scalability

Objectives:

Prepare the application for real customer usage.

Tasks:

1. Review database indexes.
2. Identify N+1 queries.
3. Optimize public menu queries.
4. Add appropriate caching.
5. Implement cache invalidation.
6. Use Redis where justified.
7. Configure queue workers.
8. Optimize image delivery.
9. Use pagination in administrative lists.
10. Review large export jobs.
11. Review database connection management.
12. Add basic performance tests.
13. Monitor slow queries.

Avoid premature complexity.

## PHASE 21 — Security Hardening

Perform a security review covering:

- Authentication.
- Authorization.
- Tenant isolation.
- IDOR.
- CSRF.
- XSS.
- SQL injection.
- Mass assignment.
- File upload security.
- Unsafe URL handling.
- Rate limiting.
- Session security.
- Password reset security.
- Payment callback verification.
- Domain verification.
- Secret management.
- Access to backups.
- Data retention.
- Audit logging.

Also consider:

- Content Security Policy.
- Secure and HttpOnly cookies.
- SameSite policy.
- HTTPS enforcement.
- Secure headers.
- Brute-force protection.

Do not introduce security controls that break existing functionality without testing.

## PHASE 22 — Production Infrastructure and Deployment

Objectives:

Deploy the application to production safely.

Tasks:

1. Define production architecture.
2. Configure web server and reverse proxy.
3. Configure PHP-FPM or the chosen production runtime.
4. Configure PostgreSQL.
5. Configure Redis.
6. Configure object storage.
7. Configure queue workers.
8. Configure scheduled tasks.
9. Configure HTTPS.
10. Configure environment secrets.
11. Configure logging.
12. Configure monitoring.
13. Configure database backups.
14. Configure restore procedures.
15. Configure health checks.
16. Configure deployment strategy.
17. Configure rollback procedure.
18. Configure database migration strategy.
19. Configure static asset builds.
20. Configure storage permissions.
21. Configure error monitoring.
22. Configure uptime monitoring.

Production must not run with:

- Debug enabled.
- Exposed secrets.
- Unprotected database access.
- Unverified backup procedures.
- Development-only credentials.

## PHASE 23 — CI/CD and Release Management

Objectives:

Build a reliable release process.

Tasks:

1. Define branch strategy.
2. Configure GitHub Actions or an equivalent CI system.
3. Run Composer checks.
4. Run PHP tests.
5. Run frontend type checks.
6. Run frontend build.
7. Run static analysis if adopted.
8. Run code style checks.
9. Build deployment artifacts.
10. Define release tagging.
11. Define migration execution.
12. Define rollback policy.
13. Document release procedure.

Do not deploy untested changes to production.

## PHASE 24 — Documentation and Final Product Readiness

Produce and maintain:

- README.md
- Architecture documentation
- Database ERD
- Database migration guide
- API and route documentation where applicable
- Local development guide
- Docker guide
- Environment configuration guide
- Deployment guide
- Backup and restore guide
- Subscription and entitlement documentation
- Tenant security documentation
- Testing guide
- Operational runbook

Create a final readiness report containing:

- Implemented features.
- Unimplemented features.
- Known bugs.
- Security findings.
- Performance findings.
- Test results.
- Deployment status.
- Outstanding risks.

Do not label the project production-ready until critical acceptance criteria are met.

---

# PART 6 — DATABASE DESIGN RULES

Apply these principles throughout development:

1. Use foreign keys.
2. Use unique constraints.
3. Add indexes for common query patterns.
4. Use transactions for multi-step operations.
5. Use integer storage for monetary amounts.
6. Use ULIDs for public identifiers.
7. Use BIGINT internal primary keys.
8. Use explicit nullability.
9. Avoid polymorphic relationships unless there is a genuine need.
10. Use soft deletes only where lifecycle requirements justify them.
11. Preserve financial records.
12. Define data retention before implementing deletion.
13. Avoid duplicating business ownership data unnecessarily.
14. Ensure related tenant records belong to the same business.
15. Document non-obvious constraints.

Consider database-level strategies to prevent invalid cross-business relationships, not just application-level checks.

---

# PART 7 — USER EXPERIENCE AND DESIGN REQUIREMENTS

The application is primarily for Iranian café and restaurant owners.

Dashboard language:

Persian.

Dashboard direction:

RTL.

Public menu languages:

- Persian
- English
- Arabic (Top only)

Design goals:

- Modern SaaS dashboard.
- Minimal and professional.
- Mobile-first menu experience.
- Fast loading.
- Clear navigation.
- Consistent visual system.
- Accessible contrast and readable typography.
- Reusable Vue components.
- Clear empty states.
- Clear validation feedback.

Do not design every screen in advance if it blocks backend development.

Build and validate the interface alongside each feature.

Avoid introducing an unnecessary component library if the existing Starter Kit and Reka UI already cover the requirement.

---

# PART 8 — REQUIRED DEVELOPMENT WORKFLOW

This section is mandatory.

## 8.1 Inspect before changing

Before every implementation phase:

1. Inspect the relevant existing code.
2. Identify affected files.
3. Review existing conventions.
4. Identify migration risks.
5. Identify dependencies.
6. Explain the intended change briefly.

Never assume a file or feature is absent without checking.

## 8.2 Work in small, controlled increments

Do not attempt to implement the entire SaaS in one response.

For every phase:

1. Define the objective.
2. Define the scope.
3. Define acceptance criteria.
4. Implement.
5. Run relevant tests.
6. Run static analysis/type checks where applicable.
7. Review security implications.
8. Summarize changed files.
9. Report actual results.
10. Identify the next step.

## 8.3 Protect existing work

Never:

- Delete existing migrations without approval.
- Reset a production database.
- Run destructive database commands without approval.
- Rewrite the entire Starter Kit without a reason.
- Remove existing authentication features without approval.
- Force-push Git history.
- Commit `.env` or secrets.
- Modify unrelated files unnecessarily.

## 8.4 Git workflow

Use the development branch:

`chore/phase-1-foundation`

Verify the active branch before making changes.

Use focused commits.

Suggested commit naming:

- `chore(docker): configure development environment`
- `feat(business): add business ownership module`
- `feat(tenancy): implement current business context`
- `test(tenancy): add cross-tenant isolation tests`
- `feat(subscription): implement entitlement engine`

Do not automatically push, merge, or deploy without authorization.

## 8.5 Technical decision management

Maintain these files:

- `docs/architecture.md`
- `docs/roadmap.md`
- `docs/decisions/`

Use Architecture Decision Records for significant decisions.

For every important decision, document:

- Context.
- Options.
- Chosen approach.
- Reasoning.
- Consequences.

Do not silently change approved product requirements.

## 8.6 Handling uncertainty

If a decision is necessary but not specified:

- Prefer the simplest secure implementation.
- Follow existing project conventions.
- Explain the assumption.
- Record it.
- Ask the user only if the decision materially affects architecture, data integrity, cost, security, or product behavior.

Do not repeatedly ask questions already answered in this specification.

Do not invent external service credentials or claim an integration works without verification.

---

# PART 9 — DEFINITION OF DONE

A phase is complete only when:

1. Implementation exists in the repository.
2. Database migrations are correct.
3. Relevant authorization rules exist.
4. Validation is implemented.
5. Automated tests pass.
6. Relevant frontend checks pass.
7. No known critical regression remains.
8. Documentation is updated.
9. The changed files are summarized.
10. Acceptance criteria are explicitly checked.

If a test cannot run because of an environmental limitation, report that limitation. Do not report a successful result.

---

# PART 10 — HOW TO START

Start with **PHASE 0: Repository Audit and Development Baseline**.

Do not immediately implement features.

First:

1. Inspect the actual repository.
2. Inspect the current Git branch and working tree.
3. Verify current runtime versions.
4. Review the existing migrations.
5. Review authentication configuration.
6. Review frontend structure.
7. Review current test coverage.
8. Compare actual repository state against the supplied project status report.
9. Identify differences.
10. Produce a Persian technical audit and an updated implementation plan.

Then proceed to **PHASE 1: Development Infrastructure and Docker Foundation**.

After completing each phase, report the result and continue to the next phase when it is safe and does not require an unresolved architectural or product decision.

Do not stop after producing a plan. The goal is actual implementation.

However, do not perform destructive actions, deploy to production, purchase services, configure paid external integrations, or change approved product decisions without explicit user approval.

**Final instruction:** Treat this document as the project's master specification. The repository is the source of truth for implementation state; this document is the source of truth for approved product requirements and architectural decisions. If they conflict, report the conflict instead of silently choosing one.