<?php /** How to use the Inventory overview page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>A summary of stock across the company: value, items below safety stock, lots near expiry, open stock counts and the latest movements</p>

<h3>Inventory in an ERP system</h3>
<p>The inventory module is <b>Inventory Management</b> — the hub that purchasing, manufacturing and sales move goods in and out of. Every stock change writes one history row, works out the <b>average cost</b> on receipt, and posts the inventory entries by itself (journal type IC)</p>

<h3>Movements in and out</h3>
<ol>
    <li><b>Receipts</b>: goods received against purchase orders (RCT-PO) · output received from work orders (RCT-WO) · manual receipts (RCT-UNP)</li>
    <li><b>Move / convert</b>: transfers between warehouses and locations (ISS-TR / RCT-TR, no posting) · converting raw materials to FG (Dr Finished goods / Cr Raw materials)</li>
    <li><b>Issues</b>: shipments against sales orders (ISS-SO, Dr Cost of goods sold / Cr Inventory) · issues to work orders (ISS-WO) · manual issues (ISS-UNP)</li>
    <li><b>Counts</b>: stock counts (CYC-CNT) adjust the balance to what is really there; the difference posts to the reason code's account</li>
    <li><b>Reports</b>: stock on hand + stock movements = the data of the <b>Stock and materials report (s.87(3))</b> (รายงานสินค้าและวัตถุดิบ)</li>
</ol>

<h3>Hand-off to other modules</h3>
<ul>
    <li><b>Purchasing / Manufacturing</b>: items below safety stock are a signal to raise a requisition or a work order — MRP works it out</li>
    <li><b>Sales</b>: you can ship only what is in stock (unless the settings allow negative stock). Raw materials (RM) cannot be sold directly</li>
    <li><b>Accounting</b>: the stock value should match the inventory accounts in the <b>Trial balance</b></li>
</ul>

<h3>On this page</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Work cards of the module</td><td>Top row: one card per menu item in the "Transactions" part of this module. Each card says what that page does and shows the work from earlier steps waiting for it (the same set as the work queue on that page), with the count and the first 3 document numbers — red means overdue. Click the card title or a document number to open it</td></tr>
    <tr><td class="text-nowrap">Total stock value</td><td>Quantity on hand × item cost</td></tr>
    <tr><td class="text-nowrap">Below safety stock</td><td>Items with less on hand than the safety stock in <b>Item-site planning</b>, with the list below</td></tr>
    <tr><td class="text-nowrap">Lots expiring in 30 days</td><td>Lots whose expiry date is within the next 30 days</td></tr>
    <tr><td class="text-nowrap">Open stock counts</td><td>Stock counts not yet adjusted</td></tr>
    <tr><td class="text-nowrap">Stock value by type</td><td>Value split by product line type (FG / RM / FA)</td></tr>
    <tr><td class="text-nowrap">Latest stock movements</td><td>The latest 6 movements in and out from <b>Stock movements</b></td></tr>
</table>
