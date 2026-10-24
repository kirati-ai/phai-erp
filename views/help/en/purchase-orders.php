<?php /** How to use the purchase orders page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>Purchase order (PO)</b> is sent to the vendor to order goods. It is the document used to <b>receive goods into stock</b> and then make the payable — there is no approval step in the system; once saved, goods can be received straight away. If an authorized person must sign, click <b>Print</b> and have it signed before sending it to the vendor</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Purchase requisitions</b> or made directly (if <b>Purchasing settings</b> do not require a requisition)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Goods receipts</b> → <b>Payables</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Purchase Order (PO) — a purchase agreement with the vendor, not a tax document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it is an order; nothing has been received yet (posting happens at goods receipt)</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Vendor</td><td>Only vendors with status Active can be picked. The system then fills in the terms, currency and tax from the vendor record</td></tr>
    <tr><td class="text-nowrap">Due</td><td>The date the vendor must deliver</td></tr>
    <tr><td class="text-nowrap">Receive into</td><td>The warehouse that will receive the goods</td></tr>
    <tr><td class="text-nowrap">Currency / Rate</td><td>For foreign purchases pick the currency, e.g. USD. The system fills in the rate for the document date (can be changed). Item costs are converted to baht at this rate</td></tr>
    <tr><td class="text-nowrap">Tax</td><td>E.g. VAT 7% — <b>line prices exclude VAT</b>; the system adds the tax in the total</td></tr>
    <tr><td class="text-nowrap">Unit price</td><td>The system fills in the last purchase price from this vendor; you can type over it</td></tr>
    <tr><td class="text-nowrap">Disc %</td><td>Discount per line, e.g. <code>5</code> = 5% off</td></tr>
    <tr><td class="text-nowrap">Received</td><td>The quantity of that line already received into stock</td></tr>
    <tr><td class="text-nowrap">Order type</td><td><b>Normal</b> = one delivery on the due date · <b>Scheduled</b> = a long-term price agreement with goods coming in several instalments — after saving, click <b>Delivery schedule</b> to split the instalments. When every instalment is received the order stays open until you click <b>Close PO</b></td></tr>
</table>

<h3>Order, then receive</h3>
<ol>
    <li>Click <b>Add</b> (or <b>Make PO</b> from a requisition), pick the vendor, then add item lines, quantities and prices</li>
    <li>Click <b>Save</b>, then <b>Print</b> and send it to the vendor</li>
    <li>When the goods arrive, click <b>Receive</b> on the purchase order; it opens the goods receipt page with this order already selected</li>
    <li>After receiving, the <b>Receipts</b> history shows on the right; click to open a receipt</li>
</ol>

<h3>Status and what you can do</h3>
<ul>
    <li><b>Open</b> — waiting for goods. It can be edited <b>only before the first receipt</b>; once received it cannot be changed</li>
    <li><b>Closed</b> — every line is received and the system closes it, or click <b>Close PO</b> when the vendor delivered short and will not deliver more (open quantities are no longer awaited)</li>
    <li><b>Cancelled</b> — click <b>Cancel document</b>, only while nothing has been received. If it was made from a requisition, the requisition goes back to Open</li>
</ul>
<p class="text-secondary">Above the list is the <b>Requisitions waiting for an order</b> box (shown when there are items; red row = past the date needed). Click <b>Make PO</b> to get one purchase order per vendor and open the first one — lines without a vendor go to the vendor in the box's Vendor field. If that field is empty, open the requisition and make the purchase order from inside it</p>
<p class="text-secondary">If purchasing settings have "Purchase orders only from requisitions" turned on, there is no Add button on this page; make them from <b>Purchase requisitions</b></p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section below a saved document: click <b>Attach</b> to attach pictures or PDFs, several files if needed, e.g. the quotation ordered from, the contract, the PO signed back by the vendor — click to open, click × to delete — click the paper clip at the end of a row in the list to open the attachments window of that row without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right of this page, deleting needs the edit or delete right. You can attach in any status</p>
