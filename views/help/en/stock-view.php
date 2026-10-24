<?php /** How to use the Stock line page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>One stock line = one item in a single warehouse + location + lot. The <b>Details</b> tab shows on hand, allocated, unit cost, value, supplier lot and expiry date. The <b>Stock movements</b> tab shows every movement in and out of this line</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Stock on hand</b> (click a row)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Transfer, convert to FG, change status/lot — every movement goes to <b>Stock movements</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Stock card by lot (lot detail)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>A transfer does not post (same inventory account) · Convert to FG: Dr FG inventory / Cr Raw materials</td></tr>
</table>

<h3>Transfer</h3>
<p>Moves goods to another warehouse or location; the lot and expiry date go with them. The value moved = quantity × this line's unit cost</p>
<ol>
    <li>Press <b>Transfer</b>, enter the date (not later than today) and the quantity</li>
    <li>Pick <b>To warehouse</b> and <b>To location</b> (not the same place), then press <b>Save</b></li>
</ol>
<p>You get a TO document number, shown in the history as a "Transfer out" and "Transfer in" pair</p>

<h3>Convert to FG (raw materials only)</h3>
<p>Raw materials cannot be sold directly; they must first be converted into an FG item, e.g. 10 wood boards → 2 shelves</p>
<ol>
    <li>Press <b>Convert to FG</b>, enter the <b>Qty</b> of raw material used, pick <b>Into finished good</b>, and enter the <b>FG qty</b> and <b>To location</b> (in the same warehouse)</li>
    <li>Press <b>Save</b>: the raw material goes out at cost and the FG item comes in at the same value. The FG lot = the document number</li>
</ol>

<h3>Edit</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Lot</td><td>Changes the lot number; the system updates every place that refers to this lot — for an item not yet lot-controlled, giving a lot switches the item to lot control, and from then on shipping, issuing and transferring must pick a lot</td></tr>
    <tr><td class="text-nowrap">Status</td><td>Active · On hold · Expired · Quarantine</td></tr>
    <tr><td class="text-nowrap">Reference (S/N, pallet)</td><td>A serial or pallet number, e.g. <code>PL-0042</code></td></tr>
    <tr><td class="text-nowrap">Qty per pack</td><td>Quantity per box or bundle</td></tr>
    <tr><td class="text-nowrap">Expires, Supplier lot, Lot remark</td><td>These belong to the lot; changing them affects every location of that lot</td></tr>
</table>
<p class="text-secondary">Quantity and cost cannot be changed on this page — quantity changes through documents or <b>Stock counts</b>; cost changes with <b>Price adjust</b> on the <b>Stock on hand</b> page</p>
