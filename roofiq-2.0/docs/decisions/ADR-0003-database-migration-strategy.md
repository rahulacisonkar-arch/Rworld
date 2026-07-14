# ADR-0003: Database Migration and Seeding Policy

## Status
Accepted

## Context
As the spatial schema evolves (adding PostGIS fields or MultiPolygons boundaries constraints), schema deployments must remain predictable, safe, and easily reproducible across development and staging environments.

## Decision
We enforce a strict migration lifecycle:
1. **Schema Modifications**: Changes must be declared inside the Prisma schema.
2. **Migration Generation**: Create migration files:
   ```bash
   npx prisma migrate dev --name <migration_name>
   ```
3. **Seeding Strategy**: Mock tables (users, properties) are seeded via `prisma/seed.ts` automatically run on migration reset.
4. **Rollback Policy**: Any destructive changes (dropping columns) require a manual SQL rollback script stored inside the migration package directory.
