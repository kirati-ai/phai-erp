<?php /** How to use the shipments page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Ship goods against a sales order. The system takes the stock out of the sales order's warehouse, posts the cost of goods sold, and counts the quantity shipped on the sales order</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Sales orders</b> with open quantities; stock is taken from <b>Stock on hand</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Invoice / tax invoice</b> (ใบแจ้งหนี้/ใบกำกับภาษี), one per shipment</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Delivery Order (DO) / delivery note that the customer signs</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Cost of goods sold / Cr Inventory (at average cost)</td></tr>
</table>

<h3>Shipping</h3>
<ol>
    <li>Click <b>Ship</b> on a row in <b>Sales orders to ship</b> above the list (one row per item line: ship to (the customer's district and province), quantity to ship, stock on hand, and <b>Enough</b> / <b>Short</b> / <b>None</b> — the stock goes to the lines needed first; when short, the <b>Coming from</b> column shows the open work orders or purchase orders, and <b>Can ship</b> = the due/receipt date of the last order to wait for; "Nothing planned" means run MRP; red row = past the date the customer wants) or on the sales order, or click <b>Add</b> on this page and pick the <b>Sales order</b></li>
    <li>Once open, <b>Ship now</b> is filled with as much as is open and in stock (if there is enough, everything ships on one document); you can change the quantities — empty lines are not shipped</li>
    <li>The system picks the <b>Location</b> and <b>Lot</b> itself, by location and oldest expiry first. If one lot is not enough it adds a row for the next lot of the same line until the quantity is covered (× button deletes an added row). You can change the location/lot yourself (the <b>Available</b> column shows the quantity at that location/lot before shipping, red when it is not enough for the open quantity — enter more than is on hand and the field turns red with "Not enough on hand" under it)</li>
    <li>Enter <b>Ship via</b> and <b>Tracking no.</b> if any, e.g. "Company truck" or <code>TH0123456789</code></li>
    <li>Click <b>Confirm shipment</b>; the system asks you to confirm once more (stock goes out and cannot be changed). After saving it opens the <b>Invoice</b> page filled from this shipment for you to check/change (date, due date, customer PO number, remarks, prices, discounts) before saving — if you do not save it, you can issue it later from the queue of shipments to invoice</li>
</ol>

<h3>Good to know</h3>
<ul>
    <li>You cannot ship more than the open quantity. The ship date cannot be after today</li>
    <li>Lot-controlled items need a lot. Non-stocked lines (services, typed-in items) need no location</li>
    <li>When every line is shipped, the sales order closes by itself</li>
    <li>One shipment can have one invoice, priced as on the sales order</li>
</ul>

<h3>Status</h3>
<ul>
    <li><b>Not invoiced</b> — open the document and click <b>Invoice</b>, or do it on the <b>Invoices / tax invoices</b> page</li>
    <li><b>Invoiced</b> — has a button to the invoice. If the invoice is cancelled, the shipment goes back to not invoiced</li>
</ul>
<p class="text-secondary">After saving, a shipment cannot be changed or cancelled. Check the quantities, locations and lots before you save</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section below a saved document: click <b>Attach</b> to attach pictures or PDFs, several files if needed, e.g. the delivery note signed by the customer (POD), pictures of the delivery — click to open, click × to delete — click the paper clip at the end of a row in the list to open the attachments window of that row without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right of this page, deleting needs the edit or delete right. You can attach in any status</p>
