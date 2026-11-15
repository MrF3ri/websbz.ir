# ADR 0007: SQLite Fast Suite and Required PostgreSQL CI

## Context
The project uses SQLite in-memory for fast feedback, while PostgreSQL is the target database. SQLite does not prove PostgreSQL row-locking, concurrency, or composite-FK behavior.

## Options
- Run only SQLite tests.
- Replace the fast suite entirely with PostgreSQL.
- Keep SQLite as default and add a required PostgreSQL full-suite job.

## Chosen
Keep SQLite in-memory as the default fast PHPUnit suite and add a separate required CI job running the full PHPUnit suite against PostgreSQL.

## Reasoning
This retains fast feedback while exercising target-database behavior on every PR. PostgreSQL is mandatory for locking/concurrency/composite-FK acceptance.

## Consequences
Both jobs remain enabled. Add targeted concurrency and composite-FK tests against PostgreSQL as those features are introduced. A green SQLite run is not evidence that locking semantics work.
