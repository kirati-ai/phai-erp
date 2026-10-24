<?php /** How to use the withholding tax returns (PND 3 / 53) page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>A summary of the <b>withholding tax</b> the company withheld from vendors in one month, to file and pay to the Revenue Department <b>by the 7th of the following month</b>. The data comes from the withholding tax certificates, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย), issued in <b>Vendor payments</b> (cancelled ones not included)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Withholding certificates (50 Tawi)</b> of that month</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>File the return and pay the tax at the Revenue Department, then record the payment in <b>Journal entries</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>PND 3 / PND 53 (ภ.ง.ด.3 / ภ.ง.ด.53): PND 3 for payees who are individuals, PND 53 for juristic persons, under s.59, with attachments</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it is a report (paying the tax: Dr Withholding tax payable / Cr Bank in Journal entries)</td></tr>
</table>

<h3>Pick the form and month</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">PND 53</td><td>Income paid to <b>juristic persons</b> (companies, partnerships)</td></tr>
    <tr><td class="text-nowrap">PND 3</td><td>Income paid to <b>individuals</b></td></tr>
    <tr><td class="text-nowrap">Tax month</td><td>The month the money was paid (the date of the vendor payment)</td></tr>
</table>
<p>Each rate withheld is one line, showing the tax ID, branch, name and address, date paid, income type, rate, amount paid, tax withheld and the 50 Tawi number — the sequence number is per payee and matches the sequence number on the certificate</p>

<h3>Steps</h3>
<ol>
    <li>Pick the form, then the month; check the totals at the bottom of the table</li>
    <li>If a vendor's tax ID or address is wrong, fix it in Purchasing → <b>Vendors</b> (the report reads the current data)</li>
    <li>Click <b>Print</b> to get the return form (A4 portrait) followed by the attachment (A4 landscape, 6 payees per sheet) in the Revenue Department's layout; you can save it as PDF</li>
    <li>Repeat with the other form if that month has both individuals and juristic persons</li>
</ol>
<p class="text-secondary">You can file online at efiling.rd.go.th by entering the figures in this report. The tax withheld stays in the withholding tax payable account; once paid, record it in <b>Journal entries</b>: debit withholding tax payable / credit bank</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section below the table belongs to the form and month picked: click <b>Attach</b> to attach pictures or PDFs, several files if needed, e.g. the PND return as filed, the tax payment receipt — click to open, click × to delete</p>
<p class="text-secondary">Attaching needs the add or edit right of this page, deleting needs the edit or delete right. You can attach in any status</p>
