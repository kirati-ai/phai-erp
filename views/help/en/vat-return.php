<?php /** How to use the VAT return (PP 30) page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>Sums up the <b>monthly VAT</b> for filling in the <b>VAT return PP 30</b> (ภ.พ.30): output VAT − input VAT − credit brought forward = VAT to pay (or a credit carried forward to next month). Then press <b>File and post</b> to clear the output VAT and input VAT accounts for that month</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The <b>Output VAT report</b> — tax invoices (ใบกำกับภาษี), credit notes (ใบลดหนี้), debit notes (ใบเพิ่มหนี้) — and the <b>Input VAT report</b> (payables with input VAT) of the same month</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>File the PP 30 with the Revenue Department by the 15th of the next month, then record the tax payment in <b>Journal entries</b>: Dr VAT payable / Cr Bank</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>VAT return form PP 30 (Revenue Code s.83) — an overpayment can be carried forward to the next month or refunded; the system carries it forward</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Output VAT / Cr Input VAT / Cr VAT payable (if input VAT is larger: Dr VAT overpaid carried forward) — using a credit brought forward: Cr VAT overpaid carried forward</td></tr>
</table>

<h3>How to use it</h3>
<ol>
    <li>Choose the <b>Tax month</b> (last month by default) and check the figures against the output and input VAT reports</li>
    <li>Press <b>Print / PDF</b> for a summary sheet to fill in the form from</li>
    <li>Press <b>File and post</b> — the month's figures are stored (changing older documents later does not change figures already filed). Only a month that has ended can be filed, and months must be filed in order</li>
    <li>Filed by mistake: press <b>Undo filing</b>, for the latest month only; the system reverses the entries dated today</li>
</ol>
<p class="text-secondary">Posted on the last day of the tax month; if that period is already closed, on the first day of the open period</p>

<h3>Attached files</h3>
<p>Press the paper clip on a month's row in the <b>Filed</b> table to open the <b>Attachments</b> window of that month (a green clip with a number = number of files): press <b>Attach</b> to add pictures or PDFs, as many as you need, e.g. the filed PP 30 form, the tax payment receipt — click a file to open it, press × to delete it</p>
<p class="text-secondary">Attaching needs the add or edit right on this page, deleting needs the edit or delete right. You can attach in any status. Undoing a filing also deletes the attached files of that month</p>
