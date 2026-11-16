# Platform Admin Audit Log

## Scope

The first audit-log slice records changes to the platform's manual card-to-card destination details. It is separate from subscription lifecycle events and is visible only to the Platform Admin at `/admin/audit-logs`.

Each record captures the actor, action, target, timestamp, and safe before/after snapshots. Card numbers are never copied into audit records; only the final four digits are retained. Receipt paths, uploaded files, raw request payloads, and other secrets are excluded.

## Integrity

Audit rows are append-only from application workflows: the feature exposes no update or delete endpoint. The settings update and its audit insert run in the same database transaction, so a failed write cannot leave an unpaired audit record.

## Extension boundary

Future sensitive platform operations must use the same audit service/pattern and define an explicit allowlist of snapshot fields. Do not serialize arbitrary request data or model attributes into the audit log.
