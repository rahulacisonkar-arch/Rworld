# Coding Standards — Shekhar RoofIQ AI 2.0

This document defines the coding conventions, design standards, and validation rules for developers contributing to the RoofIQ 2.0 ecosystem.

---

## 1. TypeScript & Node.js Core Backend
- **Architectural Layering:** Every module must strictly separate concerns using the following layered pattern:
  ```
  Controller ➔ Service ➔ Repository ➔ Prisma
  ```
- **Strict Typing:** Always enforce `"strict": true` in `tsconfig.json`. Explicit `any` is forbidden.
- **Relational Integrity:** Schema changes must be implemented through **Prisma Migrations**; raw SQL modifications on production DB are disallowed.
- **Microservices Interface:** All REST endpoints must match the schemas defined in `openapi.yaml`. Update the specs before editing controller interfaces.

## 2. Python AI Service (FastAPI)
- **Typing & Validation:** Every request and response payload must utilize **Pydantic V2** schemas.
- **Style Guidelines:** Strict adherence to **PEP-8**. All classes must contain docstrings.
- **Virtual Environments:** Always manage environments using `uv`:
  ```bash
  uv venv --python 3.11
  uv pip compile pyproject.toml -o requirements.txt
  ```

## 3. Frontend Next.js Client
- **Design Tokens:** Always use tailwind styling mapped to the design system palette. Frozen CSS or inline styles on widgets are disallowed.
- **Responsive Layout:** Grid views must handle fluid break-points from desktop (`col-lg-8`) down to mobile views.
- **Clean Componentization:** Keep JSX components small and focused. Decouple GIS mapping wrappers (OpenLayers/Leaflet) from business forms.

## 4. Git & Workflow Standards
- **Branch Naming:** `feature/*`, `fix/*`, or `docs/*`.
- **Commit Messages:** Follow Conventional Commits format:
  ```
  feat(api): add FEMA flood zone lookup
  fix(charts): destroy old solarChart canvas instance
  ```
