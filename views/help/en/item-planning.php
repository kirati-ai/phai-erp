<?php /** How to use the Item planning by site page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>Set the <b>Safety stock</b> and planning values of each item <b>by site/warehouse</b>. One row = one item at one site. You do not need to set every item, only the ones that must keep a reserve</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Items</b> and <b>Sites</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Material planning (MRP)</b> tops up the safety stock, and the Below safety stock card in the <b>Inventory overview</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Item planning by site (Item Planning / Item-Site) — base data for MRP</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Site</td><td>A site/warehouse code from the <b>Sites</b> page, e.g. <code>MAIN</code></td></tr>
    <tr><td class="text-nowrap">Item number</td><td>An item from the <b>Items</b> page</td></tr>
    <tr><td class="text-nowrap">Status</td><td>Must be <b>Active</b> for the safety stock to be counted</td></tr>
    <tr><td class="text-nowrap">Safety stock</td><td>The quantity you always want to keep — <b>Material planning (MRP)</b> counts it as extra demand (all active sites together) and the <b>Inventory overview</b> shows items below it</td></tr>
    <tr><td class="text-nowrap">Reorder point / Reorder quantity</td><td>Kept as reference for the buyer; the system does not calculate from these fields yet</td></tr>
    <tr><td class="text-nowrap">Other fields</td><td>Buyer, sourcing policy, manufacturing/purchasing lead time, issue/receiving location — reference data (the lead time MRP uses is the <b>Lead time (days)</b> on the item page)</td></tr>
</table>

<h3>Set a safety stock</h3>
<ol>
    <li>Click <b>Add</b> and enter the site and item number</li>
    <li>Enter the <b>Safety stock</b>, e.g. <code>10</code>, then click <b>Save</b></li>
    <li>Go to <b>Material planning (MRP)</b> and click <b>Run MRP</b>: the system suggests purchases/work orders so stock does not fall below the safety stock</li>
</ol>
