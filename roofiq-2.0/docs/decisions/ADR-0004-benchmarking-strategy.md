# ADR-0004: Performance Benchmarking Strategy

## Status
Accepted

## Context
AI model inference (SAM 2, YOLOv11 OBB) and large PDF reports compilation introduce latency and processing bottlenecks. We need a way to trace latency as new features are added.

## Decision
We introduce performance tracing checkpoints:
- **Core API Latency**: Measured using OpenTelemetry tracers inside Express gateways routes.
- **AI Inference Benchmarks**: Python FastAPI service tracks inference times (milliseconds) for YOLOv11 and SAM 2 model execution loops.
- **Queue Latency**: BullMQ job processing durations are periodically recorded and scraped by Prometheus.
