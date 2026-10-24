<?php /** How to use the Chart of accounts page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The list of all the company's <b>account codes</b>. Every document posts with the codes on this page. A new install comes with a standard chart of accounts (e.g. <code>10101</code> Cash on hand, <code>11101</code> Trade accounts receivable, <code>40101</code> Sales income). Add or rename accounts to fit your business</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The standard chart of accounts that comes with the install, adjusted to your business</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every document that posts · <b>Accounting settings</b> · <b>Trial balance</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Chart of accounts — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — these are the codes that ledger entries use</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Account code</td><td>A number by category: 1xxxx assets, 2xxxx liabilities, 3xxxx equity, 4xxxx revenue, 5xxxx expenses — put it next to accounts of the same group, e.g. <code>50304</code> freight</td></tr>
    <tr><td class="text-nowrap">Category</td><td>Assets / Liabilities / Equity / Revenue / Expenses — revenue and expenses start at zero every year (calendar year) and then go into retained earnings; the other categories carry their balance forward</td></tr>
    <tr><td class="text-nowrap">Account name (Thai)</td><td>The Thai name shown on screens and reports — the English name is optional</td></tr>
    <tr><td class="text-nowrap">Control account</td><td>Tick it when this account may only be posted from documents (installed: trade accounts receivable, trade accounts payable, finished goods, work in process, raw materials) — <b>Journal entries</b> cannot pick a control account, so the account balance always matches the per-customer, per-vendor and stock detail</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when no longer used — old entries stay, but new documents cannot pick it</td></tr>
</table>

<h3>Add a new account</h3>
<ol>
    <li>Press <b>Add</b>, enter the code, pick the category, enter the name, then <b>Save</b></li>
    <li>The code can be used right away in journal entries, payables (other charges), bank accounts and the settings pages that ask for an account</li>
</ol>
<p class="text-secondary">Only accounts with no entries yet, and not used by a bank account, a withholding tax code or a fixed asset, can be deleted — for an account already used, turn off "Active" instead</p>

<h3>Accounts the system uses by itself</h3>
<p>The receivable, payable, input VAT, output VAT and other accounts that documents post to automatically are set in <b>Accounting settings</b>. The inventory, sales and cost of goods sold accounts per item type are set in <b>Master data → Product lines</b>, Accounts tab</p>
