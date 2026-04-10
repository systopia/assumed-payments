# Assumed Payments

CiviCRM extension to create **assumed payments** for recurring contributions that appear unpaid within a configured date
window.

The extension provides:

- A **settings form** to configure date range, "unfinished" contribution statuses, batch size, and default dry-run behavior.
- An **APIv4 action** `AssumedPayments.schedule` which identifies relevant recurring contributions and enqueues work items.
- A **Queue worker** which ensures a pending contribution instance exists and then creates a payment + flags the resulting transaction as “assumed”.
- An **APIv3 Scheduled Job** `Job.assumed_payments_schedule` which delegates to the APIv4 scheduler and then runs the queue.
- Built-in **SearchKit saved searches** for reviewing assumed payments and inspecting queue items.

---

## Overview

Recurring contributions can become inconsistent over time. Expected payments may be missing or remain in an "unfinished" state. This extension exists to restore a coherent financial record by programmatically completing those gaps in a controlled and traceable way.

The extension:
- identifies relevant `ContributionRecur` records
- enqueues them for processing
- creates a payment for the related contribution
- flags the resulting `FinancialTrxn` with `is_assumed`

If configured, the processed contribution is also moved into a final contribution status.

---

## Configuration

Different organisations have different definitions of what constitutes a "missing" or "unpaid" contribution. The configuration allows adapting the extension’s behavior to those definitions without changing code.

The scheduler uses the following settings:

- `assumed_payments_from_date`
- `assumed_payments_to_date`
- `assumed_payments_contribution_status_ids`
- `assumed_payments_payment_instrument_ids`
- `assumed_payments_financial_type_ids`
- `assumed_payments_batch_size`
- `assumed_payments_final_contribution_state`

---

## API

### `AssumedPayments.schedule`

This action focuses purely on identifying which recurring contributions require intervention.

**Parameters (optional):**

- `fromDate`
- `toDate`
- `batchSize`
- `openStatusIds`
- `paymentInstrumentIds`
- `financialTypeIds`

**Returns:**

- `from_date`
- `to_date`
- `recur_ids`
- `count`
- `queue_name`
- `queued`

A recur is considered relevant if:

- `next_sched_contribution_date` is within the configured window
- `start_date` / `end_date` do not exclude that window
- it matches the optional payment instrument / financial type filters
- and within the window either:
  - no contribution exists, or
  - at least one contribution exists in a configured “unpaid” status

---

### `AssumedPayments.runJob`

This action represents the full automation path, combining detection and execution in a single step.

job flow:

1. schedule items
2. run the queue
3. return a summary

**Parameters (optional):**

- `fromDate`
- `toDate`
- `batchSize`
- `openStatusIds`
- `paymentInstrumentIds`
- `financialTypeIds`

---

## Scheduled Job

Recurring data inconsistencies typically need continuous correction rather than one-time fixes. The scheduled job allows running this process regularly without manual intervention.

`Job.assumed_payments_schedule` is a thin APIv3 wrapper around `AssumedPayments.runJob`.

**Supported parameters:**

- `fromDate`
- `toDate`
- `batchSize`
- `openStatusIds`
- `paymentInstrumentIds`
- `financialTypeIds`

**Note:** List parameters support both arrays and JSON strings.

---

## Worker

All actual data modifications are isolated in the queue worker. This ensures that processing is scalable, repeatable, and safe to retry without unintended side effects.

For each queued recur:

1. load latest contribution instance
2. skip if an assumed payment already exists
3. create contribution if none exists
4. create payment
5. flag `FinancialTrxn.is_assumed`
6. optionally update contribution status

**Note:** The worker uses the **latest contribution instance**, not a specific pending one.

---

## Data Model

Automatically created payments need to remain distinguishable from real transactions. The additional data model ensures that all generated records can be clearly identified and audited.

The extension adds a custom field on `FinancialTrxn`:

- Custom Group: `assumed_payments_financialtrxn`
- Custom Field: `is_assumed`

---

## SearchKit

Visibility is critical when automating financial data changes. The provided searches make it easy to review results and monitor ongoing processing.

The extension ships with two SearchKit saved searches:

### Contributions with assumed payments

Shows contributions linked to a `FinancialTrxn` with `assumed_payments_financialtrxn.is_assumed = TRUE`. This can be
used to review contributions that were processed by the extension.

### Queue items for unpaid recurs

Shows queue items belonging to the assumed payments queue. This can be used to inspect remaining or scheduled queue
items.
