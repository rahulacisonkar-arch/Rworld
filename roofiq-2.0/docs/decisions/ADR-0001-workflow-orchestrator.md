# ADR-0001: Workflow Orchestrator Pattern

## Status
Accepted

## Context
Originally, the application processed calculations in a linear service-to-service call chain. This created problems with API failures, parallel task execution, and transaction tracking.

## Decision
We introduce a centralized parent-child dependency queue structure using BullMQ.
- Tasks like Weather forecast checks, municipal Permits lookup, and OCR document scans run in parallel.
- A centralized coordinator monitors completion status and fires downstream AI segmentation tasks only when prerequisites are met.

## Consequences
- Pros: Excellent error resilience, automatic retries with exponential backoffs, and reduced execution times.
- Cons: Slightly higher complexity in job status tracking.
