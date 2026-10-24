<?php /** How to use the Items page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The register of all <b>Items</b> you buy, sell, make or keep in stock. Use the filters next to the search box to see one type only (FG / RM / FA) or one product line</p>
<p>Before adding items, have the <b>Units of measure</b> and <b>Product lines</b> ready to choose from</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set up <b>Units of measure</b>, <b>Product lines</b>, <b>Item groups</b> and <b>Item types</b> first (Master data › Setup)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every document: <b>Purchase requisitions</b>, <b>Purchase orders</b>, <b>Goods receipts</b>, stock, <b>Bills of materials</b>, <b>Work orders</b>, <b>Quotations</b>, <b>Sales orders</b>, <b>Shipments</b>, <b>Invoices</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Item master — master data shared by every module: one code from buying and storing to making and selling</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — but the item's product line decides the inventory, cost of goods sold, sales and purchase accounts that other documents post to</td></tr>
</table>

<h3>Key fields (General tab)</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Item number</td><td>A unique code, e.g. <code>FG-TABLE-01</code>, <code>RM-WOOD-02</code></td></tr>
    <tr><td class="text-nowrap">Product line</td><td>Says whether it is a product (FG), a raw material (RM) or an asset (FA), and where it posts — the most important field</td></tr>
    <tr><td class="text-nowrap">Vendor / customer</td><td>A raw material takes its main <b>Vendor</b>, other items their main <b>Customer</b> — choose the product line first for this field to work. Optional</td></tr>
    <tr><td class="text-nowrap">Source</td><td><b>Purchased</b> or <b>Manufactured</b> — planning (MRP) suggests purchase orders or work orders based on this field</td></tr>
    <tr><td class="text-nowrap">Status</td><td><b>Obsolete</b> items are no longer offered in new documents</td></tr>
    <tr><td class="text-nowrap">Control</td><td><b>Lot</b> (the default) — every receipt gets a new lot number, e.g. the number of the vendor's tax invoice (ใบกำกับภาษี) or the work order number</td></tr>
    <tr><td class="text-nowrap">Stocked</td><td>Tick it for things that have a stock balance — leave it unticked for services, freight and consumables, which have no balance in stock</td></tr>
</table>

<h3>Other tabs</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Prices and cost</td><td><b>Selling price</b> in baht, used when there is no price in <b>Sales › Price lists</b> · <b>Standard cost</b>: if the costing method is <b>Average</b>, the system updates it by itself on every receipt</td></tr>
    <tr><td class="text-nowrap">Planning</td><td>Lead time (days), minimum order, order multiple, maximum order per line (0 = no limit), quantity per pack — used when <b>Material planning (MRP)</b> calculates; if you do not use MRP, keep the defaults</td></tr>
    <tr><td class="text-nowrap">Reference</td><td>Barcode, drawing number, weight, size — kept for reference; the system calculates nothing from these fields</td></tr>
</table>

<h3>Add an item</h3>
<ol>
    <li>Press <b>Add</b>, enter the code, name and unit of measure, and choose the product line</li>
    <li>Choose the source (Purchased / Manufactured) and, if you sell it, enter the selling price on the <b>Prices and cost</b> tab</li>
    <li>Press <b>Save</b> — opening balances go in through <b>Inventory › Stock counts</b>, not this page</li>
</ol>
<p class="text-secondary">Many items: use the <b>Import from Excel</b> icon, press <b>Template</b>, fill it in and import — existing codes are updated, new codes are added, and one wrong row means nothing in the file is saved</p>
<p class="text-secondary">Items with stock, movement history, documents, a bill of materials or a selling price cannot be deleted; the system says "Cannot delete: this record is in use" — change the status to Obsolete instead</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section at the bottom of the window of a saved record: press <b>Attach</b> to add pictures or PDFs, as many as you need, e.g. specifications, drawings, product photos, chemical safety data sheets (MSDS) — click a file to open it, press × to delete it — press the paper clip at the end of a row in the list to open that row's attachments window straight away, without opening the record (a green clip with a number = the number of attached files)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page, deleting needs the edit or delete right. You can attach in any status. Deleting an item also deletes its attached files</p>
