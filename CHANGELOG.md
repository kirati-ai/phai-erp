# Changelog

## 1.0.0

First release.

- **Core** — router with attribute routes, JSON API for every screen, CSRF, sessions with login lock-out, role permissions per screen and action, audit log, Thai/English language files, web installer.
- **Master data** — parts, part groups/types, product lines, units, customers, vendors, currencies and exchange rates, taxes, payment terms, Thai addresses (province, district, sub-district).
- **Inventory** — stock by site/location/lot, receive/issue/adjust, transfers, stock counts, history, average costing, reason codes.
- **Purchasing** — requisitions, purchase orders with approval, receipts with tolerance, landed costs.
- **Sales** — price lists, quotations, sales orders with credit limit, shipments, tax invoices, billing notes, printed documents with amounts in Thai words.
- **Production** — BOMs, routings, work centers, machines, employees, work orders with material issue, labor and receipt, QC records, MRP.
- **Accounting** — chart of accounts, automatic posting from all documents, manual journals with reversal, receivables ageing, cash receipts, payables, payments with withholding tax and 50 Tawi certificates, trial balance with ledger drill-down, period close.
- **AI** — ask questions about your data (Claude or Gemini); read-only, limited to what the user may see; usage and cost log.
- **Installer** — five-step wizard (server, database, company with logo and address pickers, administrator, confirm); company logo and address (`ad_mstr`) editable on the company screen.
- **Home** — dashboard with key figures, sales chart, top customers and pending work, limited to what each user may open; redesigned sign-in page and collapsible sidebar.
- **Sample data** — installer option and `php bin/phai demo`; it stays once loaded (install again for an empty system).
- **Document numbers** — per-document format: year-month + 4 digits (default, restarts monthly), short year-month, year + 5 digits, or 6 digits.
- **Printing** — one A4 template with logo for all documents; real PDF files made on the server with Edge/Chrome when available.
- **Security** — automated checks for login and permission on every route, CSRF, SQL injection, stored XSS, file exposure and session handling.

Database: 96 tables (`database/schema/phaierp_schema.sql`).
