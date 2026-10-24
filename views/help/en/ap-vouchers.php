<?php /** How to use the Payment vouchers page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>A <b>payment voucher (VO)</b> gathers several open payables of <b>one vendor</b> into one document. Print it for the preparer, the checker and the approver to sign before the money is really paid on the <b>Vendor payments</b> page</p>
<p><b>Payables waiting for a voucher</b> above the list shows payables and credit notes that are not on a voucher yet (red = past the due date, hidden when there are none). Tick the ones of the same vendor and click <b>Make voucher</b>: the system opens a new voucher with that vendor and the ticked items</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Payables</b> still open for one vendor (credit notes included)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Vendor payments</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Payment voucher (VO) — an internal document for approval before paying</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Accounts payable / Cr Vouchered payables</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Click <b>Add</b> and choose the vendor. The system shows the <b>Open payables</b> that are not on another voucher (credit notes included, with negative amounts)</li>
    <li>Tick the ones to pay this round, enter the <b>Pay date</b> (if any) and a remark</li>
    <li><b>Save</b>, then click <b>Print</b> and take it to be signed for approval</li>
    <li>When it is time to pay, click <b>Pay</b> on the voucher: the system opens a new payment with the vendor and the amount of this voucher</li>
</ol>

<h3>How it posts</h3>
<p>Debit the accounts payable account of each payable / credit <b>Vouchered payables</b> (Accounting settings) — the payables on this voucher become <b>Paid</b>, because the debt has moved to the voucher</p>
<p>The total must be more than zero. If the credit notes are more than the payables, choose more payables to cover them</p>

<h3>Status and cancelling</h3>
<ul>
    <li><b>To pay</b> → <b>Paid</b> once fully paid (part payments are allowed; it stays To pay until fully paid)</li>
    <li><b>Cancel document</b> only works on a voucher with no payment at all — the payables go back to Open and the posting is reversed with today's date. If it has been paid, cancel the payment first</li>
</ul>
<p class="text-secondary">If the payable lines carry a withholding tax code, <b>Withholding x%</b> and <b>Net to pay</b> show under the total (on screen and on the printout) — these are estimates and are not posted yet. The tax is posted and the withholding tax certificate, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย), is issued when you <b>Pay</b></p>
