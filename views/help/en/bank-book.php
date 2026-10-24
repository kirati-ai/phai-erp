<?php /** How to use the bank book page (opened from the ? button, top right) */ ?>
<h3>What this page is</h3>
<p>Money in and out of <b>one bank account</b>, one month at a time, like a bank passbook: the balance brought forward, every entry with a <b>running balance</b>, and the month-end balance. The amounts come from the account code linked to the bank account in the general ledger.</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Entries posted to the bank account: <b>Cash receipts</b> <b>Customer deposits</b> <b>Vendor payments</b> <b>Petty cash</b> (withdraw from/return to bank) and <b>Journal entries</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Bank reconciliation</b> — the Reconciled column shows the statement date on which the entry was reconciled</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Bank book / bank account ledger</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — read only</td></tr>
</table>

<h3>How to use</h3>
<ul>
    <li>Pick the account and move between months with the arrows</li>
    <li>Click an entry to open its <b>Journal entries</b> record (to see which document it came from)</li>
    <li><b>Export Excel</b> and <b>Print</b> one month at a time</li>
</ul>
