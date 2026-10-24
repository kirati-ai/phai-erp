<?php /** How to use the Payables page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>Records a <b>vendor invoice / tax invoice</b> (ใบกำกับภาษี) as a debt to pay, in baht. One payable can hold 2 kinds of lines: <b>receipts not yet invoiced</b> and/or <b>other charges</b> that have no receipt (rent, services, repairs)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Goods receipts</b> not yet invoiced, allocated <b>Landed costs</b>, or charges with no receipt</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Payment vouchers</b> → <b>Vendor payments</b>; input VAT goes to the <b>Input VAT report</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Payable (AP) from the vendor's invoice / tax invoice (Revenue Code s.86/4) · vendor credit note (ใบลดหนี้), type CM, s.86/10</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr GRNI (receipts) or Expense + Input VAT / Cr Accounts payable · a credit note is the other way round</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Type</td><td><b>Payable</b> (normal) or <b>Credit note</b> issued by the vendor (returned goods, a price cut)</td></tr>
    <tr><td class="text-nowrap">Payable account</td><td>Leave it blank to use the vendor's payable account, or the account in the accounting settings</td></tr>
    <tr><td class="text-nowrap">Vendor invoice no.</td><td>The number on the vendor's tax invoice, e.g. <code>IV-6910-0123</code> — the same vendor cannot use a number twice, and it shows in the input VAT report</td></tr>
    <tr><td class="text-nowrap">Date</td><td>The payable date — the input VAT report puts it in the month of this date; it cannot be before the open period</td></tr>
    <tr><td class="text-nowrap">Due</td><td>Leave it blank and the system works it out from the vendor's credit terms</td></tr>
    <tr><td class="text-nowrap">VAT (other charges)</td><td>Pick a rate such as VAT7 — the system works out input VAT from the line amounts (enter amounts before VAT)</td></tr>
</table>

<h3>Receipts to invoice</h3>
<p>Above the list are the goods receipts not yet invoiced, and the <b>Landed costs</b> (freight, shipping agent, duty, insurance) that are allocated but not yet invoiced by whoever charged them, one line each — with a tag for the kind and the document number (such as the AWB number) when there is one. Tick the receipts of the same vendor that are on the same invoice, then press <b>Make payable</b>: the system opens a new payable with that vendor and the ticked receipts — if you cancel the payable later, the receipts come back to this list</p>

<h3>Steps</h3>
<ol>
    <li>Pick the vendor; the system shows the goods receipts not yet invoiced. Tick the ones on this invoice </li>
    <li>For charges with no receipt, press <b>Add line</b> and pick the account, the cost center (if any), a description and the amount</li>
    <li>Check that the amount before tax, the tax and the total match the invoice, then <b>Save</b></li>
</ol>

<h3>How it posts</h3>
<ul>
    <li>Payable: debit Goods received not invoiced (the amount the receipt posted) or the expense, debit Input VAT / credit the payable account — a few satang of difference between the receipt and the payable goes to Inventory adjustment</li>
    <li>Credit note: pick the vendor's earlier payable in <b>Payable referenced</b> and enter charge lines only. It posts the other way round (debit Accounts payable / credit the expense and Input VAT) with a negative amount, which can be deducted on a payment voucher</li>
</ul>
<p>The <b>GL entry</b> button opens the posted entry; the <b>Print / PDF</b> button prints the payable</p>

<h3>Status and cancelling</h3>
<p><b>Open</b> → <b>Paid</b> when it is taken onto a <b>Payment voucher</b> (the debt moves to the voucher) — <b>Cancel document</b> works only while it is open: the system reverses the entry dated today and the receipts go back to waiting for a payable. If it is already on a payment voucher, cancel the voucher first</p>

<h3>Withholding tax by line</h3>
<p>Every line (goods receipts and other charges) has a <b>Withholding</b> field: pick that line's code, e.g. freight 1%, services 3% — a vendor with <b>Withholding tax</b> set on the vendor page (Trading tab) fills it in for you. When paying, the withholding table adds up the amounts before VAT by code for you (a part payment = in proportion)</p>

<h3>Changing an open payable</h3>
<p>The <b>Edit</b> button shows while the payable is still <b>Open</b> and not on a payment voucher: you can change the vendor invoice number, date, due date, remarks and payable account, add/change/delete charge lines, and change the withholding code of every line — lines from goods receipts cannot change their amount (it must match what came into stock). The system posts only the difference, as a change entry on the payable's date (today if that period is closed); it does not delete the old entry</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: press <b>Attach</b> to add pictures or PDFs, several files if you like, such as the vendor's tax invoice / invoice (evidence of input VAT) — click a file to open it, press × to remove it — press the paper clip at the end of a row in the list to open that row's attachments straight away without opening the document (a green clip with a number = how many files are attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status</p>
