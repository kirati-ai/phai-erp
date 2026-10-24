<?php /** How to use the Journal entries page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p><b>All</b> of the company's accounting entries. One row = one entry (type + reference + date), both the ones documents post automatically and the ones entered by hand as a <b>General journal</b> (JE), such as opening balances, accrued expenses and tax remittances</p>
<p>The <b>Sales journal</b> menu is this page filtered to the <b>Sales</b> type (entries from invoices)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Every document that posts (goods receipts, payables, sales, receipts/payments, production, assets) and entries made by hand</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Trial balance</b> → period close</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>General journal (JE) — a general voucher for entries that have no source document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>JE: Dr / Cr as entered; it must balance (control accounts cannot be used)</td></tr>
</table>

<h3>Types (filter them in the status box)</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">General journal</td><td>Entered by hand on this page</td></tr>
    <tr><td class="text-nowrap">Inventory / Landed cost / Production</td><td>Receipts, issues, shipments, cost changes and work orders</td></tr>
    <tr><td class="text-nowrap">Sales / Receipts</td><td>Invoices, credit notes and customer payments</td></tr>
    <tr><td class="text-nowrap">Purchases / Voucher / Payments</td><td>Payables, payment vouchers and vendor payments</td></tr>
    <tr><td class="text-nowrap">Fixed assets</td><td>Asset receipts, depreciation, disposals</td></tr>
</table>

<h3>Making an entry by hand</h3>
<ol>
    <li>Press <b>Add</b>, enter the date (not before the open period) and a description</li>
    <li>On each line pick the account and the cost center (if any), and enter either a <b>Debit</b> or a <b>Credit</b>. Press <b>Add line</b> as needed (at least 2 lines)</li>
    <li>The <b>Difference</b> must be 0 (debits equal credits) before you can <b>Save</b></li>
</ol>
<p class="text-secondary">Example opening balance for a bank deposit: debit <code>10201</code> savings deposit 500,000 / credit <code>30101</code> share capital 500,000</p>
<p><b>Control accounts</b> (trade receivables, trade payables, inventory) are not in the list — they must be posted through documents such as invoices, payables or stock counts, so that the detail always matches the account</p>

<h3>Correcting an entry</h3>
<p>A saved entry cannot be changed. Open the general journal entry and press <b>Reverse</b>: the system makes a new entry that turns every line around, dated today. Then enter the correct entry again — entries from other documents are cancelled on the source document</p>
<p>Open any entry to see its debit/credit lines; you can also <b>Print</b> it</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: press <b>Attach</b> to add pictures or PDFs, several files if you like, such as supporting documents (contract, invoice, working) — click a file to open it, press × to remove it — press the paper clip at the end of a row in the list to open that row's attachments straight away without opening the document (a green clip with a number = how many files are attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status</p>
