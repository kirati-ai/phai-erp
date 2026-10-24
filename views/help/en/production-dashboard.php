<?php /** How to use the Manufacturing overview page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A summary of production work: <b>what to make, how many, and for whom</b> (from sales orders), the work orders in progress, those waiting to start, the work finished this month and the quality check results</p>

<h3>Manufacturing in an ERP system</h3>
<p>The manufacturing module is <b>Manufacturing</b> together with <b>MRP</b> (Material Requirements Planning) — it sets up the bills of materials, plans what to buy or make, then controls production and the actual cost (materials + labor + overhead)</p>

<h3>Document flow</h3>
<ol>
    <li><b>Setup data</b>: bill of materials (BOM) + routing, work centers, machines, employees and item-site planning (safety stock)</li>
    <li><b>Material planning (MRP)</b> — looks at open sales orders, stock and what is already on order, then suggests purchase requisitions or work orders to create — the planner clicks <b>Run MRP</b> to get planned orders, action messages and an MRP grid per item</li>
    <li><b>Work order</b> status F (can be edited) → R (released for production)</li>
    <li>Material issue — Dr Work in progress (WIP) / Cr Raw materials</li>
    <li>Labor booking — Dr Work in progress / Cr Direct labor / Cr Applied manufacturing overhead</li>
    <li>Receipt into stock + quality check (QC) — Dr Finished goods inventory / Cr Work in progress</li>
    <li>Close (C) — the WIP left over is the production variance and goes to cost of goods sold</li>
</ol>

<h3>Hand-off to other modules</h3>
<ul>
    <li><b>Sales</b>: open sales orders are the demand for MRP, and finished goods are ready for shipping</li>
    <li><b>Purchasing</b>: MRP creates purchase requisitions for missing materials</li>
    <li><b>Inventory</b>: material issues and output receipts always go through stock</li>
    <li><b>Accounting</b>: production costs post to the journal with type WO</li>
</ul>

<h3>On this page</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Work cards of the module</td><td>Top row: one card for each menu item in the "Transactions" part of this module, telling what that page does and the work from earlier steps waiting for it (the same set as the work queue on that page), with the count and the first 3 document numbers — red means overdue. Click the card title or a document number to open it</td></tr>
    <tr><td class="text-nowrap">Work orders in progress</td><td>Work orders in status R and how many are overdue</td></tr>
    <tr><td class="text-nowrap">Firmed work orders</td><td>Work orders in status F that have not started yet</td></tr>
    <tr><td class="text-nowrap">Completed this month</td><td>Work orders closed since the start of the month</td></tr>
    <tr><td class="text-nowrap">QC pass rate this month</td><td>QC records with the result PASS ÷ all QC records of this month</td></tr>
    <tr><td class="text-nowrap">To make for sales orders</td><td>Open sales order lines of items made in-house where the stock is not enough — the stock goes first to the lines the customer wants first, and the shortage is matched to open work orders by due date (the same matching as the shipping queue on the Shipments page): <b>Work order needed</b> + quantity = no work order makes it yet (open one from the queue on the Work orders page) · <b>Will finish late</b> = the work order finishes after the date the customer wants or is already past its due date; push it along · <b>On time</b> — with no rows, this part is hidden</td></tr>
    <tr><td class="text-nowrap">Work orders in progress</td><td>Ordered, done and scrap quantities of each order</td></tr>
    <tr><td class="text-nowrap">Latest QC results</td><td>The 5 latest QC records</td></tr>
</table>
