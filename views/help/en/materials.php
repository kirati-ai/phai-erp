<?php /** How to use the materials page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The same item register as the <b>Items</b> page, but showing only <b>Raw materials (RM)</b>, i.e. items in a product line of type RM. You can filter by product line next to the search box</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The same register as <b>Items</b>, filtered to product lines of type RM</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Purchase requisitions</b> → <b>Purchase orders</b> → <b>Goods receipts</b> → issued to <b>Work orders</b> by the <b>Bills of materials</b>, or <b>Convert to FG</b> — cannot be sold directly</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Raw material register (Raw Material, RM), part of the Item Master</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — receipts and issues use the raw material account of the product line</td></tr>
</table>

<h3>Good to know about raw materials</h3>
<ul>
    <li>The <b>Vendor / customer</b> field of a raw material is picked from the main <b>Vendors</b> who sell it</li>
    <li>Raw materials cannot be sold directly; convert them into an FG item first (the <b>Convert to FG</b> button on the stock row view page)</li>
    <li>The cost of raw materials comes from the receiving prices (average)</li>
</ul>
<p class="text-secondary">For the meaning of each field, see the help of the <b>Items</b> page — this page shares its permissions with that page</p>
