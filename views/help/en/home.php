<?php /** How to use the home page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A company overview for managers: <b>is there enough cash, are we making a profit, and what needs a decision</b>. It is read-only, with no links to other pages (point at a chart to see the figures). Click <b>Export PDF</b> in the top bar to get this page as A4 landscape — each part shows only when the user may view the screen it comes from, so other departments see only their own parts. All money is in the base currency</p>

<h3>Sections</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Header</td><td>Greeting, date, company name and the <b>open accounting period</b></td></tr>
    <tr><td class="text-nowrap">Cash in bank</td><td>The book balance of every bank account as of today</td></tr>
    <tr><td class="text-nowrap">Sales this month</td><td>The amount before VAT of invoices, credit notes (ใบลดหนี้) and debit notes (ใบเพิ่มหนี้) that are not cancelled (a deposit deducted on an invoice still counts as sales), compared with the same days of last month</td></tr>
    <tr><td class="text-nowrap">Gross profit / Net profit</td><td>From the general ledger, the same way as the income statement: sales revenue (401…) − cost of goods sold (501…) for this month, and income − all expenses since 1 January</td></tr>
    <tr><td class="text-nowrap">Orders to ship</td><td>The value before VAT of what customers have ordered but not yet received — revenue on its way</td></tr>
    <tr><td class="text-nowrap">Receivables / Payables</td><td>What customers have not paid yet (and how much is overdue), and what we have not paid yet, including payment vouchers not yet paid (and how much is due within 7 days)</td></tr>
    <tr><td class="text-nowrap">Stock value</td><td>Quantity on hand × item cost</td></tr>
    <tr><td class="text-nowrap">Sales and gross margin, 12 months</td><td>Bars = monthly sales, dashed line = sales of the same month last year, orange line = gross margin % (right axis) — sales going up while the margin % goes down means too much discounting or rising costs</td></tr>
    <tr><td class="text-nowrap">Receivables aging</td><td>The open balance split by days past due: not due / 1–30 / 31–60 / 61–90 / over 90 days (the red part is money at risk of never being collected)</td></tr>
    <tr><td class="text-nowrap">Cash forecast, 8 weeks</td><td>Starts from the cash in bank today. Green bars = receivables due that week, orange bars = payables and payment vouchers due, blue line = cash left (right axis). Anything already overdue counts in the first week. If cash would go below zero, a red warning shows</td></tr>
    <tr><td class="text-nowrap">Tax and books</td><td>Last month's VAT return PP 30 (ภ.พ.30) (filed or not yet, due on the 15th / online the 23rd), last month's withholding tax (remitted with PND 3/53 (ภ.ง.ด.3/53) by the 7th / online the 15th) and the open accounting period</td></tr>
    <tr><td class="text-nowrap">Needs attention</td><td>Shown only when there is something: overdue receivables, customers over credit limit, items below reorder point / safety stock, late deliveries to customers, late work orders and late vendors (at most 5 of each)</td></tr>
    <tr><td class="text-nowrap">Top customers</td><td>The top 5 of the last 12 months, with gross profit (sales − cost of what was shipped to them) and %</td></tr>
</table>
<p>In a new system with no items yet, an administrator sees 5 starting steps: company information → parts → customers → vendors → users. Demo data (if ticked at install) stays for good; for an empty system, install again — the pending work of each department is in the <b>work queue</b> at the top of document pages and on the <b>Overview</b> page of each module</p>

<h3>ERP at a glance</h3>
<p><b>ERP</b> (Enterprise Resource Planning) is one system in which every department uses the same set of data: the documents of one department become the starting data of another, with no typing twice. PhaiERP has 5 main modules:</p>
<table class="table table-sm">
    <tr><td class="text-nowrap">Purchasing</td><td>Procurement / SRM — vendors, purchase requisitions, purchase orders, goods receipts</td></tr>
    <tr><td class="text-nowrap">Inventory</td><td>Inventory — stock on hand, lots, transfers, stock counts, average cost</td></tr>
    <tr><td class="text-nowrap">Manufacturing</td><td>Manufacturing / MRP — bills of materials, material planning, work orders, QC</td></tr>
    <tr><td class="text-nowrap">Sales</td><td>Sales / CRM — customers, quotations, sales orders, shipments, tax invoices (ใบกำกับภาษี), billing</td></tr>
    <tr><td class="text-nowrap">Accounting</td><td>Finance — payables (AP), receivables (AR), general ledger (GL), fixed assets (FA), tax</td></tr>
</table>

<h3>How documents flow</h3>
<ol>
    <li><b>Buy</b>: purchase requisition → purchase order → goods receipt (stock goes up, Dr Inventory / Cr Goods received not invoiced) → payable from the vendor's tax invoice → payment voucher → vendor payment + withholding tax certificate, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย)</li>
    <li><b>Make</b>: MRP looks at sales orders and stock → work order, material issue, labor booking → finished goods received into stock + QC</li>
    <li><b>Sell</b>: quotation → sales order → shipment (stock goes down, Dr Cost of goods sold) → invoice / tax invoice (receivable set up) → billing note → cash receipt / receipt (ใบเสร็จรับเงิน)</li>
    <li><b>Accounting</b>: every step that has a value posts to the journal by itself → trial balance, input and output VAT reports (PP 30) and PND 3/53</li>
</ol>
<p class="text-secondary">Each module has its own <b>Overview</b> page as the first menu item of its section, and every page has a <b>Help</b> button that tells where that page sits in the flow</p>
