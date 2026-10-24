<?php /** How to use the Credit terms page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The list of <b>Credit terms</b> (payment terms), such as cash or 30 days credit — set them on customers and vendors, new documents pick them up, and the system uses them to work out the <b>Due date</b> of invoices and payables</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The payment agreements with customers and vendors</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Default of <b>Customers</b> and <b>Vendors</b> → the due date of invoices and payables</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Credit terms — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — only used to work out due dates</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>e.g. <code>COD</code>, <code>30DAYS</code>, <code>60DAYS</code></td></tr>
    <tr><td class="text-nowrap">Terms name</td><td>The text printed on documents, e.g. "30 days credit"</td></tr>
    <tr><td class="text-nowrap">Credit (days)</td><td>Number of days from the document date to the due date — <code>0</code> = due the same day (cash)</td></tr>
    <tr><td class="text-nowrap">Early payment within (days) / Early payment discount (%)</td><td>Kept for reference only; the system does not give the discount by itself. You can leave them at 0</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when no longer used — it will not be offered in new lists</td></tr>
</table>

<h3>Example</h3>
<p>An invoice dated 10/10/2026 with terms of 30 days credit → due 09/11/2026</p>
<p class="text-secondary">Terms that a customer or vendor still uses cannot be deleted; turn off "Active" instead</p>
