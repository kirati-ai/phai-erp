<?php /** How to use the Financial statements page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>The <b>Statement of financial position</b> (งบแสดงฐานะการเงิน), as at the "To" date, and the <b>Income statement</b> (งบกำไรขาดทุน), from "From" to "To", built from the whole general ledger. Nothing more needs to be entered. Figures in brackets are negative</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Every posted entry (purchasing, sales, inventory, production, payments, receipts, journal entries, depreciation, VAT return) — check that the <b>Trial balance</b> balances first</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Management, the auditor, and the yearly filing of financial statements (DBD e-Filing) together with the corporate income tax return PND 50 (ภ.ง.ด.50)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Statement of financial position and income statement, as required by the Accounting Act B.E. 2543 (2000)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post (report)</td></tr>
</table>

<h3>How accounts are grouped</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Current assets</td><td>Accounts 10–13 (cash, bank, receivables, inventory, input VAT) — the rest of group 1 are non-current assets (land, buildings, machinery, less accumulated depreciation)</td></tr>
    <tr><td class="text-nowrap">Current liabilities</td><td>Accounts 20–22 (payables, taxes payable, short-term loans) — 23 and up are non-current liabilities</td></tr>
    <tr><td class="text-nowrap">Equity</td><td>Capital, retained earnings and <b>Profit (loss) for the year</b>, which is worked out as revenue − expenses since 1 January — so total assets always equal total liabilities and equity</td></tr>
    <tr><td class="text-nowrap">Income statement</td><td>Revenue from sales (401) − cost of sales (501) = gross profit + other income − selling and administrative expenses − finance costs (510) = profit before tax − income tax (520) = net profit</td></tr>
</table>
<p class="text-secondary">The groups are read from the account codes in the chart of accounts that came with the install. If you add your own accounts, use codes in the same range as the group you want</p>
