<?php /** How to use the Purchase requisitions page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>Purchase requisition</b> is a request from an employee or department saying what they need, how many and when. Purchasing turns it into a <b>Purchase order</b> with one button — there is no approval step in the system; if a manager must sign, click <b>Print</b> and take it to be signed</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The requester (employee, department) or <b>Material planning (MRP)</b>, which creates requisitions for you</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Purchase order</b> (the Make PO button)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Purchase requisition (PR) — an internal document with no legal form</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — no purchase or debt has happened yet</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Requested by / Department</td><td>The person who needs the goods, e.g. "Somchai Jaidee" of the "Production" department</td></tr>
    <tr><td class="text-nowrap">Item</td><td>Choose from the list, or type your own if it is not in the system (e.g. air-conditioner repair), then enter the description and unit yourself</td></tr>
    <tr><td class="text-nowrap">Needed</td><td>The date the goods are needed; the default is 7 days ahead</td></tr>
    <tr><td class="text-nowrap">Est. price</td><td>The system fills in the last purchase price, used as the starting price on the purchase order</td></tr>
    <tr><td class="text-nowrap">Preferred vendor</td><td>The shop to order from. If filled in, purchase orders are split by the vendor of each line</td></tr>
    <tr><td class="text-nowrap">PO</td><td>The purchase order number created from that line; click to open it</td></tr>
</table>

<h3>From requisition to purchase order</h3>
<ol>
    <li>Click <b>Add</b>, enter the requester and department, then add item lines with quantity and needed date. Click <b>Save</b></li>
    <li>Purchasing opens the requisition and clicks <b>Make PO</b></li>
    <li>If some lines have no vendor, the system asks for "Vendor for lines without one" — choose one and confirm</li>
    <li>You get purchase orders split by vendor (one per vendor), and the system opens the first one. Check the prices and save</li>
</ol>

<h3>Status</h3>
<ul>
    <li><b>Open</b> — can be edited, turned into a purchase order or cancelled</li>
    <li><b>Ordered</b> — a purchase order has been made; it can no longer be edited. If the purchase order made from it is cancelled, the requisition goes back to Open</li>
    <li><b>Cancelled</b> — click <b>Cancel document</b>, only while Open. The number stays in the system</li>
</ul>
<p class="text-secondary">Requisitions can also be created from the <b>Material planning (MRP)</b> page — above the list is the <b>MRP suggests buying</b> box (shown when there are items; a red row = past the date it should have been ordered; it comes from the latest MRP run). Click <b>Make requisition</b> on a row, or tick several rows and click <b>Make requisition from ticked</b> to get a single requisition</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: click <b>Attach</b> to attach pictures or PDFs, several files allowed, e.g. vendor quotations (several vendors to compare), specs, sample pictures — click a file to open it, click × to remove it — click the clip icon at the end of a row in the list to open that row's attachments window straight away without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status</p>
