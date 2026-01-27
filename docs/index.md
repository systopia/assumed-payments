# de.systopia.assumedpayments

CiviCRM extension to **create “assumed payments”** for recurring contributions in cases where a contribution instance is missing or remains “open” within a configured date window.

The extension provides:

- A **settings form** to configure date range, “open” contribution statuses, batch size, and default dry-run behavior.
- An **APIv4 action** `AssumedPayments.schedule` which identifies relevant recurring contributions and enqueues work items.
- A **Queue worker** which ensures a pending contribution instance exists and then creates a payment + flags the resulting transaction as “assumed”.
- An **APIv3 Scheduled Job** `Job.assumedpayments_schedule` which delegates to the APIv4 scheduler and then runs the queue.

---

## What “assumed payments” means here

For each selected `civicrm_contribution_recur` record, the worker:

1. Ensures there is a **Pending** contribution instance for the recur (reuses an existing Pending instance if present; otherwise creates one from the recur).
2. Ensures we do **not** already have an assumed payment for that contribution (checked via a custom field on `FinancialTrxn`).
3. Creates a **Payment** (`Payment.create`) for the contribution amount.
4. Sets a custom boolean flag (`is_assumed`) on the latest related `FinancialTrxn`.

This produces a payment trail that can be filtered/recognized as “assumed”.

---

## Configuration

### Settings UI

The settings form is implemented at:

- `CRM/AssumedPayments/Form/AssumedPayments.php`
- template: `templates/CRM/AssumedPayments/Form/AssumedPayments.tpl`

Settings currently in use by the scheduler:

- `assumedpayments_date_from` / `assumedpayments_date_to`  
  Absolute date window (stored normalized as `YYYY-MM-DD`).
- `assumedpayments_contribution_status_ids`  
  Contribution statuses considered “open”.
- `assumedpayments_batch_size`  
  Maximum number of recurs queued per run.
- `assumedpayments_dry_run_default`  
  Default dry-run state if not overridden via API/job params.

> Note: there is also a setting definition `assumedpayments_relative_date_filter` in `settings/assumedpayments.setting.php`, but the current scheduler implementation uses the absolute `*_date_from/to` settings.

---

## Scheduled Job (APIv3)

File: `api/v3/Job/AssumedpaymentsSchedule.php`

API name/action:

- Entity: `Job`
- Action: `assumedpayments_schedule`

Behavior:

- Builds APIv4 params from job params (if provided)
- Calls `AssumedPayments.schedule` (APIv4) to **fill the queue**
- Runs the queue `de.systopia.assumedpayments` via `CRM_Queue_Runner::runAll()`

### Supported job parameters (optional)

- `fromDate` (string `YYYY-MM-DD` or datetime)
- `toDate` (string `YYYY-MM-DD` or datetime)
- `limit` (int)
- `dryRun` (bool|int)
- `openStatusIds` (array<int> OR JSON string)

The job returns a summary including queued items and processed count.

---

## APIv4

Entity: `Civi\Api4\AssumedPayments` (`Civi/Api4/AssumedPayments.php`)

### `AssumedPayments.getFields`

Provides metadata for:

- `fromDate`
- `toDate`
- `limit`
- `dryRun`
- `openStatusIds`

Implementation: `Civi/AssumedPayments/Api4/Action/AssumedPayments/GetFields.php`

### `AssumedPayments.schedule`

Implementation: `Civi/AssumedPayments/Api4/Action/AssumedPayments/Schedule.php`

Inputs (all optional):

- `fromDate`, `toDate` override configured settings
- `limit` overrides batch size
- `dryRun` overrides default dry-run setting
- `openStatusIds` overrides configured “open” statuses

Output (one row):

- `dryRun`
- `from_date`, `to_date`
- `recur_ids`
- `count`
- `queue_name`
- `queued`

#### Scheduling heuristic (current)

A recur is considered relevant when:

- `recur.next_sched_contribution_date` lies within `[from, to]`
- and `recur.start_date/end_date` do not exclude the window
- and within the window either:
  - **no contribution instance exists**, OR
  - a contribution instance exists with an **“open”** status (configurable)

The scheduler enqueues one queue item per recur id.

---

## Queue Worker

File: `CRM/AssumedPayments/Queue/AssumedPaymentWorker.php`

Queue name: `de.systopia.assumedpayments`

For each item (`recur_id`, `dry_run`):

- Validates recur exists
- Finds an existing **Pending** contribution instance or creates one from the recur
- Checks if an assumed payment was already created (by scanning related transactions for `is_assumed = 1`)
- Creates payment and flags latest `FinancialTrxn`

> Current implementation passes `dry_run` into the queue item payload; the worker currently does not branch on it yet. If you expect dry-run to suppress writes, implement it in `run()` (skip Payment.create + CustomValue.create and only log/return TRUE).

---

## Data Model / Managed Entities

The extension creates a custom field on `FinancialTrxn` to mark assumed payments.

Managed entity file:

- `managed/CustomGroupAssumedPaymentsFinancialTrxn.mgd.php`

Creates:

- CustomGroup `assumedpayments_financialtrxn` extending `FinancialTrxn`
  - table: `civicrm_value_assumedpayment`
- CustomField `is_assumed` (Boolean) on that group

There is also a managed option value to ensure the `cg_extend_objects` option includes `FinancialTrxn`.

---

## Installation / Enablement

1. Install the extension in CiviCRM as usual.
2. Enable the extension.
3. Configure settings in **Assumed Payments Settings** (admin form).
4. Enable the Scheduled Job **Assumed Payments – Schedule** (created inactive by default).

Managed scheduled job definition:

- `managed/AssumedPayments.job.mgd.php`

---

## Development notes

- Queue uses SQL backend (`CRM_Queue_Service` with `type=Sql`).
- Job always runs the queue even if the scheduler returns `queued=0` to avoid leftovers from prior runs.
- `openStatusIds` in the Scheduled Job supports both array and JSON string for compatibility with Scheduled Job parameter storage.

---

## Troubleshooting

- If nothing is queued:
  - Verify `assumedpayments_date_from/to` are set and form a valid window.
  - Verify `assumedpayments_contribution_status_ids` matches your intended “open” statuses.
  - Confirm recurs have `next_sched_contribution_date` within the configured window.

- If payments are created repeatedly:
  - Confirm the custom field exists and is being set on `FinancialTrxn` (`is_assumed = 1`).
  - Confirm `EntityFinancialTrxn` links exist for the contribution and the latest transaction is being flagged.

---

## License

(TODO)
