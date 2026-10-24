<?php /** How to use the Invoices / tax invoices page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Invoices / tax invoices (ใบกำกับภาษี) issued from shipments (one per shipment, at the sales order prices). Once issued, the system sets up the receivable and posts receivables, sales and output VAT by itself</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Shipments</b> waiting to be invoiced (prices from the <b>Sales order</b>)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Billing notes</b> → <b>Cash receipts</b> (Receipt, ใบเสร็จรับเงิน) · <b>Output VAT report</b> · <b>e-Tax Invoice (email)</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Invoice / tax invoice — a full tax invoice (ใบกำกับภาษีเต็มรูป) under Revenue Code s.86/4</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Accounts receivable / Cr Sales (by product line) / Cr Output VAT</td></tr>
</table>

<h3>Issue an invoice</h3>
<ol>
    <li>Click <b>Confirm shipment</b> on the shipment, and the system opens an invoice filled in from that shipment straight away</li>
    <li>Or click <b>Invoice</b> on a row of <b>Shipments to invoice</b> above the list, or click <b>Add</b> and choose the shipment</li>
    <li>The new invoice page has the items, the quantities (as shipped, cannot be changed) and the sales order prices filled in — you can change <b>Date</b>, <b>Due</b>, <b>Customer PO</b>, <b>Remarks</b>, <b>Unit price</b> and <b>Disc %</b>, then click <b>Save</b></li>
    <li>Open the invoice and click <b>Print</b> to get the A4 invoice / tax invoice</li>
</ol>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Due</td><td>Invoice date + the credit terms of the sales order, e.g. 30 days</td></tr>
    <tr><td class="text-nowrap">Paid</td><td>The amount received so far, updated from the <b>Cash receipts</b> page</td></tr>
    <tr><td class="text-nowrap">Less deposit</td><td>If the sales order has a <b>Deposit receipt</b>, the tax invoice deducts the deposit (before VAT) from the value of the goods by itself, shown as Subtotal − Less deposit = Value after deposit, and charges VAT on that part only (the VAT on the deposit was already paid with the deposit receipt)</td></tr>
    <tr><td class="text-nowrap">Foreign currency</td><td>A tax invoice must show the VAT in baht (s.86/4), so the printed invoice ends with the exchange rate, VAT (THB) and total (THB), and the amount in words is in baht</td></tr>
</table>
<p>An invoice cannot be edited. If the price or quantity is wrong, cancel it and issue a new one, or issue a credit note (ใบลดหนี้) / debit note (ใบเพิ่มหนี้) under the Accounting → Receivables (AR) menu</p>

<h3>Status</h3>
<ul>
    <li><b>Unpaid</b> — waiting for payment; can be put on a <b>Billing note</b></li>
    <li><b>Paid</b> — fully received on the <b>Cash receipts</b> page (Accounting → Receivables (AR))</li>
    <li><b>Cancelled</b> — click <b>Cancel document</b>; only while nothing has been paid and the invoice is not on a billing note still waiting for payment (cancel the billing note first). The system reverses the entries dated today, and the shipment goes back to waiting for an invoice</li>
    <li><b>Delete</b> — a cancelled invoice can be deleted when it is the latest number of its series, has not been sent by e-Tax, no billing note / receipt / credit note refers to it, and it is in a period that is still open — the entries are deleted and the number goes back to the next invoice (for a wrong invoice cancelled right after issuing). Otherwise it must be kept as a cancelled invoice, because an issued tax invoice must be kept with its number</li>
</ul>

<h3>e-Tax</h3>
<ul>
    <li><b>Send e-Tax</b> — shown when e-Tax Invoice by Email is enabled in <b>e-Tax / email settings</b>. It sends the PDF to the customer's email with a copy to ETDA. A cancelled invoice can be sent too, as a cancellation notice</li>
    <li><b>XML e-Tax</b> — shown once a certificate is uploaded; downloads the signed XML file</li>
</ul>
