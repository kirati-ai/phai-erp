<?php /** How to use the Receive / issue and price adjust page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Change stock without a purchase or sales document. It has 4 tabs: <b>Receive</b>, <b>Issue</b>, <b>Convert to FG</b>, <b>Price adjust</b>. Saving gives an IC document number and posts for you. It is usually opened from the <b>Price adjust</b> button on the <b>Stock on hand</b> page</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The <b>Price adjust</b> button in <b>Stock on hand</b>, or <b>Convert to FG</b> on the <b>Stock line</b> page — reasons from <b>Reason codes</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Stock movements</b> and the journal (type IC)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>General receipt / issue (unplanned receipt / issue) and inventory revaluation, number series IC</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Receive: Dr Inventory / Cr the reason code's account (none chosen = Inventory adjustment) · Issue: the other way round · Price adjust: the difference goes to the same account · Convert to FG: Dr FG inventory / Cr Raw materials</td></tr>
</table>

<h3>Price adjust (FG items)</h3>
<p>Changes an item's unit cost, e.g. when the old cost is too high. Every warehouse/location/lot that holds the item goes out at the old cost and comes back in at the new cost; the quantity does not change</p>
<ol>
    <li>Enter the date (not later than today). Pick a <b>Reason</b> if you want the difference posted to that reason code's account (none chosen = the Inventory adjustment account)</li>
    <li>Enter the item; the system shows <b>Available</b> and <b>Current cost</b>. Enter the <b>New unit cost</b></li>
    <li>Press <b>Add line</b> if there are several items, then press <b>Save</b></li>
</ol>
<p class="text-secondary">Raw materials (RM) cannot be re-priced; they keep the cost they were received at · the new cost must differ from the current cost, and the item must have stock</p>

<h3>Receive / issue</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Number</td><td>The next number you will get; you can type over it with another number if no one has used it</td></tr>
    <tr><td class="text-nowrap">Reason</td><td>Receive can pick only receipt-type codes, Issue only issue-type codes — it sets the other side of the entry</td></tr>
    <tr><td class="text-nowrap">Lot</td><td>Receive: leave it blank = the document number is used as the lot · Issue: pick a lot that has stock</td></tr>
    <tr><td class="text-nowrap">Unit cost</td><td>Receive: leave it blank = the item's current cost. Entering a value makes the average cost be worked out again</td></tr>
</table>
<p><b>Convert to FG</b>: pick the raw material, location, lot and quantity, then pick <b>Into finished good</b>, <b>FG qty</b> and <b>To location</b> (in the same warehouse). One document can hold several lines — you can also convert one line at a time on the stock line page</p>
<p class="text-secondary">Once saved it cannot be changed or deleted; if it was wrong, make a reversing transaction (receive back, or issue out) — Ctrl+Enter saves</p>
