<?php /** How to use the Withholding tax codes page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The <b>withholding tax</b> rates the company deducts when it pays vendors, chosen on the lines of <b>Payables</b> (default from the vendor) and actually deducted at <b>Vendor payments</b>. The system issues the <b>Withholding tax certificate, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย)</b> and puts it into the <b>Withholding tax returns (PND 3 / 53)</b> for you</p>
<p>A new installation already has: <code>WHT1</code> transport / advertising 1%, <code>WHT3</code> services / hire of work 3%, <code>WHT5</code> rent 5%</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The rates in the Revenue Department notifications (services 3%, rent 5%, transport 1%)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The default on <b>Vendors</b> → lines of <b>Payables</b> → <b>Vendor payments</b> → 50 Tawi and PND 3/53 (ภ.ง.ด.3/53)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Withholding tax rate master data — each code gives the rate, the income type (the row on the 50 Tawi form) and the account</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the code's account is used at payment (Cr Withholding tax payable)</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>The short name chosen when paying, e.g. <code>WHT2</code></td></tr>
    <tr><td class="text-nowrap">Description</td><td>The type of income paid, e.g. "Commission 3%" — printed on the 50 Tawi and the PND reports</td></tr>
    <tr><td class="text-nowrap">Rate %</td><td>The rate deducted, e.g. 1, 2, 3, 5</td></tr>
    <tr><td class="text-nowrap">Account code</td><td><b>Required</b> — the liability account where the tax withheld waits to be paid to the Revenue Department, e.g. <code>21103</code> Withholding tax payable</td></tr>
    <tr><td class="text-nowrap">Income type on the 50 Tawi certificate</td><td>The row on the 50 Tawi form the amount goes on — hire of work, services, rent, transport and advertising use row <b>5</b>; fees and commissions use row <b>2</b>; interest <b>4 (a)</b>; anything that fits no row uses <b>6. Other</b></td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when no longer used — it will not be offered for new payments</td></tr>
</table>

<h3>When a rate changes</h3>
<p>Just change the rate. Certificates already issued keep their old rate and row; they do not change</p>
<p class="text-secondary">Check the rate of each income type against the Revenue Department notifications. The form PND 3 (individuals) or PND 53 (companies) is chosen when paying; it is not tied to the code</p>
