<?php /** How to use the Warehouses and locations page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Set up the <b>locations</b> in each warehouse, such as shelves, zones or rooms. Stock is kept by warehouse + location + lot, and every receipt, issue or transfer must choose a location</p>
<p>Warehouses themselves are added in <b>System settings › Sites</b> — the install already gives you warehouse <code>MAIN</code> with location <code>STOCK</code></p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Sites</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every receipt, issue, transfer and count, and <b>Stock on hand</b> by location — MRP counts only locations that count as available (nettable)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Location (bin), an inventory master record</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post — moving between locations does not post because it is the same inventory account</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Warehouse</td><td>The warehouse this location is in</td></tr>
    <tr><td class="text-nowrap">Location</td><td>A short upper-case code such as <code>STOCK</code>, <code>A-01</code>, <code>RM</code>, <code>QC</code> — different warehouses can use the same code</td></tr>
    <tr><td class="text-nowrap">Type</td><td><b>Multiple items</b> or <b>Single item</b> — describes the kind of storage; most are Multiple items</td></tr>
    <tr><td class="text-nowrap">Status</td><td><b>Active</b> as normal · <b>On hold</b> the location does not show in the lists to pick from when receiving, issuing or transferring · <b>Restricted</b> can still be picked; it marks a special storage place</td></tr>
    <tr><td class="text-nowrap">Counts as available</td><td>Ticked = stock in this location counts as available when MRP is calculated — untick it for places that hold damaged goods or goods waiting for inspection</td></tr>
    <tr><td class="text-nowrap">Used in material planning</td><td>Leave it ticked as normal</td></tr>
    <tr><td class="text-nowrap">Department / project</td><td>Optional; notes which department or job this location belongs to</td></tr>
</table>

<h3>Adding a location</h3>
<ol>
    <li>Press <b>Add</b>, pick the warehouse, enter the location code and description, e.g. <code>A-01</code> "Shelf A, row 1"</li>
    <li>If it is a place for damaged goods, untick <b>Counts as available</b></li>
    <li>If you want this location chosen first when receiving goods, set it as the <b>Default receiving location</b> on the <b>Sites</b> page</li>
</ol>
<p class="text-secondary">To move goods from one location to another, use the <b>Transfer</b> button on the stock line page (<b>Stock on hand</b> › click a row)</p>
