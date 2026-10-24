<?php /** How to use the Sales overview page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A summary of sales work: sales this month, open sales orders, quotations waiting for a reply, goods shipped but not yet invoiced, best sellers and the main customers</p>

<h3>Sales in an ERP system</h3>
<p>The sales module is <b>Sales</b> together with <b>CRM</b> (Customer Relationship Management) — the customer register, prices and credit limits, from the quotation all the way to collecting the money</p>

<h3>Document flow</h3>
<ol>
    <li><b>Quotation</b> — won W / lost L; no posting</li>
    <li><b>Sales order</b> — confirms the order and checks the credit limit; no posting</li>
    <li><b>Shipment</b> (delivery order) — stock goes down: Dr Cost of goods sold / Cr Inventory</li>
    <li><b>Invoice / Tax invoice</b> (ใบกำกับภาษี, Revenue Code s.86/4) — sets up the receivable: Dr Accounts receivable / Cr Sales / Cr Output VAT; can be sent as an e-Tax Invoice</li>
    <li><b>Billing note</b> — a trade custom, not a tax document; no posting</li>
    <li>Hand-off to accounting: <b>Cash receipts</b> (Receipt, ใบเสร็จรับเงิน, Revenue Code s.105) Dr Bank / Dr Withholding tax deducted / Cr Accounts receivable → <b>Output VAT report</b></li>
</ol>
<p class="text-secondary">Credit notes (ใบลดหนี้, s.86/10) / debit notes (ใบเพิ่มหนี้, s.86/9) are made in <b>Accounting › Invoices / credit notes</b></p>

<h3>Hand-off to other modules</h3>
<ul>
    <li><b>Manufacturing</b>: open sales orders are the starting demand of MRP</li>
    <li><b>Inventory</b>: shipping takes stock out at the average cost</li>
    <li><b>Accounting</b>: receivables (AR), output VAT and the VAT return PP 30 (ภ.พ.30)</li>
</ul>

<h3>On this page</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Work cards of the module</td><td>Top row: one card for each menu item in the "Transactions" part of this module, telling what that page does and the work from earlier steps waiting for it (the same set as the work queue on that page), with the count and the first 3 document numbers — red means overdue. Click the card title or a document number to open it</td></tr>
    <tr><td class="text-nowrap">Sales this month</td><td>The amount before VAT of invoices that are not cancelled, compared with the same days of last month</td></tr>
    <tr><td class="text-nowrap">Open sales orders</td><td>Sales orders still open, and how many are past their delivery date</td></tr>
    <tr><td class="text-nowrap">Quotations awaiting reply</td><td>Quotations waiting for the customer's answer</td></tr>
    <tr><td class="text-nowrap">Shipped, not yet invoiced</td><td>Shipments with no invoice yet — click to issue the invoice</td></tr>
    <tr><td class="text-nowrap">Latest sales orders</td><td>The 6 latest orders with their status</td></tr>
    <tr><td class="text-nowrap">Best sellers / Top customers</td><td>The top 5 of this year</td></tr>
</table>
