#!/bin/sh
set -eu

# The official Postgres image runs this only when initializing a fresh
# PGDATA volume. The test runner also creates the DB if it is absent, to
# support volumes that already existed before this init script was added.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  --command 'CREATE DATABASE websbz_test'
