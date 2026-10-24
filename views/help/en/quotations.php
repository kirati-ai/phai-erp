<?php /** How to use the Quotations page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>Quote prices to a customer and print the quotation for them to sign. When the customer agrees, one button makes the sales order — nothing to type twice</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Customers</b>, <b>Items</b>, <b>Price lists</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Won → <b>Sales orders</b> · lost: the quotation is closed</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Quotation (QT) — a trade document, not a tax document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — no sale has happened yet</td></tr>
</table>

<h3>Make a quotation</h3>
<ol>
    <li>Press <b>Add</b> and choose the <b>Customer</b> — terms, currency, tax and salesperson are filled in from the customer</li>
    <li>Set <b>Valid until</b> (30 days from today by default)</li>
    <li>Enter the lines: choose an item and enter the quantity — the price comes from <b>Price lists</b> or the item's price; you can change the price and the discount % yourself</li>
    <li>For things that are not in the system (e.g. freight, installation) you can type the description and unit yourself</li>
    <li>Press <b>Save</b>, then <b>Print</b> to send it to the customer</li>
</ol>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Valid until</td><td>Must not be before the quotation date — once this date has passed, no sales order can be made from the quotation; change the date or make a new one</td></tr>
    <tr><td class="text-nowrap">Exchange rate</td><td>Filled in from the currency and date; baht is 1</td></tr>
    <tr><td class="text-nowrap">Item</td><td>Raw materials (RM) cannot be sold directly; convert them to an FG item on the stock page first</td></tr>
</table>

<h3>Status</h3>
<ul>
    <li><b>Open</b> — can be edited; press <b>Make sales order</b> when the customer agrees, or <b>Mark lost</b> when the customer says no</li>
    <li><b>Won</b> — the sales order has been made; cannot be edited</li>
    <li><b>Lost</b> — closed; cannot be edited</li>
</ul>
<p class="text-secondary">If the sales order made from a quotation is cancelled, the quotation goes back to Open</p>
