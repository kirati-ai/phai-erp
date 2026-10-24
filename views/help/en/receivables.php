<?php /** How to use the Open receivables page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The <b>receivables still unpaid</b> per customer, split by <b>age</b> counted from the due date to today, for chasing payment. Amounts are in baht, sorted from the customer who owes the most</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Invoices / tax invoices</b> (ใบกำกับภาษี) and <b>Invoices / credit notes</b> not yet fully paid</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Billing notes</b> and <b>Cash receipts</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>AR aging report — not a document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it is a report</td></tr>
</table>

<h3>Columns</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Not due</td><td>Not yet at the due date (or due today)</td></tr>
    <tr><td class="text-nowrap">1–30 / 31–60 / 61–90</td><td>How many days past the due date</td></tr>
    <tr><td class="text-nowrap">&gt; 90</td><td>More than 90 days past due — follow up urgently</td></tr>
    <tr><td class="text-nowrap">Total</td><td>Everything the customer owes (unused credit notes already deducted)</td></tr>
</table>

<h3>How to use it</h3>
<ol>
    <li>Search by customer code or name</li>
    <li>Click a customer row to see the unpaid invoices one by one, with the days past due — click a number to open that invoice</li>
    <li>When the money comes in, press <b>New receipt</b> to go to the <b>Cash receipts</b> page</li>
</ol>
<p class="text-secondary">The due date comes from the invoice (worked out from the customer's terms) — billing notes for the customer are made under Sales → <b>Billing notes</b></p>
