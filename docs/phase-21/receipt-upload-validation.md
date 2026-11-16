# Phase 21 — Manual payment receipt upload validation

## Accepted upload contract

Manual payment receipts are stored on the private `local` disk under a server-generated path scoped to the current business. The client filename is never used as a storage path.

The upload must satisfy all of these checks:

- A file must be present.
- Detected content MIME must be JPEG, PNG, or PDF.
- The client filename extension must be one of `jpg`, `jpeg`, `png`, or `pdf`.
- The detected MIME and supplied extension are checked independently to reject mismatched pairs.
- Maximum size is 5 MiB (5120 KiB).

Rejected uploads must not create a payment row or leave a stored file. The same receipt path is not exposed through public storage; downloads remain behind the tenant-scoped billing route or the Platform Admin route.

## Regression coverage

Feature tests cover valid private storage, generated business-scoped paths, absence from the public disk, mismatched content/extension, disallowed MIME content, oversized files, and cross-business receipt download denial.

These application-level checks complement, but do not replace, production web-server/PHP upload-size limits and malware scanning if the deployment later accepts higher-risk file types.
