<?php /** How to use the Price lists page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>Special prices and discounts that differ from the normal item price. The system fills in the price by itself on quotation and sales order lines</p>
<ul>
    <li><b>The <code>ALL</code> list</b> — applies to every customer, good for promotions</li>
    <li><b>Lists with other names</b>, e.g. <code>VIP</code>, <code>DEALER</code> — apply to customers that have this code in the <b>Special price list</b> field of the <b>Customers</b> page</li>
</ul>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The normal price from <b>Items</b> and the <b>Special price list</b> field of <b>Customers</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The price filled in on <b>Quotations</b> and <b>Sales orders</b>, which flows on to <b>Invoices</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Price list — sales master data, not a document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it is an offered price; no trade has happened yet</td></tr>
</table>

<h3>How the system picks the price</h3>
<ol>
    <li>Looks at the customer's price list first</li>
    <li>If not found, looks at the <code>ALL</code> list</li>
    <li>If neither has it, uses the sales price on the item page (converted to the customer's currency at the exchange rate)</li>
</ol>
<p>A row can be used when the item matches, the currency matches the customer's currency, the document date is between Start and End, and the quantity is not less than Minimum qty — if several rows qualify, the one with the highest Minimum qty is used</p>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Price list</td><td><code>ALL</code> or a code you make up, e.g. <code>VIP</code></td></tr>
    <tr><td class="text-nowrap">Type</td><td><b>Special price</b> = use the price in the Price field instead of the normal price · <b>Discount %</b> = the normal price, with the Discount % put on the line</td></tr>
    <tr><td class="text-nowrap">Minimum qty</td><td>Lets you price by quantity, e.g. 1 piece = 100, from 10 pieces = 90, from 100 pieces = 80 (three rows)</td></tr>
    <tr><td class="text-nowrap">Start / End</td><td>The period when this price applies. End left empty = never expires</td></tr>
</table>
<p class="text-secondary">A filled-in price is fetched again when the quantity changes, but a price you typed over in the document is left alone</p>
