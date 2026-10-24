<?php /** How to use the Billing notes page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>Gather one customer's unpaid invoices on a single sheet and print it to bill them; the customer signs for it and sets a payment date</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Unpaid <b>invoices / tax invoices</b> (ใบกำกับภาษี) of one customer</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Cash receipts</b> on the agreed payment date</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Billing note / statement of account — a Thai trade custom, not a tax document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the receivable was set up when the invoice was issued</td></tr>
</table>

<h3>Make a billing note</h3>
<ol>
    <li>Tick invoices in <b>Invoices to bill</b> above the list (one customer; red row = past due) and press <b>Make billing note</b> — or press <b>Add</b> and choose the <b>Customer</b>; the table shows the unpaid invoices that are not on another billing note</li>
    <li>Enter the <b>Date</b> and the <b>Payment date</b> (the day the customer will pay), plus remarks if any</li>
    <li>Tick the invoices to bill (the box in the table header ticks them all) and press <b>Save</b></li>
    <li>Press <b>Print</b> for an A4 billing note with the amount in words and signature boxes for Received by and Billed by</li>
</ol>

<h3>Good to know</h3>
<ul>
    <li>The amount on the billing note = what is still owed on each invoice on the day it is made (after any part payments)</li>
    <li>One billing note must be in one currency</li>
    <li>An invoice can be on only one open billing note, and that invoice cannot be cancelled meanwhile</li>
    <li>Receive the money on the <b>Cash receipts</b> page (Accounting → Receivables (AR)), not on this page</li>
</ul>

<h3>Status</h3>
<ul>
    <li><b>Open</b> — can be cancelled with <b>Cancel document</b> (e.g. to bill again); its invoices can then be picked again</li>
    <li><b>Paid</b> — set by itself once every invoice on the billing note is fully paid</li>
    <li><b>Cancelled</b></li>
</ul>
<p class="text-secondary">Once saved, the lines of a billing note cannot be changed; if it is wrong, cancel it and make a new one</p>
