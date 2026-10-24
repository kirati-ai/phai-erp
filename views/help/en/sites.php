<?php /** How to use the Sites page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>The list of <b>warehouses</b> or branches where goods are kept. All stock is in one warehouse or another, and inside a warehouse there is a further level of <b>locations</b> (set up in <b>Inventory › Warehouses and locations</b>)</p>
<p>The install already gives you warehouse <code>MAIN</code> (main warehouse) with location <code>STOCK</code>. If you keep goods in only one place, just carry on using it</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Installed with warehouse <code>MAIN</code>; the address uses the province, district and sub-district tables</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Warehouses and locations</b> → stock, purchase orders, sales orders, work orders and <b>Item-site planning</b> name a warehouse</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Site / warehouse, inventory master data that tells where goods are</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Warehouse code</td><td>A short upper-case code such as <code>MAIN</code>, <code>WH2</code>, <code>BKK</code> — it cannot be changed later because documents refer to it</td></tr>
    <tr><td class="text-nowrap">Warehouse name</td><td>A readable name, e.g. "Bang Na warehouse"</td></tr>
    <tr><td class="text-nowrap">Default receiving location</td><td>The location the system picks first when receiving goods, transferring and issuing materials to production, e.g. <code>STOCK</code> — it must be a location that exists in this warehouse</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when the warehouse is no longer used — old data stays, but new documents cannot pick it</td></tr>
    <tr><td class="text-nowrap">Address tab</td><td>Fill in the separate fields (No., Moo, Building, Soi, Road …), pick the province, then the district and sub-district; the postcode follows. The system puts it together as one line for you</td></tr>
</table>

<h3>Adding a new warehouse</h3>
<ol>
    <li>Press <b>Add</b>, enter the warehouse code and name</li>
    <li>Go to <b>Inventory › Warehouses and locations</b> and add at least one location for this warehouse, e.g. <code>WH2</code> / <code>S1</code></li>
    <li>Come back and edit the warehouse: set the <b>Default receiving location</b> to that location</li>
</ol>
<p class="text-secondary">A warehouse that locations or assets refer to cannot be deleted — turn off "Active" instead</p>
