<?php /** How to use the bills of materials page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Set which <b>Materials</b> a manufactured item uses and how much, and which <b>Steps</b> it goes through, and see an estimated cost per unit — work orders and MRP use this structure to work out the materials to issue</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Items</b> and <b>Work centers</b> (labor, machine and overhead rates)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Work orders</b> (materials to issue) and <b>Material planning (MRP)</b> (exploding the requirements)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Bill of Materials (BOM) + Routing — master data that MRP needs</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the cost per unit is an estimate (materials + labor + overhead)</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Click an item in the list on the left (only items that already have a structure), or type a code in the <b>Item made</b> field at the top to start a new one</li>
    <li><b>Materials</b> tab: enter the material code, the <b>Qty per</b> unit and the <b>Scrap %</b> — click <b>Add line</b> or press Enter in the last field to start a new row</li>
    <li><b>Steps</b> tab: enter the op number (e.g. 10, 20, 30), the work center, the operation, the setup time and the run time per unit</li>
    <li>Click <b>Save</b> once to save both tabs</li>
</ol>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Qty per</td><td>Quantity of the material per 1 unit of the item, in the material's unit, e.g. 1 table uses <code>4</code> table legs</td></tr>
    <tr><td class="text-nowrap">Scrap %</td><td>Added on top of the quantity used, e.g. uses 2 kg with 5% scrap = issue 2.1 kg per unit</td></tr>
    <tr><td class="text-nowrap">⋯ (end of row)</td><td>Opens the <b>From</b> / <b>Until</b> and <b>Remarks</b> fields — use them when a material changes on a date; a work order picks only the rows valid on its start date. Empty = always valid</td></tr>
    <tr><td class="text-nowrap">Setup (h)</td><td>Time to prepare the machine each run; not counted in the cost per unit on this page</td></tr>
    <tr><td class="text-nowrap">Run per unit (h)</td><td>Hours per 1 unit, e.g. 15 minutes = <code>0.25</code></td></tr>
</table>

<h3>How the cost/unit is worked out</h3>
<ul>
    <li><b>Materials</b> = qty per unit × (1 + scrap %) × the material's current cost</li>
    <li><b>Steps</b> = run time per unit × (labor + machine + overhead rate per hour) of the work center</li>
    <li><b>Total cost/unit</b> on the right of the tab = materials + steps — an estimate; the actual cost comes from the work order</li>
</ul>

<h3>Notes</h3>
<ul>
    <li>You cannot add a material that makes the structure loop back to itself (e.g. A uses B and B uses A)</li>
    <li>Delete every row in a tab and save = delete that part of the structure. The <b>Delete</b> button at the top deletes both the materials and the steps of this item</li>
    <li>Changing a structure does not affect work orders that have already been released</li>
</ul>
<p class="text-secondary">Set the hourly rates in <b>Work centers</b> first, so the cost of the Steps tab is not zero</p>
