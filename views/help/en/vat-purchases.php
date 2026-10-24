<?php /** How to use the Input VAT report page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The monthly <b>Input VAT report</b> (รายงานภาษีซื้อ) for filing the <b>VAT return PP 30</b> (ภ.พ.30) together with the output VAT report — it shows every payable in <b>Payables</b> of that month that is not cancelled, in baht</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Payables</b> (the input VAT account lines) of that month</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>File the PP 30 together with the <b>Output VAT report</b>, then record the closing of input and output VAT in <b>Journal entries</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Input VAT report under Revenue Code s.87</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it is a report (input VAT was posted with the payable)</td></tr>
</table>

<h3>Where the data comes from</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Date</td><td>The payable date — the report month follows this date</td></tr>
    <tr><td class="text-nowrap">Tax invoice no.</td><td>The Vendor invoice no. field of the payable</td></tr>
    <tr><td class="text-nowrap">Vendor / Tax ID / Branch</td><td>From the <b>Vendors</b> data — if blank, fix it in Purchasing → Vendors</td></tr>
    <tr><td class="text-nowrap">VAT</td><td>The amount posted to the input VAT account (Accounting settings) on that payable</td></tr>
    <tr><td class="text-nowrap">Value</td><td>The payable amount less tax — credit notes from vendors are negative</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Choose the <b>Tax month</b>, then check the items and the totals at the bottom of the table</li>
    <li>Fix missing tax invoices (ใบกำกับภาษี) or wrong numbers at the source: a payable not yet recorded should be saved in the right month; a wrong payable should be cancelled and made again</li>
    <li>Click <b>Print</b> to get the report in landscape, and save it as PDF to keep with the filing</li>
</ol>
<p class="text-secondary">Payables without tax (e.g. bought from someone not registered for VAT) also show, with tax 0 — the PP 30 is filed by the 15th of the next month (filing online may get extra time; check the Revenue Department's announcements)</p>
