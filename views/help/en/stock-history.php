<?php /** How to use the Stock movements page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Every time stock changes, the system records one row here, newest on top. Use it to find when goods came in or went out, from which document and who did it. View only; rows cannot be edited or deleted</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Every document that changes stock: goods receipts, shipments, work orders, stock counts, receive / issue, transfers</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Used for checking back, and it is the data of the stock and materials report (รายงานสินค้าและวัตถุดิบ)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Stock movements / stock ledger — the data of the <b>Stock and materials report (s.87(3))</b> that VAT-registered businesses must keep</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post by itself — rows with a value are posted together with the source document</td></tr>
</table>

<h3>Reading the numbers</h3>
<p>Each row is one warehouse + location + lot: <b>Before</b> + <b>Qty</b> (plus = in, minus = out) = <b>On hand</b>, e.g. 0 +80 = 80, then 80 −30 = 50</p>

<h3>Search and filter</h3>
<ul>
    <li>Search box: item code or name, document number, or lot number</li>
    <li>Date range from – to, warehouse and type</li>
    <li>The <b>Columns</b> button adds columns such as time, document line, unit price, unit cost, movement value, remarks</li>
</ul>

<h3>Common types</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">PO receipt</td><td>Goods received against a purchase order</td></tr>
    <tr><td class="text-nowrap">Customer shipment</td><td>From a shipment</td></tr>
    <tr><td class="text-nowrap">Issue to production / Production receipt</td><td>From a work order</td></tr>
    <tr><td class="text-nowrap">Transfer out / Transfer in</td><td>A transfer between warehouses or locations; they come in pairs</td></tr>
    <tr><td class="text-nowrap">Count adjustment</td><td>The difference from a stock count (including opening balances)</td></tr>
    <tr><td class="text-nowrap">Price adjust (out at old cost / in at new cost)</td><td>From a price adjustment; same quantity, only the cost differs</td></tr>
    <tr><td class="text-nowrap">Conversion (material out / finished good in)</td><td>Converting raw materials into FG items</td></tr>
    <tr><td class="text-nowrap">Receipt / Issue</td><td>Done by hand without a purchase or sales document</td></tr>
</table>
<p class="text-secondary">To see the history of one lot: go to <b>Stock on hand</b>, click the row, then open the <b>Stock movements</b> tab</p>
