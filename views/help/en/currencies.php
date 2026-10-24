<?php /** How to use the Currencies page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The list of <b>currencies</b> used in purchase and sales documents, e.g. THB, USD — picked as the currency of customers, vendors and documents. The company's base currency is set in <b>Company information</b> (normally THB)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The standard ISO 4217 currencies that come with the install</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Exchange rates</b> · customers, vendors, and purchase and sales documents</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Currency — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — receivables, payables and the general ledger are always kept in baht</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>The international 3-letter code, e.g. <code>USD</code>, <code>EUR</code>, <code>JPY</code></td></tr>
    <tr><td class="text-nowrap">Currency name</td><td>e.g. "US Dollar", or the name in Thai</td></tr>
    <tr><td class="text-nowrap">Symbol</td><td>e.g. <code>$</code>, <code>€</code>, <code>฿</code></td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when no longer used — it no longer shows up to pick for new entries</td></tr>
</table>

<h3>Using a foreign currency</h3>
<ol>
    <li>Check that the currency is on this page; if not, press <b>Add</b></li>
    <li>Go to <b>Exchange rates</b> and enter the rate in baht per 1 unit of that currency</li>
    <li>Set the currency on the customer or vendor. New documents use that currency and convert to baht at the rate of the document date</li>
</ol>
<p class="text-secondary">A currency used by a customer, a vendor or an exchange rate cannot be deleted — receivables and payables in the system are always kept in baht</p>
