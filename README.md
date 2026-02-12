<div style="text-align: center;">

[![Generic badge](https://img.shields.io/badge/Maintained-Actively-green.svg)](https://shields.io/)
[![Generic badge](https://img.shields.io/badge/Maintainer-SYSTOPIA-blue.svg)](https://github.com/systopia)
[![Generic badge](https://img.shields.io/badge/License-AGPL%203.0-yellow.svg)](https://github.com/systopia)

</div>

# Assumed Payments

CiviCRM extension to automatically create **assumed payments** for recurring contributions where payments are missing or
still open within a defined date range.

---

## Description
Assumed Payments identifies recurring contributions with missing or still open payments within a defined date range and creates corresponding assumed payment transactions.

It solves the problem of incomplete recurring contribution accounting where payments are delayed, missing, or intentionally assumed.

Designed for organizations running CiviCRM with recurring contributions that require structured financial reconciliation.

## Features

- Identifies relevant `ContributionRecur` records within a configured date window
- Creates queue items for each eligible recur
- Ensures a **Pending** contribution instance exists
- Creates a **Payment** for the contribution amount
- Marks the resulting financial transaction as *assumed*
- Marks the contribution as **Completed**

## Quickstart

Enable the extension in CiviCRM.


## Documentation

The settings allow you to define:

- Installation & Requirements → see /docs/installation.md
- Configuration → see /docs/configuration.md
- Architecture → see /docs/architecture.md
- API Reference → see /docs/api.md
- Development Guide → see /docs/development.md


## Status

<span style="color: #28a745; font-weight: 600;">**Actively maintained.**</span> Production-ready for structured recurring contribution reconciliation.

## Notes

- Assumed payments are flagged via a custom field on `FinancialTrxn`.
- The extension is designed to be idempotent and avoids duplicate assumed payments.

## Contributing

Contributions are happily welcome. see more 

## Support / Issues / Security

- We need your support. -> Read more here.
- Please report issues or security concerns -> here.
