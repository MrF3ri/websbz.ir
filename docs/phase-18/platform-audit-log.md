# Platform Admin Audit Log

## Scope

The first audit-log slice records changes to the platform's manual card-to-card destination details. It is separate from subscription lifecycle events and is visible only to the Platform Admin at `/admin/audit-logs`.

Each record captures the actor, action, target, timestamp, and safe before/after snapshots. Card numbers are never copied into audit records; only the final four digits are retained. Receipt paths, uploaded files, raw request payloads, and other secrets are excluded.

## Integrity

Audit rows are append-only from application workflows: the feature exposes no update or delete endpoint. The settings update and its audit insert run in the same database transaction, so a failed write cannot leave an unpaired audit record. Manual payment approval/rejection is also audited in the existing locked transaction; snapshots include only payment/subscription state, amount, plan, and period fields. Rejection reasons, bank tracking numbers, receipt paths, and feature snapshots are excluded.

The one-time `platform-admin:grant` Artisan command records a grant when it changes a user's flag. Since a CLI invocation has no authenticated application user, the audit row uses a null `actor_id` and the explicit `actor_label` `CLI / Artisan`; the target is identified by the user's public ULID, not email. Re-running the command for the same admin does not create a duplicate record.

## Extension boundary

Future sensitive platform operations must use the same audit pattern and define an explicit allowlist of snapshot fields. Never serialize arbitrary request data or model attributes into the audit log. Do not serialize arbitrary request data or model attributes into the audit log.
