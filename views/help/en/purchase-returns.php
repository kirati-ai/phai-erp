<?php /** How to use the purchase returns page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>Purchase return</b> records goods sent back to the vendor (damaged, wrong spec, over-delivered) from one goods receipt. The system takes them out of stock and waits for the vendor's credit note (ใบลดหนี้)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Goods receipts</b> — open the receipt and click <b>Return goods</b>. You can return up to what was received minus what was already returned</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Payables</b>: if the receipt has no payable yet, tick the return together with the receipt (deducted from the invoice) — if the payable is already made, the vendor sends a <b>Credit note</b>; click <b>Enter the vendor credit note</b> to make a credit-note payable that picks up this return by itself</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Purchase return / Return to vendor — the tax evidence is the <b>Credit note</b> the vendor issues (s.86/10), used to reduce input VAT in the month it is received</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Return: Dr Goods received not invoiced (purchase price) / Cr Inventory (average cost) / difference to the inventory adjustment account — vendor credit note: Dr Trade payables / Cr Goods received not invoiced, Cr Input VAT</td></tr>
</table>

<h3>How to use</h3>
<ol>
    <li>Open the goods receipt and click <b>Return goods</b> (or pick from the list of receipts that can still be returned on the new page, searching by number, vendor, PO number or the vendor's delivery note number)</li>
    <li>Enter the <b>Reason for return</b> and the <b>Qty returned</b> on the lines being returned (the goods leave from the same location and lot they were received into, which must still have enough)</li>
    <li>Save — status <b>Awaiting credit note</b> → once deducted in a payable or credit note it becomes <b>Credited</b> (cancel that payable and the return goes back to waiting)</li>
    <li>Click <b>Print / PDF</b> and send it with the goods for the vendor to sign for the return</li>
</ol>
<p class="text-secondary">The purchase order is not reopened automatically. If the vendor will send replacement goods, issue a new purchase order</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section below a saved document: click <b>Attach</b> to attach pictures or PDFs, several files if needed, e.g. pictures of the faulty goods, the vendor's credit note, the return letter — click to open, click × to delete — click the paper clip at the end of a row in the list to open the attachments window of that row without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right of this page, deleting needs the edit or delete right. You can attach in any status</p>
