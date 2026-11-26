# ADR 0025 — Product export format and streaming strategy

- Status: Proposed for Phase 15.5 implementation
- Date: 2026-11-26

## Context

Top-plan businesses need a safe downloadable export of their menu products. Product catalogs may grow, so the export should avoid loading the entire catalog into application memory or writing temporary files that require cleanup.

The current production runtime already supports PHP CSV streaming. No spreadsheet-writing dependency is currently installed.

## Decision

- Deliver an Excel-compatible UTF-8 CSV file with a UTF-8 BOM so Persian text opens correctly in common spreadsheet applications.
- Use the standard CSV format rather than adding a native XLSX dependency in this increment. The output is a .csv file, not a native .xlsx workbook.
- Stream the response directly to the client and query products in chunks of 500. Do not persist export files or queue jobs.
- Export the canonical menu catalog only: public product identifier, category, name, description, base price, discount price, publication status, availability, label, and creation timestamp.
- Do not export private media paths, internal database identifiers, visitor information, or branch-specific override rows.
- Require the current business context, catalog authorization, and the excel_export entitlement.
- Scope the query through the current business's menu.
- Prefix spreadsheet formula-leading cells with an apostrophe to mitigate CSV formula injection. Apply this to every exported cell, not only user-entered names/descriptions.
- Send no-store cache headers and nosniff content type.

## Consequences

- No new Composer dependency or production PHP extension is required.
- Streaming and chunking keep memory use bounded and avoid retained temporary files.
- Excel and other spreadsheet programs can open the CSV, but formatting, formulas, multiple worksheets, and native XLSX features are not provided.
- Branch-specific price/availability overrides are not included; the export represents canonical product data.
- A future request for native XLSX or branch-specific sheets should be evaluated as a separate increment with an explicit dependency and compatibility review.

## Verification requirements

- Top plan can download a UTF-8 CSV with expected headers and product fields.
- Persian text remains intact through the UTF-8 BOM.
- Formula-leading cells are neutralized.
- Export is scoped to the current business.
- Non-Top plans and unauthorized users cannot export.
- Empty catalogs still produce a valid header-only export.
- SQLite, PostgreSQL, static analysis, frontend checks, and production-image validation pass.
