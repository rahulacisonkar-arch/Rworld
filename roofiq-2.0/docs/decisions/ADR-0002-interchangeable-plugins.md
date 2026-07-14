# ADR-0002: Interchangeable Plugin Interfaces

## Status
Accepted

## Context
Third-party providers (CRM, Weather APIs, GIS services) need to hook into the platform without modifying core code or causing merge conflicts.

## Decision
All integrations are wrapped in a standardized interface (`initialize`, `shutdown`, `health`, `execute`). Plugins are housed under separate subdirectories inside `src/plugins/`.

## Consequences
- Pros: True plug-and-play architecture, modular testing, and decoupling of core routes.
- Cons: Inputs/outputs schemas must be standardized using rigid typing.
