<?php /** How to use the Stock on hand page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>See what is where and how much, by warehouse · location · item · lot, with expiry dates. This page is view-only; balances change through documents: goods receipts, shipments, work orders and stock counts</p>
<p>The menu items <b>Finished goods stock</b> (Sales) and <b>Raw material stock</b> (Purchasing, Manufacturing) open this page with the type already filtered</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Goods receipts</b>, <b>Shipments</b>, <b>Work orders</b>, <b>Stock counts</b> and receive / issue</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Lots are picked in issue and shipment documents, MRP counts it as available, click a row to go to the <b>Stock line</b> view</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Stock on hand report — used with <b>Stock movements</b> to make the stock and materials report (รายงานสินค้าและวัตถุดิบ), s.87(3)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the total value should match the inventory accounts in the <b>Trial balance</b></td></tr>
</table>

<h3>Search and filter</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Search box</td><td>Item number or name, lot number or location (press <code>/</code> to jump to this box)</td></tr>
    <tr><td class="text-nowrap">All warehouses</td><td>Pick one to see a single warehouse</td></tr>
    <tr><td class="text-nowrap">All types</td><td>Finished goods (FG), Raw materials (RM), Fixed assets (FA) — by the item's product line</td></tr>
    <tr><td class="text-nowrap">All product lines</td><td>Pick one to see a single product line</td></tr>
    <tr><td class="text-nowrap">Totals per item</td><td>Switch on = totals per item per warehouse, without splitting by location and lot (rows cannot be clicked in this mode)</td></tr>
</table>

<h3>What you can do next</h3>
<ul>
    <li><b>Click a row</b> to open the stock line view: details, value, the history of that lot, and the buttons <b>Transfer</b>, <b>Edit</b>, <b>Convert to FG</b> (raw materials only) — when opened from the Purchasing, Sales or Manufacturing menu it shows in a window instead</li>
    <li>The <b>Price adjust</b> button changes the unit cost of all the stock of an FG item (this button is not there when raw materials are filtered)</li>
    <li>The table's Excel export icon takes the balances out for further reports</li>
</ul>
<p class="text-secondary">Raw materials (RM) cannot be sold directly; <b>Convert to FG</b> first · items without "Stocked" ticked on the item page have no balance on this page</p>
