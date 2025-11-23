# Technical Architecture Overview

## Executive Summary

The **Laravel Webhook Engine** is an enterprise-grade, high-throughput webhook ingestion, processing, and management system. Built on **Laravel 11**, **Redis**, **Laravel Horizon**, and **Livewire v3**, it guarantees sub-millisecond ingestion acknowledgment (`202 Accepted`), strict idempotency via Redis atomic locks, multi-tier queue priority handling, automated Dead-Letter Queue (DLQ) capture, and Kubernetes cluster autoscale readiness.

---

## High-Level System Architecture

```
                                  ┌──────────────────────────────┐
                                  │ Webhook Producer             │
                                  │ (Stripe, Shopify, Custom)    │
                                  └──────────────┬───────────────┘
                                                 │ POST /api/v1/webhooks/{provider}
                                                 ▼
                                  ┌──────────────────────────────┐
                                  │ HMAC Signature Verification  │
                                  └──────────────┬───────────────┘
                                                 │
                                                 ▼
                                  ┌──────────────────────────────┐
                                  │ Redis Idempotency Lock       │
                                  │ (SET key val NX EX 86400)    │
                                  └──────────────┬───────────────┘
                                                 │
                                                 ▼
                                  ┌──────────────────────────────┐
                                  │ Webhook Event Persistence    │
                                  │ (MySQL / WebhookLog)         │
                                  └──────────────┬───────────────┘
                                                 │
                                                 ▼
                                  ┌──────────────────────────────┐
                                  │ Laravel Horizon Queue        │
                                  │ High | Default | Low         │
                                  └──────────────┬───────────────┘
                                                 │
                        ┌────────────────────────┴────────────────────────┐
                        ▼                                                 ▼
          ┌───────────────────────────┐                     ┌───────────────────────────┐
          │ ProcessWebhookJob         │                     │ Failed Job (Attempt == 4) │
          │ (Exponential Backoff)     │                     └─────────────┬─────────────┘
          └─────────────┬─────────────┘                                   │
                        │                                                 ▼
                        ▼                                   ┌───────────────────────────┐
          ┌───────────────────────────┐                     │ Dead-Letter Queue (DLQ)   │
          │ RateLimited Dispatcher    │                     │ Capture & Slack Alerts    │
          └───────────────────────────┘                     └─────────────┬─────────────┘
                                                                          │
                                                                          ▼
                                                            ┌───────────────────────────┐
                                                            │ Livewire Admin Dashboard  │
                                                            │ Manual Replay / Audit Log │
                                                            └───────────────────────────┘
```

---

## Core Components & Key Design Patterns

### 1. Ingestion API & DTO Parser (`WebhookIngestionController`)
- Low overhead endpoint returning `202 Accepted` within ~15ms.
- High-performance `WebhookDtoParserService` standardizes raw payloads from different vendors into unified `WebhookPayloadDTO`.

### 2. Security & HMAC Signature Middleware (`VerifyWebhookSignature`)
- Enforces cryptographic HMAC validation before payload parsing:
  - **Stripe**: `Stripe-Signature` header (`t=timestamp,v1=signature`)
  - **Shopify**: `X-Shopify-Hmac-SHA256` header (Base64 HMAC)
  - **Generic**: `X-Signature` or `X-Hub-Signature-256` header

### 3. Redis Atomic Idempotency (`RedisIdempotencyService`)
- Prevents duplicate event execution under high concurrency.
- Atomic `SET webhook_idempotency:{provider}:{event_id} locked EX 86400 NX`.
- Returns `409 Conflict` on duplicate attempts within 24 hours.

### 4. Horizon Multi-Tier Queues (`ProcessWebhookJob`)
- Configured queue priorities:
  - `high`: Payment & financial events (`charge.succeeded`, `invoice.payment_failed`)
  - `default`: Standard operational webhooks (`orders/create`, `user.updated`)
  - `low`: Data synchronization & analytics
- Exponential retry schedule: `[10s, 30s, 90s, 300s]` across 4 attempts.

### 5. Dead-Letter Queue (DLQ) & Alerting (`DeadLetterQueueService`)
- Failed jobs exceeding 4 retries are captured in `dead_letter_queue_events` with full stack traces.
- `WebhookAlertManager` triggers automated Slack notifications and Sentry exceptions when >5 failures occur within 15 minutes.

### 6. Livewire v3 Admin UI (`DashboardMetrics`, `DeadLetterQueueManager`, `WebhookLogTable`)
- Real-time polling monitoring queue metrics, execution latency, and provider distribution.
- One-click manual job replay for failed DLQ records.
