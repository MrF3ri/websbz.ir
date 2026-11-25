# Phase 10 — S3-compatible storage verification

The repository currently contains S3 disk configuration, but composer.lock does not yet include league/flysystem-aws-s3-v3. Therefore the S3 driver is not operational until that adapter dependency is added and composer.lock is regenerated. The application is designed to serve media through its own authorization-checked route; the bucket must not be public.

## Non-production bucket requirements

Before enabling the integration test, provision a dedicated non-production bucket and credentials with only the access required by the application:

- Read object metadata and content for the configured bucket/prefix.
- Put objects.
- Delete objects.
- Do not grant public ACL or anonymous bucket access.
- Keep bucket-level public access blocks enabled.
- Require TLS for object requests.
- Use provider-side encryption at rest where available.
- Restrict credentials to the dedicated non-production bucket or prefix.

Set the normal Laravel S3 environment variables (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET). For S3-compatible providers, also set AWS_ENDPOINT and AWS_USE_PATH_STYLE_ENDPOINT as required by that provider. Set MEDIA_DISK=s3.

## Retention and lifecycle

Product images and their generated variants must remain available for as long as their media records are referenced. Do not apply an age-based expiration rule to current objects: that could delete live menu images without updating the database.

Recommended baseline lifecycle rule:

- Abort incomplete multipart uploads after 7 days.
- Do not expire current objects automatically.
- Do not configure non-current version expiration until the owner selects a version-retention period.
- Application deletion removes the original and generated variants together. A failed object deletion must leave database metadata available for retry.

## Integration test

The opt-in S3MediaStorageIntegrationTest exercises upload, variant creation, authenticated public delivery, and deletion against the configured bucket. It skips when the Flysystem S3 adapter is not installed; adding and locking that package is a prerequisite for a real-bucket run.

Enable it only against a dedicated non-production bucket:

- MEDIA_S3_INTEGRATION=1
- MEDIA_S3_BUCKET_IS_NON_PRODUCTION=1
- Valid S3-compatible credentials and endpoint configuration

Never add access keys or secrets to source control. The test is skipped by default. A successful local fake-disk test or a MinIO container does not replace this real-bucket verification.
