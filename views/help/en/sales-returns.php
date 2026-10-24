<?php /** How to use the Sales returns page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>Sales return</b> records goods a customer sends back from one shipment. The goods go back into stock at the original cost of when they were shipped, then wait for a <b>Credit note</b> (ใบลดหนี้) to the customer</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Shipments</b> — open the shipment and click <b>Customer return</b>. You can take back no more than what was shipped minus what was already returned</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Invoices / credit notes</b> (Accounting → Receivables (AR)): in the <b>Returns waiting for a credit note</b> box, click <b>Issue credit note</b> to get a credit note with the customer, the original tax invoice, reason CDNG05 and the lines already filled in → <b>Cash receipts</b> can deduct the credit note</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Sales return (RMA), an internal document — the tax evidence is the <b>Credit note</b> (Revenue Code s.86/10), which must refer to the original tax invoice (ใบกำกับภาษี)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Return: Dr Inventory / Cr Cost of goods sold (at the cost of when it was shipped) — Credit note: Dr Sales revenue, Dr Output VAT / Cr Accounts receivable</td></tr>
</table>

<h3>How to use</h3>
<ol>
    <li>Open the shipment and click <b>Customer return</b> (or pick from the list of shipments that can still be returned on the new page), and enter the <b>Reason for return</b> and the <b>Qty returned</b></li>
    <li>The goods go back to the same location and lot they were shipped from — if they are damaged, use <b>Issue</b> or move them to a location for damaged goods later</li>
    <li>Status <b>Awaiting credit note</b> → once the credit note is issued, <b>Credit note issued</b> (cancelling the credit note puts the return back to waiting)</li>
</ol>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: click <b>Attach</b> to add pictures or PDFs, several at a time, e.g. pictures of the returned goods or the customer's return letter — click a file to open it, click × to remove it — click the paperclip at the end of a row in the list to open the attachments window of that row without opening the document (a green paperclip with a number = how many files are attached)</p>
<p class="text-secondary">Attaching needs the add or edit permission of this page, removing needs edit or delete. You can attach in any status</p>
