<?php /** How to use the Vendor payments page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>Record the <b>actual payment (PV)</b> to a vendor against <b>Payment vouchers</b> waiting to be paid. You can pay several vouchers at once and withhold tax — the system issues the <b>withholding tax certificate, 50 Tawi</b> (หนังสือรับรองการหักภาษี ณ ที่จ่าย) for you</p>
<p><b>Vouchers waiting for payment</b> above the list shows the vouchers not yet fully paid, sorted by pay date (red = past the pay date, hidden when there are none). Click <b>Pay</b> and the system opens a new payment with the vendor and the full amount of that voucher</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Payment vouchers</b> waiting to be paid</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Withholding certificates (50 Tawi)</b> (if tax is withheld) → <b>Withholding tax returns (PND 3 / 53)</b> and <b>Bank reconciliation</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Payment (PV) — the proof that the money was received is the vendor's receipt (ใบเสร็จรับเงิน), Revenue Code s.105 bis</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Vouchered payables / Cr Bank (net amount) / Cr Withholding tax payable (each rate)</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Click <b>Pay</b> on the payment voucher (it fills in the vendor and amount), or click <b>Add</b> and choose the vendor</li>
    <li>Choose the date and the <b>Bank / cash</b> (the cash or bank account the money goes out of)</li>
    <li>In the table, enter the <b>Pay</b> amount for each voucher. The arrow button next to the number fills in the <b>Full amount</b> — part payments are allowed, up to the balance</li>
    <li>If tax must be withheld, choose the <b>Form</b> PND 53 (ภ.ง.ด.53) (companies) or PND 3 (ภ.ง.ด.3) (individuals), then fill in the withholding table under the items (see the next section)</li>
    <li>Check that <b>Net paid</b> matches the money really transferred, then <b>Save</b></li>
</ol>

<h3>Withholding table (several rates allowed)</h3>
<p>If the payable lines carry a withholding tax code, this table fills itself from the amount paid (the amounts before VAT added up by code) — once you edit the table yourself, the system stops filling it</p>
<table class="table table-sm">
    <tr><td class="text-nowrap">Withholding</td><td>A code from <b>Withholding tax codes</b>, e.g. WHT3 services 3%</td></tr>
    <tr><td class="text-nowrap">Base amount</td><td>For the first rate the system fills in the amount before VAT of what is paid; can be changed</td></tr>
    <tr><td class="text-nowrap">Rate % / Tax withheld</td><td>Worked out for you = base amount × rate</td></tr>
</table>
<p>One payment can cover several kinds of income, e.g. services 3% and transport 1%: click <b>Add rate</b> and split the base amount (a new row starts from what is left) — the base amounts together must not be more than the amount paid, and a code cannot be used twice. All rates go on one 50 Tawi and are separate lines in the PND report</p>
<p class="text-secondary">Example: pay an installation service of 10,700 (10,000 before VAT), withhold 3% = 300, net paid 10,400</p>

<h3>How it posts</h3>
<p>Debit Vouchered payables (amount paid) / credit the bank account (net paid) / credit the withholding tax payable account of each code</p>
<p>After saving there are the <b>50 Tawi</b> button (print the certificate) and <b>Print / PDF</b> (the payment — proof of the real payment, unlike the payment voucher, which is the approval before paying)</p>

<h3>Cancelling</h3>
<p>After <b>Cancel document</b> the payment voucher goes back to To pay, the 50 Tawi certificate is cancelled, and the posting is reversed with today's date — status <b>Posted</b> → <b>Cancelled</b></p>

<p class="text-secondary">Paying/receiving in <b>cash</b>: choose the <b>CASH petty cash (of the person making the entry)</b> account — the money comes out of / goes into the petty cash box of the person who makes this document (see the Petty cash page). The document can be cancelled while that month of the box is not yet counted</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: click <b>Attach</b> to attach pictures or PDFs, several files allowed, e.g. transfer slips, cheque copies, the vendor's receipt — click a file to open it, click × to remove it — click the clip icon at the end of a row in the list to open that row's attachments window straight away without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status</p>
