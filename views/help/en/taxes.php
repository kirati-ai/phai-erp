<?php /** How to use the Taxes page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The <b>value added tax (VAT)</b> codes chosen in purchase and sales documents, e.g. VAT 7%, VAT 0% or no VAT — set one as the default on customers and vendors, and new documents take it from there</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The VAT rates of the Revenue Code (7%, 0%, exempt)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The default on <b>Customers</b> and <b>Vendors</b> → purchase and sales documents → input / output VAT reports</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Value added tax (VAT) code — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — documents post input / output VAT at this rate</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Tax code</td><td>E.g. <code>VAT7</code> (7%), <code>VAT0</code> (exports, 0% rate), <code>NON</code> (no VAT, e.g. a vendor not registered for VAT)</td></tr>
    <tr><td class="text-nowrap">Name</td><td>A readable name, e.g. "VAT 7%"</td></tr>
    <tr><td class="text-nowrap">Rate (%)</td><td>The percentage as a number, e.g. <code>7</code> — the system works out the tax from the amount before tax</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when no longer used — it will not be offered in new documents</td></tr>
</table>
<p>The accounts that input and output VAT post to are set in <b>Accounting › Accounting settings</b></p>

<h3>If the VAT rate changes</h3>
<ol>
    <li>Click <b>Add</b> for a new code, e.g. <code>VAT10</code> with rate 10</li>
    <li>Change the tax code on customers and vendors</li>
    <li>Turn off "Active" on the old code — do not change the rate of the old code, so old documents still read correctly</li>
</ol>
<p class="text-secondary">A tax code used by a customer, vendor or product line cannot be deleted — withholding tax is on a separate page (<b>Accounting › Withholding tax codes</b>)</p>
