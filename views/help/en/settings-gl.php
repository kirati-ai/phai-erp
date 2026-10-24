<?php /** How to use the Accounting settings page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The <b>default accounts</b> the system uses to post automatically from documents (goods receipts, payables, invoices, receiving and paying money, manufacturing) and the open accounting period. The installed values work as they are; change them when the company's chart of accounts is different</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set once when you start using the system, from the <b>Chart of accounts</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every document that posts automatically (when the product line or the business partner has no account of its own)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Accounting settings (table <code>gl_ctrl</code>) — default accounts and the open period</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it tells documents which accounts to post to</td></tr>
</table>

<h3>General tab</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Current fiscal year / Current period</td><td>The open period (cannot be changed here) — nothing can be posted before the 1st of this period. Move the period on with the <b>Close period</b> button on the <b>Trial balance</b> page</td></tr>
    <tr><td class="text-nowrap">Base currency</td><td>Normally THB — receivables, payables and the accounts are in this currency</td></tr>
    <tr><td class="text-nowrap">Retained earnings account</td><td>Profit and loss of earlier years shows in this account in the trial balance</td></tr>
</table>

<h3>Accounts tab</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Trade receivables / Trade payables</td><td>Used when the customer or vendor has no account of its own</td></tr>
    <tr><td class="text-nowrap">Sales revenue / Cost of goods sold / Inventory / Work in process</td><td>Used when the item's product line has no account set</td></tr>
    <tr><td class="text-nowrap">Output VAT / Input VAT</td><td>Invoices credit output VAT, payables debit input VAT — the input VAT report reads the tax from this input VAT account</td></tr>
    <tr><td class="text-nowrap">Goods received not invoiced</td><td>(GRNI) Goods receipts credit this account; payables debit it to clear it</td></tr>
    <tr><td class="text-nowrap">Direct labor</td><td>Labor recorded on work orders: debit work in process / credit this account</td></tr>
    <tr><td class="text-nowrap">Applied manufacturing overhead</td><td>Labor recorded on work orders: hours × the work center's machine and overhead rates, debit work in process / credit this account (the credits net off against the overhead actually paid; the difference is under- or over-applied overhead)</td></tr>
    <tr><td class="text-nowrap">Inventory adjustment</td><td>Price differences between goods receipt and payable, and stock adjustments whose reason code has no account</td></tr>
    <tr><td class="text-nowrap">Non-stock item expense</td><td>Purchases of non-stock items or typed-in lines, when the product line has no purchases account</td></tr>
    <tr><td class="text-nowrap">Vouchered payables</td><td>Payment vouchers move the payable to this account; the payment clears it</td></tr>
    <tr><td class="text-nowrap">Prepaid withholding tax</td><td>Tax the customer withheld when paying — debit this account</td></tr>
</table>
<p class="text-secondary">A changed account applies to documents saved from now on; old entries do not change</p>

<h3>Document numbers</h3>
<p>The table at the bottom of the page sets the prefix, format and next number of this module's documents (receipt (ใบเสร็จรับเงิน), deposit receipt, payable, payment voucher, withholding tax certificate (หนังสือรับรองการหักภาษี ณ ที่จ่าย), payment, general journal); then press the table's <b>Save</b> — it applies to new documents only. See the format details in System settings → <b>Document numbering</b>, which has every module in one place</p>
