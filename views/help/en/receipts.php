<?php /** How to use the Goods receipts page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>Records the goods a vendor delivers against a <b>Purchase order</b>. The system adds the stock, works out the average cost and posts by itself (Inventory / Goods received not invoiced). Partial receipts are fine; the rest waits for the next receipt</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Purchase orders</b> still waiting for goods</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Payables</b> once the vendor's tax invoice (ใบกำกับภาษี) arrives, and <b>Landed costs</b> (if any)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Goods receipt (RC) — an internal document; lot = the vendor's tax invoice number</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Inventory (non-stock = expense, fixed asset line = asset account) / Cr Goods received not invoiced (GRNI)</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">PO</td><td>Only orders with status Open can be picked; search by number or vendor name</td></tr>
    <tr><td class="text-nowrap">Date</td><td>The day the goods actually arrived; it cannot be in the future</td></tr>
    <tr><td class="text-nowrap">Tax invoice / delivery note no.</td><td>One field: the number of the document that comes with the goods (the vendor's tax invoice or delivery note), e.g. <code>IV6910-0123</code> — required, used to search later and automatically as the <b>Lot</b> of lot-controlled items</td></tr>
    <tr><td class="text-nowrap">Document date</td><td>The date on the vendor's document</td></tr>
    <tr><td class="text-nowrap">Receive now</td><td>The quantity actually received; empty or 0 = do not receive that line. You may enter more than is open — above the % set in Purchasing settings, the warning "More than ordered" shows under the field and after saving, but you can still save (the PO line closes)</td></tr>
    <tr><td class="text-nowrap">Location</td><td>Where it is stored in the warehouse; the system picks the warehouse's default location</td></tr>
    <tr><td class="text-nowrap">Lot / Expires</td><td>Only for lot-controlled items. The lot is filled from the <b>Tax invoice / delivery note no.</b> field (enter the number of either document the vendor sent with the goods) and you can type over it — if that field is empty, the system warns at that field</td></tr>
    <tr><td class="text-nowrap">Per pack</td><td>Quantity per pack or bundle, e.g. 12 per box; default 1</td></tr>
</table>
<p class="text-secondary">Services and non-stock items need no location or lot; the system posts them as expenses instead</p>

<h3>Receiving goods</h3>
<ol>
    <li>Press <b>Receive</b> on an order in the <b>Orders waiting for goods</b> box above the list (red row = past the delivery date) to get a receipt page with that order already picked — or press <b>Add</b> and pick the order yourself</li>
    <li>Pick the warehouse, enter the <b>Tax invoice / delivery note no.</b> (one field: the number of the document that came with the goods) and the document date</li>
    <li>Enter quantities in "Receive now", or press <b>Receive everything</b> if everything still open has arrived</li>
    <li>Check the locations and lots, then press <b>Save</b> — once every line is received in full, the PO closes by itself</li>
</ol>

<h3>After saving</h3>
<ul>
    <li>The receipt becomes view-only and <b>cannot be changed or cancelled</b>, because stock and accounts are already posted</li>
    <li>Badge <b>Waiting for payable</b> / <b>Payable created</b> — press the <b>Payables</b> button to record the payable from this receipt (or tick several receipts in the <b>Receipts to invoice</b> queue on Accounting → <b>Payables</b>)</li>
    <li>Freight or duty for this lot goes in <b>Landed costs</b></li>
</ul>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: press <b>Attach</b> to attach several pictures or PDFs, e.g. the vendor's delivery note / tax invoice, the inspection sheet, pictures of damaged goods — click to open, press × to remove — press the paper clip at the end of a row in the list to open that row's attachments window without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right of this page; removing needs the edit or delete right. Files can be attached in any status</p>
