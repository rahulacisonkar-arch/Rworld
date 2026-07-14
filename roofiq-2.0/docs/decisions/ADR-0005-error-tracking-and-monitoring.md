# ADR-0005: Error Tracking & Centralized Monitoring

## Status
Accepted

## Context
When microservices (AI service, email workers, API core) run in a distributed production deployment (under Coolify), diagnosing root causes of transaction failures becomes difficult without central error log collections.

## Decision
We integrate a self-hosted Sentry instance alongside OpenTelemetry tracers:
1. **Sentry Node/Python SDK**: Catches and alerts developers of unhandled runtime failures, stack traces, and database connection losses.
2. **OpenTelemetry APM**: Emits latency measurements and request calls tracing diagrams, allowing dashboards (Prometheus/Grafana) to isolate queue delays.

## Consequences
- Pros: Instant alert notifications, trace metrics mapping, and rapid debugging capabilities.
- Cons: Extra runtime footprint (small overhead) to dispatch metrics to collector agents.
