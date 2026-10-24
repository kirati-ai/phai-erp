<?php /** How to use the Purchasing overview page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A one-page summary of purchasing work: purchase amounts, purchase orders waiting for vendors to deliver, and the main vendors. Click a card or a row to open the document</p>

<h3>Purchasing in an ERP system</h3>
<p>The purchasing module is <b>Procurement</b>, and it is part of <b>SRM</b> (Supplier Relationship Management, managing your vendors) — choose vendors, order goods, follow up deliveries, inspect what arrives, and hand over to accounting for payment</p>

<h3>Document flow</h3>
<ol>
    <li><b>Purchase requisition</b> (PR) — an internal request, or created from MRP. No posting</li>
    <li><b>Purchase order</b> (PO) — sent to the vendor, printed for signing. No posting</li>
    <li><b>Goods receipt</b> — against the vendor's delivery note / tax invoice (ใบกำกับภาษี). Stock goes up: Dr Inventory / Cr Goods received not invoiced (GRNI)</li>
    <li><b>Landed costs</b> — freight and duties added to the cost of the goods (if any)</li>
    <li>Hand-off to accounting: <b>Payables</b> → <b>Payment vouchers</b> → <b>Vendor payments</b> + withholding tax certificate, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย) → <b>Input VAT report</b></li>
</ol>

<h3>Hand-off to other modules</h3>
<ul>
    <li><b>Inventory</b>: goods received go into stock on hand; cost = price after discount × exchange rate</li>
    <li><b>Manufacturing</b>: MRP creates requisitions for materials, and counts open requisitions / purchase orders as supply on the way</li>
    <li><b>Accounting</b>: accounts payable (AP), input VAT and withholding tax</li>
</ul>

<h3>On this page</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Work cards of the module</td><td>The top row: one card per menu item in the "Transactions" part of this module, saying what that page does and which work from the step before is waiting for it (the same set as the work queue on that page), with the count and the first 3 document numbers — red is overdue. Click the card title or a document number to open it</td></tr>
    <tr><td class="text-nowrap">Purchases this month</td><td>Total of purchase orders that are not cancelled (in baht), compared with the same days of last month</td></tr>
    <tr><td class="text-nowrap">Open purchase orders</td><td>Orders still open, and how many are past their delivery date</td></tr>
    <tr><td class="text-nowrap">Requisitions to order (PR)</td><td>A card that links to the purchase requisitions page</td></tr>
    <tr><td class="text-nowrap">Receipts this month</td><td>Number of goods receipts since the start of the month</td></tr>
    <tr><td class="text-nowrap">Late deliveries</td><td>The first 5 purchase orders that vendors have not delivered on time — use it to follow up vendors (shown when there are any)</td></tr>
    <tr><td class="text-nowrap">Latest purchase orders</td><td>The 5 latest orders with their status</td></tr>
    <tr><td class="text-nowrap">Top vendors</td><td>The 5 vendors with the most purchases this year</td></tr>
</table>
