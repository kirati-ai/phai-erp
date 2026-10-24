<?php /** How to use the Sales settings page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Settings used for sales across the whole company</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set once when you start, together with the credit limits in <b>Customers</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Saving <b>Sales orders</b> (a warning when over the credit limit)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Sales control — credit control</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post</td></tr>
</table>

<h3>Check credit limit</h3>
<p>When it is on, saving a sales order adds up the customer's amounts:</p>
<ul>
    <li>Receivables still unpaid</li>
    <li>Amounts ordered but not yet invoiced (including VAT, in baht)</li>
    <li>The sales order being saved</li>
</ul>
<p>If the total is over the customer's <b>Credit limit</b>, a warning shows, e.g. "Over credit limit (limit 100,000.00, used 125,000.00)" — the sales order is still saved and can be shipped as normal; there is no approval step</p>
<p>When it is off, the system does not check credit limits at all</p>
<p class="text-secondary">Customers whose limit is 0 are not checked. Set the limit on the <b>Customers</b> page, Trading tab</p>

<h3>Document numbers</h3>
<p>The table at the bottom of the page sets the prefix, format and next number of the documents in this module (quotations, sales orders, shipments, sales returns, invoices, credit notes (ใบลดหนี้), debit notes (ใบเพิ่มหนี้), billing notes); then press <b>Save</b> of the table — it affects new documents only. See the formats in detail in System settings → <b>Document numbering</b>, which has every module in one place</p>
