<?php /** How to use the Accounting and finance overview page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>A summary of the finances: open receivables and payables, overdue receivables, payables coming due, estimated VAT and bank accounts</p>

<h3>Accounting in an ERP system</h3>
<p>The accounting module is <b>Finance</b> — the end point of every module. Purchasing, inventory, manufacturing and sales documents post to the journal by themselves. It is split into <b>AP</b> payables, <b>AR</b> receivables, <b>GL</b> general ledger, <b>FA</b> fixed assets and <b>Tax</b> (VAT, withholding tax, e-Tax)</p>

<h3>Document flow</h3>
<ol>
    <li><b>Payables (AP)</b>: record the payable from the vendor's tax invoice (ใบกำกับภาษี) (Dr GRNI or expense / Dr Input VAT / Cr Accounts payable) → payment voucher → vendor payment (Dr Vouchered payables / Cr Bank / Cr Withholding tax payable) + withholding tax certificate, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย) → PND 3 / 53 report (ภ.ง.ด.3 / 53)</li>
    <li><b>Receivables (AR)</b>: invoice / tax invoice, Revenue Code s.86/4 (from sales or made by hand, including credit notes (ใบลดหนี้), s.86/10, and debit notes (ใบเพิ่มหนี้), s.86/9) → cash receipt / receipt (ใบเสร็จรับเงิน), s.105 (Dr Bank / Dr Prepaid withholding tax / Cr Accounts receivable)</li>
    <li><b>Tax</b>: input VAT report + output VAT report (s.87) → file the VAT return PP 30 (ภ.พ.30) · e-Tax Invoice by Email / XML</li>
    <li><b>Cash and bank</b>: petty cash, bank accounts, bank reconciliation</li>
    <li><b>GL</b>: general journal (JE) → trial balance → close period</li>
    <li><b>FA</b>: asset register → monthly depreciation (Dr Depreciation expense / Cr Accumulated depreciation)</li>
</ol>

<h3>Received from other modules</h3>
<ul>
    <li><b>Purchasing</b>: goods receipt Dr Inventory / Cr GRNI · landed costs (LC)</li>
    <li><b>Inventory</b>: receipts, issues, stock counts, cost changes (IC)</li>
    <li><b>Manufacturing</b>: issues, labor and output receipts, through work in process (WO)</li>
    <li><b>Sales</b>: shipments (cost of goods sold) and invoices (AR)</li>
</ul>

<h3>On this page</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Work cards of the module</td><td>Top row: one card per menu item in the "Transactions" part of this module. Each card says what that page does and shows the work from earlier steps waiting for it (the same set as the work queue on that page), with the count and the first 3 document numbers — red means overdue. Click the card title or a document number to open it</td></tr>
    <tr><td class="text-nowrap">Open receivables (AR)</td><td>Everything still to collect, with a list of receivables past their due date</td></tr>
    <tr><td class="text-nowrap">Open payables (AP)</td><td>Everything still to pay, with the items due within 7 days</td></tr>
    <tr><td class="text-nowrap">Estimated net VAT (PP.30)</td><td>Output VAT from this month's invoices − input VAT from this month's goods receipts — for the figures you actually file, use the <b>Output VAT report</b> and the <b>Input VAT report</b></td></tr>
    <tr><td class="text-nowrap">Cash in bank and on hand</td><td>Bank accounts in use</td></tr>
</table>
