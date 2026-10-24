<?php /** How to use the Bank accounts page (opened from the ? button, top right) */ ?>
<h3>What this page is</h3>
<p>The list of <b>money channels</b> the company uses to receive and pay money — cash, and each bank account. You choose them on the <b>Cash receipts</b>, <b>Vendor payments</b> and <b>Bank reconciliation</b> pages</p>
<p>Each one is linked to an <b>Account code</b> in the chart of accounts, and the system posts money in and out to that code for you, e.g. money received into BANK1 = debit 10201</p>
<p>The table shows the <b>Balance</b> as of today (the balance of the account code in the general ledger), <b>Last reconciled</b> and the number of items <b>To reconcile</b> — click a row to open the <b>Bank book</b> of that account (the petty cash row goes to the <b>Petty cash</b> page). The pencil icon opens the settings form</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The accounts the company opened with banks, and cash</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Chosen in <b>Cash receipts</b> <b>Vendor payments</b> and <b>Bank reconciliation</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Bank account master data — linked to an account code in the chart of accounts</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the linked account code is used when money is received or paid</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>A short name to choose in documents, e.g. <code>CASH</code>, <code>KBANK-SA</code>, <code>SCB-CA</code></td></tr>
    <tr><td class="text-nowrap">Bank account name</td><td>A readable name with the bank, type and account number, e.g. "Kasikorn savings 123-4-56789-0"</td></tr>
    <tr><td class="text-nowrap">Account code</td><td>The account in the chart of accounts that this channel's money goes to — <b>each bank account should have its own account code</b> so it can be reconciled with the statement</td></tr>
    <tr><td class="text-nowrap">Currency</td><td>The account's currency, usually THB</td></tr>
    <tr><td class="text-nowrap">Petty cash</td><td>Tick it for petty cash: documents that receive or pay with this account put money into or take it out of the petty cash box of the person making the entry, and it is left out of bank reconciliation</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when the account is no longer used — old entries stay, but it cannot be chosen in new documents</td></tr>
</table>

<h3>Add the company's real bank account</h3>
<ol>
    <li>The chart of accounts already has <code>10201</code> savings deposits and <code>10202</code> current deposits — if you have one of each, use them as they are. If you have several accounts, go to <b>Chart of accounts</b> and add a new code under group 102xx, e.g. <code>10203</code> "Kasikorn savings deposit 123-4-56789-0"</li>
    <li>Come back to this page, click <b>Add</b>, enter the code and name, then choose that account code</li>
    <li>Record the account's opening balance (money already there) in <b>Journal entries</b>: debit the bank account / credit capital or opening balance</li>
</ol>
<p class="text-secondary">CASH (petty cash → 10102) comes with the installation, and BANK1 comes from the sample data. Rename them or turn them off as you like</p>
