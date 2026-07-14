# Security & RBAC Guidelines

RoofIQ AI enforces security protocols across all endpoints.

---

## 1. Multi-Tenancy

- **Partitioning**: All query operations include a mandatory `tenantId` filter mapped from the verified user session token.
- **Cross-Tenant Prevention**: Explicit checks prevent fetching properties or analyses belonging to other tenants.

---

## 2. Role-Based Access Control (RBAC)

- **Admin**: Full settings access, API key generations, integrations toggling, user management.
- **Estimator**: Manage properties, trigger AI runs, apply measurements overrides, compile proposals.
- **Crew**: Read-only access to measurements, inspections, and assigned schedules.
- **Client**: Read-only access to their specific property proposal downloads.

---

## 3. Cryptography & CSRF

- **Secrets Encryption**: Wholesalers API keys and database parameters are encrypted using AES-256-CBC.
- **CSRF Defense**: Non-GET REST routes check double-submit cookie verification values.
