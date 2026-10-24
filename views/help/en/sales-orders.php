<?php /** How to use the Sales orders page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Record a customer's order, then ship against it. There is no approval step (print it to sign). The quantities still to ship are used in production planning (MRP)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>A won <b>Quotation</b>, or made directly — prices from <b>Price lists</b>, credit limit from <b>Customers</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Shipments</b> · quantities still to ship go into <b>Material planning (MRP)</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Sales order (SO) — confirms the order; not a tax document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post — nothing has been delivered yet</td></tr>
</table>

<h3>Making a sales order</h3>
<ol>
    <li>From a <b>Quotation</b> press <b>Make sales order</b>, or press a row in <b>Quotations awaiting an answer</b> above the list (it shows only when there are quotations not yet expired), or press <b>Add</b> on this page</li>
    <li>Pick the <b>Customer</b> and <b>Ship from</b>, and enter the <b>Customer PO</b> if there is one</li>
    <li>Enter the lines: item, quantity, price (filled from <b>Price lists</b>), and a <b>Wanted</b> date for each line if needed</li>
    <li>Press <b>Save</b>, then press <b>Ship</b> when ready</li>
</ol>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Ship from</td><td>The warehouse stock is taken from when shipping</td></tr>
    <tr><td class="text-nowrap">Customer PO</td><td>Printed on the delivery note and the invoice, and searchable in the list, e.g. <code>PO-6910-015</code></td></tr>
    <tr><td class="text-nowrap">Wanted</td><td>The date the customer wants the goods</td></tr>
    <tr><td class="text-nowrap">Shipped</td><td>The quantity already shipped on each line</td></tr>
</table>

<h3>Scheduled sales orders</h3>
<p>For a customer who orders long-term and wants deliveries in instalments (e.g. 10 sets a month), choose <b>Order type = Scheduled (instalments)</b>, then press <b>Delivery schedule</b> and enter the instalments from the schedule the customer sent — large customers often send the schedule by <b>EDI</b> (data sent from system to system). After all the instalments so far are shipped, the order stays open until you press <b>Close order</b></p>

<h3>Customer deposits</h3>
<p>An open sales order (in baht) has a <b>Receive deposit</b> button. It issues a <b>Deposit receipt / tax invoice</b> (ใบรับเงินมัดจำ / ใบกำกับภาษี) to the customer, and the tax invoice made when this order ships deducts the deposit for you</p>

<h3>Credit limit</h3>
<p>If the credit check is turned on in <b>Sales settings</b> and the customer's total is over the <b>Credit limit</b>, the system saves the order but shows the warning "Over credit limit" — you decide whether to ship or wait for payment first</p>

<h3>Status</h3>
<ul>
    <li><b>Open</b> — can be changed until the first shipment; you can ship part of it several times</li>
    <li><b>Closed</b> — every line is fully shipped (closes by itself), or press <b>Close order</b> when the rest will not be shipped</li>
    <li><b>Cancelled</b> — press <b>Cancel document</b>; only possible when nothing has been shipped. If it came from a quotation, the quotation goes back to Open</li>
</ul>
<p class="text-secondary">Once something has shipped, the sales order cannot be changed or cancelled. If the customer cuts the quantity, press Close order</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: press <b>Attach</b> to add pictures or PDFs, several files if you like, such as the customer's purchase order (PO) and the agreed drawings / specifications — click a file to open it, press × to remove it — press the paper clip at the end of a row in the list to open that row's attachments straight away without opening the document (a green clip with a number = how many files are attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status</p>
