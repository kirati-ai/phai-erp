<?php /** How to use the Invoices / credit notes page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>Issue <b>invoices / tax invoices</b> (ใบกำกับภาษี), <b>credit notes</b> (ใบลดหนี้) and <b>debit notes</b> (ใบเพิ่มหนี้) directly, with no shipment — for example service fees, rent, or correcting the price of an earlier invoice (invoices made from shipments are under Sales → <b>Invoices / tax invoices</b>)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>An agreement with a customer that has no shipment (services, rent), or an earlier <b>invoice</b> whose price needs correcting</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Cash receipts</b>; output VAT goes into the <b>Output VAT report</b>, and the document can be sent by e-Tax</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Tax invoice (Revenue Code s.86/4) · Credit note (CN, s.86/10) · Debit note (DN, s.86/9)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Trade receivables / Cr Revenue + Output VAT · a credit note posts the reverse</td></tr>
</table>

<h3>Document types</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Invoice</td><td>Same number series as sales invoices (e.g. IV…), sets up the receivable — debit Receivables / credit Sales revenue (by product line) / credit Output VAT</td></tr>
    <tr><td class="text-nowrap">Credit note</td><td>Number series CN; reduces the receivable, output VAT and revenue — it posts the reverse, the receivable is a negative amount, and you deduct it on the <b>Cash receipts</b> page</td></tr>
    <tr><td class="text-nowrap">Debit note</td><td>Number series DN; posts and sets up the receivable just like an invoice</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Output VAT due</td><td>Invoices (IN) only: <b>On invoicing</b> for sales of goods, or <b>On payment</b> for services (s.78/1) — a service invoice prints as an "Invoice" (not a tax invoice). Its VAT waits in <b>Output VAT not yet due</b> and becomes due in proportion each time a payment comes in. That cash receipt prints as a <b>Receipt / tax invoice</b> (ใบเสร็จรับเงิน / ใบกำกับภาษี) and goes into the output VAT report on the date the money is received (service invoices have no e-Tax XML)</td></tr>
    <tr><td class="text-nowrap">Invoice referenced</td><td>Required for credit/debit notes — an earlier invoice of the same customer that is not cancelled, e.g. <code>IV2026090012</code></td></tr>
    <tr><td class="text-nowrap">Reason</td><td>Required for credit/debit notes. Choose the Revenue Department code that matches the type: CDN… for credit notes (e.g. CDNG02 Goods damaged, CDNS04 Service contract ended), DBN… for debit notes (e.g. DBNG01 Goods price increased) — codes ending in 99 "Other" need the reason written in <b>Remarks</b>. The reason prints on the document and is used in the e-Tax XML</td></tr>
    <tr><td class="text-nowrap">Printed credit/debit notes</td><td>As s.86/10 requires, they show the number and date of the original tax invoice, the <b>Value on the original invoice</b>, the <b>Correct value</b>, the <b>Difference</b> (the amount of this note) and the VAT on the difference — if the original invoice already had credit/debit notes, the original value includes them</td></tr>
    <tr><td class="text-nowrap">Terms / Currency / Tax</td><td>Filled in from the customer and can be changed. Leave Due empty and the system works it out from the terms</td></tr>
    <tr><td class="text-nowrap">Exchange rate</td><td>A foreign currency takes the rate for the date; receivables and accounts are recorded in baht</td></tr>
    <tr><td class="text-nowrap">Date</td><td>Must not be before the open accounting period</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Press <b>Add</b> and choose the type, customer and date (for a credit/debit note, choose the referenced invoice and the reason)</li>
    <li>Enter the lines: choose an item or type a description, then quantity and price — a credit note takes a <b>negative quantity</b> (e.g. -1) with a positive price, so the whole note is negative — billing notes, cash receipts and the output VAT report deduct it by themselves</li>
    <li><b>Save</b>, then <b>Print</b> or <b>Send e-Tax</b> / <b>XML e-Tax</b> (shown once e-Tax is set up)</li>
</ol>

<h3>Status and cancelling</h3>
<p><b>Unpaid</b> → <b>Paid</b> once it is fully received — <b>Cancel document</b> works only when nothing has been received and the invoice is not on an open billing note; the system reverses the entries dated today. A saved document cannot be edited: if it is wrong, cancel it and make a new one, or issue a credit/debit note</p>
