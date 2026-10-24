<?php /** How to use the inventory settings page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Company-wide inventory settings. There is one row; change it and click <b>Save</b></p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set once when you start using the system</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every stock movement (negative stock check) and <b>Work orders</b> (location for production receipts)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Inventory Control — a single row</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Allow negative stock</td><td>Unticked (recommended) = you cannot issue, ship or transfer more than is on hand; the system shows "Not enough stock" under the quantity field · Ticked = you can ship first and receive later; the balance goes negative for a while</td></tr>
    <tr><td class="text-nowrap">Location for production receipts</td><td>A location code; default <code>STOCK</code></td></tr>
</table>
<p>The location the system picks first when receiving goods, issuing materials to production and transferring comes from the <b>Default receiving location</b> of each warehouse, set in <b>System settings › Sites</b></p>
<p class="text-secondary">Item cost is moving average, recalculated on every receipt; there is no option on this page — the number formats of inventory documents (IC receive/issue, TO transfer, PI count) are set in <b>System settings › Document numbering</b></p>

<h3>Document numbers</h3>
<p>The table at the bottom of the page sets the prefix, format and next number of the documents in this module (stock receipts/issues, transfer orders, stock counts); then click the table's <b>Save</b> — this affects new documents only. For details of the formats see System settings → <b>Document numbering</b>, which holds every module in one place</p>
