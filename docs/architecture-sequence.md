# Webhook Lifecycle Sequence Flowcharts

This document details the sequence flowcharts and architectural interactions across the entire **Laravel Webhook Engine** lifecycle.

---

## 1. Webhook Ingestion & HMAC Signature Verification

```mermaid
sequenceDiagram
    autonumber
    actor Provider as Webhook Producer (Stripe/Shopify)
    participant API as Ingestion API Controller
    participant Middleware as Signature Middleware
    participant Validator as Provider Validator
    participant Redis as Redis Idempotency Lock
    participant DB as MySQL Database

    Provider->>API: POST /api/v1/webhooks/{provider}
    API->>Middleware: Intercept Request
    Middleware->>Validator: verifySignature(rawPayload, headerSignature, secret)
    alt Invalid Signature
        Validator-->>Middleware: false
        Middleware-->>Provider: HTTP 401 Unauthorized
    else Valid Signature
        Validator-->>Middleware: true
        Middleware->>API: Pass Request
        API->>Redis: acquireLock(provider, event_id)
        alt Lock Exists / Already Processed
            Redis-->>API: false
            API-->>Provider: HTTP 409 Conflict (Duplicate Ignored)
        else Lock Acquired
            Redis-->>API: true
            API->>DB: WebhookEvent::updateOrCreate(status=pending)
            API->>DB: WebhookLog::create(response_code=202)
            API-->>Provider: HTTP 202 Accepted
        end
    end
```

---

## 2. Horizon Queue Processing & Exponential Retries

```mermaid
sequenceDiagram
    autonumber
    participant Horizon as Horizon Queue Worker
    participant Job as ProcessWebhookJob
    participant Service as Business / Dispatch Service
    participant DLQ as DeadLetterQueueService
    participant DB as MySQL Database

    Horizon->>Job: Dequeue ProcessWebhookJob
    Job->>DB: Update status = processing, retry_count++
    Job->>Service: Execute payload processing logic
    alt Processing Succeeded
        Service-->>Job: Success
        Job->>DB: Update status = completed, processed_at = now()
    else Processing Failed (Attempt < 4)
        Service-->>Job: Throw Exception
        Job-->>Horizon: Retry with Exponential Backoff [10s, 30s, 90s, 300s]
    else Retries Exhausted (Attempt == 4)
        Service-->>Job: Throw Permanent Exception
        Job->>Job: failed(Throwable $e)
        Job->>DB: Update status = failed
        Job->>DLQ: captureFailedJob(WebhookEvent, Exception)
        DLQ->>DB: DeadLetterQueueEvent::create(status=unresolved)
    end
```

---

## 3. Dead-Letter Queue (DLQ) Alerting & Manual Replay

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Admin User (Livewire UI)
    participant UI as Livewire DLQ Manager Component
    participant Service as DeadLetterQueueService
    participant Horizon as Horizon Queue
    participant DB as MySQL Database

    Admin->>UI: Click "Replay" on DLQ Event #123
    UI->>Service: replayEvent(dlqEvent, "admin_ui")
    Service->>DB: Reset WebhookEvent (status=pending, retry_count=0)
    Service->>DB: Update DLQEvent (status=replayed, replayed_at=now())
    Service->>Horizon: ProcessWebhookJob::dispatch(webhookEvent)
    Horizon-->>UI: Job Re-queued
    UI-->>Admin: Display Success Banner Notice
```
