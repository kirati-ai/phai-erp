<?php /** How to use the stock and materials report page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>Stock card</b> for a date range, item by item: the opening balance, every receipt/issue with its quantity, unit cost, value and running balance — the balance on the last line of each item always matches <b>Stock on hand</b> on that date</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Every document that changes stock: goods receipts, shipments, production issues/receipts, manual receive/issue, transfers, counts, price adjustments (read from <b>Stock movements</b>)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Kept for the tax officers to inspect, and used to reconcile stock on hand with the inventory account in the financial statements</td></tr>
    <tr><td class="text-nowrap">Document name</td><td><b>Stock and materials report (รายงานสินค้าและวัตถุดิบ)</b>, Revenue Code s.87(3), which a VAT-registered business must keep, item by item, showing receipts, issues and balance</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting (a report) — each entry was already posted by its source document</td></tr>
</table>

<h3>How to use</h3>
<ol>
    <li>The page opens with <b>All items</b> that have an opening balance or movements this month, sorted by code — to see one item, pick it in the item field (pick All items or press Delete to go back). You can filter by <b>Type</b> FG/RM (by product line) and by warehouse (empty = all warehouses)</li>
    <li>Pick the <b>Month</b> with ‹ month › (gives the 1st to the end of the month) or enter a date range yourself (the month label then shows Custom range)</li>
    <li>Each item starts with a row with the item name, then the <b>Opening balance</b> before the start date, every movement by date, and a total row for in, out and closing — at the very end is <b>All items (value)</b> (value only, because the units differ), used to compare with the inventory account</li>
    <li>Click <b>Print / PDF</b> for an A4 landscape copy with the filters you picked</li>
</ol>
<p class="text-secondary">When viewing all warehouses together, a transfer shows both the transfer out and the transfer in; the total does not change — values use the unit cost at the time of the movement (average cost). A price adjustment shows as an issue at the old price + a receipt at the new price</p>
