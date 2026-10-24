<?php /** How to use the Material planning (MRP) page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>Plan what to <b>buy</b> or <b>make</b>, how much and when, so there is enough to ship the sales orders and to issue to work orders — the same way big ERP systems (QAD, SAP) do it: the planner presses <b>Run MRP</b>, the system works through the items one bill-of-materials level at a time and gives <b>planned orders</b> and <b>action messages</b>, then the planner decides what to act on</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Sales orders</b> not yet shipped, <b>Work orders</b> with materials still to issue, stock, <b>Purchase requisitions</b> and <b>Purchase orders</b> not yet received, <b>Bills of materials</b>, the planning data of <b>Items</b> (lead time, order size) and <b>Item-site planning</b> (safety stock)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Planned buy orders → <b>Purchase requisitions</b> → purchase order → goods receipt; planned make orders → <b>Work orders</b> (the materials on the work order are the ones MRP exploded from the same bill of materials)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Material Requirements Planning (MRP) — the result is planned orders, not documents</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>What Run MRP does</h3>
<ol>
    <li>It starts from the top-level items (level 0 = items you sell) and goes down one level at a time through the bills of materials (low-level code)</li>
    <li>For each item: <b>Gross requirements</b> = sales orders not fully shipped + materials that work orders still have to issue + materials for the planned orders of the level above</li>
    <li>It takes off the stock in locations that <b>Count as available</b> (all warehouses together, keeping the safety stock) and the <b>Scheduled receipts</b> (open purchase orders, requisitions and work orders) — an order already placed but arriving late still covers the need first (you get a reschedule-in message), so nothing is ordered twice</li>
    <li>What is still short is the <b>Net requirements</b> → a planned order, its quantity rounded by the order size (lot-for-lot, rounded to a multiple, minimum, maximum per order); its due date = the day of the shortage, and <b>Order by</b> = due date − lead time</li>
    <li>Planned make orders explode their materials through the bill of materials into requirements of the next level down, and this repeats down to the raw materials</li>
</ol>
<p class="text-secondary">The result of a run stays until the next run — when new sales orders come in or documents change, run MRP again (big systems often run it every night). The time of the last run and who ran it show at the top left</p>

<h3>Planned orders</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Order by</td><td>Due date − the item's lead time (days); shows today if that day has already passed</td></tr>
    <tr><td class="text-nowrap">Due</td><td>The date the goods must be there — the <b>Late</b> label = that date has passed</td></tr>
    <tr><td class="text-nowrap">For</td><td>The document or item that causes the shortage, e.g. a sales order number, a work order number or the parent item number</td></tr>
</table>

<h3>Action messages</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Release</td><td>A planned order whose order-by date has come — make the requisition or work order now (red = past the order-by date)</td></tr>
    <tr><td class="text-nowrap">Reschedule in / expedite</td><td>An open purchase order, requisition or work order is due later than the date it is needed — chase the vendor or production to hurry it</td></tr>
    <tr><td class="text-nowrap">Reschedule out</td><td>An open order is not needed until the suggested date — its due date can move out, no need to rush the goods in and store them</td></tr>
    <tr><td class="text-nowrap">Cancel</td><td>An open order has no requirement behind it at all — consider cancelling it or reducing the quantity</td></tr>
</table>

<h3>MRP record per item</h3>
<p>Every item MRP worked on, whether it has enough or not, in level order. Click a row to open it: the top line shows the planning data (level, lead time, order size, safety stock, on hand), then comes a textbook MRP table split into weeks (the <b>Past due</b> column = already overdue)</p>
<table class="table table-sm">
    <tr><td class="text-nowrap">Gross requirements</td><td>Every requirement in that week</td></tr>
    <tr><td class="text-nowrap">Scheduled receipts</td><td>Open purchase orders, requisitions and work orders</td></tr>
    <tr><td class="text-nowrap">Projected on hand</td><td>Balance at the end of the week = previous balance + scheduled receipts + planned order receipts − gross requirements (red = below safety stock)</td></tr>
    <tr><td class="text-nowrap">Net requirements</td><td>What is still short (measured against safety stock) after on-hand stock and scheduled receipts</td></tr>
    <tr><td class="text-nowrap">Planned order receipts</td><td>Planned orders that must arrive in that week</td></tr>
    <tr><td class="text-nowrap">Planned order releases</td><td>The week to place the order (planned order receipts moved back by the lead time). For a made item, this quantity × the bill of materials = the gross requirements of its materials one level down</td></tr>
</table>
<p>Below the table is <b>Where each number comes from (MRP detail)</b>: every line in date order — sales order, issue to work order, goes into planned order, purchase order, requisition, work order, planned order — with the balance after each line</p>

<h3>Steps</h3>
<ol>
    <li>Press <b>Run MRP</b></li>
    <li>Read the <b>Action messages</b>: Release → steps 3–4; reschedule in / reschedule out / cancel → change that purchase order or work order (or tell the vendor)</li>
    <li>Tick planned orders of type <b>Buy</b> and press <b>Requisition the ticked</b> — you get one requisition holding all the lines, priced from the items' cost</li>
    <li>Tick type <b>Make</b> and press <b>Work orders for the ticked</b> — you get one work order per line (or several if it is over the maximum per order) in status Firm, with materials from the same bill of materials that MRP exploded</li>
    <li>Lines that became documents drop off the planned orders, and the MRP table shows them as those documents straight away (scheduled receipt / issue to work order)</li>
</ol>
<p class="text-secondary">Lead time and order size are set on <b>Items</b>; safety stock is set on <b>Item-site planning</b></p>
