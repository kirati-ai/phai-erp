<?php /** How to use the Output VAT report page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The monthly <b>Output VAT report</b> (รายงานภาษีขาย) for filing the <b>VAT return PP 30</b> (ภ.พ.30) together with the input VAT report — it shows every invoice / tax invoice (ใบกำกับภาษี), credit note (ใบลดหนี้) and debit note (ใบเพิ่มหนี้) of that month, both those made from shipments and those made by hand in <b>Invoices / credit notes</b>, in baht</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Invoices / tax invoices</b> and <b>Invoices / credit notes</b> of that month</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>File the PP 30 together with the <b>Input VAT report</b>, then record the closing of input and output VAT in <b>Journal entries</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Output VAT report under Revenue Code s.87</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it is a report (output VAT was posted when the invoice was issued)</td></tr>
</table>

<h3>Where the data comes from</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Date / Tax invoice no.</td><td>The date and number of the invoice — the report month follows this date, except for services (tax due on payment), which use the date and number of the <b>cash receipt</b>, a receipt / tax invoice (ใบเสร็จรับเงิน / ใบกำกับภาษี) for the part received</td></tr>
    <tr><td class="text-nowrap">Customer / Tax ID / Branch</td><td>From the <b>Customers</b> data — if blank or wrong, fix it in Sales → Customers</td></tr>
    <tr><td class="text-nowrap">Value / VAT</td><td>The amount before tax and the tax of that document × exchange rate — <b>credit notes are negative</b></td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Choose the <b>Tax month</b>, then check the items and the totals at the bottom of the table</li>
    <li>Compare the tax with the input VAT report of the same month: output VAT − input VAT = tax to pay (or credit carried forward)</li>
    <li>Click <b>Print</b> to get the report in landscape, and save it as PDF to keep with the filing</li>
</ol>
<p class="text-secondary">Cancelled tax invoices stay in the report with zero amounts and the word (cancelled) after the name, so every tax invoice number is there as the Revenue Department checks — the PP 30 is filed by the 15th of the next month (filing online may get extra time; check the Revenue Department's announcements)</p>
