<?php /** How to use the landed costs page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Add <b>freight, import duty, insurance</b> and other costs of one purchase into the item cost of that <b>Goods receipt</b>, so the cost per unit matches what was really paid</p>
<p>The list shows <b>import</b> receipts (purchase orders in a foreign currency — purchases in baht have no landed costs) with the currency, the value (THB) of the goods and the total landed costs entered. Click a row to open that receipt</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Goods receipts</b> (receipts with freight, duty or insurance)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The vendor of the cost sends an invoice → <b>Payables</b> (pick the allocated landed cost)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Landed Cost (LC) — the import customs entry (ใบขนสินค้าขาเข้า) and the duty receipt are the supporting evidence</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Allocate cost: Dr Inventory (already sold = Cost of goods sold) / Cr GRNI · Payable: Dr GRNI + Input VAT / Cr Trade payables</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Line</td><td>One line per bill; enter as many lines as needed (<b>Add line</b>, × button to delete) — the same type can repeat</td></tr>
    <tr><td class="text-nowrap">Cost</td><td>Freight (sea/air freight), Customs clearance (shipping agent), Import duty, Insurance, Other (e.g. D/O fee, port charges)</td></tr>
    <tr><td class="text-nowrap">Document no.</td><td>The number of the document that comes with this cost, e.g. the Air Waybill (AWB) / Bill of Lading (B/L) number or the import customs entry number — shown in the list waiting for a payable, to help match the bill of the party charging it</td></tr>
    <tr><td class="text-nowrap">Amount (THB)</td><td>The amount in baht, excluding VAT, e.g. freight 3,500.00 — no amount = that line is not saved</td></tr>
    <tr><td class="text-nowrap">Charged by</td><td>The vendor that charges this cost (required), e.g. an airline, a freight company, a shipping agent or the Customs Department — each line waits for a payable from its own party</td></tr>
    <tr><td class="text-nowrap">Payable</td><td>The payable number that cleared this cost. The system fills it in once the payable is made (click to open)</td></tr>
    <tr><td class="text-nowrap">Share</td><td>The cost each item line gets, split by the line's <b>value</b>, e.g. a line worth 60% of the receipt gets 60% of the cost</td></tr>
</table>

<h3>Enter the costs, then allocate</h3>
<ol>
    <li>Click the receipt in the list and click <b>Edit</b> — receipts from foreign-currency purchase orders that are not allocated yet appear in the <b>Import receipts waiting for landed costs</b> box above the list; click <b>Add costs</b> to open one straight away</li>
    <li>Enter one line per bill: type, document no., charged by, amount, then click <b>Save</b> — the badge shows <b>Waiting to allocate</b> and can still be changed</li>
    <li>Check the amounts, then click <b>Allocate to cost</b> and confirm</li>
</ol>

<h3>What happens after allocating</h3>
<ul>
    <li>The item's average cost goes up, spread over the quantity still in stock at that moment</li>
    <li>If the items have all been sold or issued, that part of the cost goes to cost of goods sold. For non-stocked items it goes to expense</li>
    <li>Posted automatically, dated today: debit inventory / credit <b>Goods received not invoiced (GRNI)</b> — it is not a debt until the party charging it sends an invoice</li>
    <li>Costs with a charging party appear in <b>Receipts to invoice</b> on the <b>Payables</b> page, one line each, with a type badge (e.g. Freight) and the document number. When the invoice arrives, tick every line on that bill and make the payable from that list: debit GRNI + input VAT / credit trade payables — so the GRNI of this cost becomes zero and the cost is not counted twice</li>
    <li>Allocated costs show the <b>Allocated</b> badge and <b>cannot be changed or cancelled</b> — for a bill that comes later, add a new line and allocate again, until the payable is made</li>
    <li>Once a line has a payable, the badge shows <b>Billed</b> and there is no edit button — the costs of this receipt are closed (cancel the payable first to change them again)</li>
</ul>
<p class="text-secondary">After entering costs, allocate them soon, before the items of that lot are sold or issued, so the cost stays with the items in stock</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section below the shares of each line: click <b>Attach</b> to attach pictures or PDFs, several files if needed, e.g. the customs entry, B/L, AWB, duty receipt, freight invoice — click to open, click × to delete</p>
<p class="text-secondary">Attaching needs the add or edit right of this page, deleting needs the edit or delete right. You can attach in any status</p>
