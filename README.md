# de.systopia.assumedpayments

CiviCRM extension to automatically create **assumed payments** for recurring contributions where payments are missing or
still open within a defined date range.

## What it does

- Identifies relevant `ContributionRecur` records within a configured date window
- Creates queue items for each eligible recur
- Ensures a **Pending** contribution instance exists
- Creates a **Payment** for the contribution amount
- Marks the resulting financial transaction as *assumed*

## Installation

1. Install and enable the extension in CiviCRM.
2. Configure **Assumed Payments Settings** under *Administer → System Settings*.
3. Activate the scheduled job **“Assumed Payments – Schedule”**.

## Configuration (brief)

The settings allow you to define:

- Date range (from / to)
- Contribution statuses considered “open”
- Batch size per run
- Default dry-run behavior

## Execution

The scheduled job fills a queue and immediately processes it.  
Alternatively, the scheduling logic can be triggered via API.

## Notes

- Assumed payments are flagged via a custom field on `FinancialTrxn`.
- The extension is designed to be idempotent and avoids duplicate assumed payments.
