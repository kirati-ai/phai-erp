<?php /** How to use the Withholding certificates (50 Tawi) page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The list of <b>withholding tax certificates (50 Tawi)</b> (หนังสือรับรองการหักภาษี ณ ที่จ่าย) the company issues to vendors. You cannot create them on this page — the system issues one automatically when a payment with tax withheld is saved in <b>Vendor payments</b> (one payment = one certificate)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Vendor payments</b> with tax withheld (issued automatically)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>2 copies to the vendor · the totals go to <b>Withholding tax returns (PND 3 / 53)</b> (ภ.ง.ด.3 / 53)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Withholding tax certificate under Revenue Code s.50 bis (50 Tawi)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Posts nothing itself — the withholding tax payable was posted with the payment</td></tr>
</table>

<h3>Columns</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Form</td><td>PND 53 (vendor is a juristic person) or PND 3 (individual), as chosen at payment</td></tr>
    <tr><td class="text-nowrap">Reference</td><td>The number of the payment that issued this certificate</td></tr>
    <tr><td class="text-nowrap">Base amount / Tax withheld</td><td>Total of all rates on the certificate</td></tr>
    <tr><td class="text-nowrap">Status</td><td><b>Active</b> or <b>Cancelled</b> (when that payment is cancelled)</td></tr>
</table>

<h3>Print for the vendor</h3>
<ol>
    <li>Click the row or the print icon to open the certificate in a new tab on the Revenue Department form — 2 copies, one A4 page each (copy 1 the payee attaches to their tax return, copy 2 the payee keeps as evidence)</li>
    <li>Print it, or save it as PDF, and send it to the vendor, signed in the payer box</li>
</ol>
<p>Amounts go on the income type row set by the <b>Withholding tax code</b>. If several rates fall on the same row, the next rate goes on row "6. Others (specify)" with the type name. The sequence number on the certificate matches the order in that month's PND report</p>

<h3>Change or cancel</h3>
<p>A certificate cannot be changed directly — go to <b>Vendor payments</b>, press <b>Cancel document</b> on that payment (the certificate is cancelled with it), then record the payment again</p>
<p class="text-secondary">Tax withheld is totalled by month on the <b>Withholding tax returns (PND 3 / 53)</b> page, to be paid by the 7th of the following month</p>
