<?php /** How to use the Company information page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The company details printed at the top of every document (purchase orders, quotations, invoices / tax invoices (ใบกำกับภาษี), receipts (ใบเสร็จรับเงิน), withholding tax certificates, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย), etc.) and used in the PND forms (แบบ ภ.ง.ด.) and e-Tax — fill them in completely before issuing the first document</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Filled in after the install; the address uses the province, district and subdistrict tables</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The heading of every printed document, 50 Tawi, PND forms, and the seller in e-Tax</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Company profile — the name, address, tax ID and branch that a tax invoice must show under Revenue Code s.86/4</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the base currency on this page is the currency every ledger entry is recorded in</td></tr>
</table>

<h3>Parts of the page</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Company logo</td><td>Press <b>Change logo</b> and pick a PNG, JPG or WebP picture of any size (the browser shrinks it to at most 1000px on the long side before sending) (a transparent or white background prints best) — <b>Remove</b> to print without a logo</td></tr>
    <tr><td class="text-nowrap">Company address</td><td>Press <b>Edit</b> and fill in the separate fields as on the company certificate (หนังสือรับรอง) or the VAT registration certificate PP 20 (ภ.พ.20) (No., Moo, Building, Floor, Room, Village, Soi, Yaek, Road), then pick the province, district and subdistrict — the system joins them into one line on documents. Leave fields that do not apply empty</td></tr>
    <tr><td class="text-nowrap">Company name</td><td>The full registered name, e.g. "Example Trading Co., Ltd."</td></tr>
    <tr><td class="text-nowrap">Tax ID</td><td>The company's 13-digit number</td></tr>
    <tr><td class="text-nowrap">Branch</td><td><code>00000</code> = head office. For a branch, enter the 5-digit branch number from the PP 20, e.g. <code>00001</code></td></tr>
    <tr><td class="text-nowrap">Base currency</td><td>The currency used for posting, normally <code>THB</code> — do not change it after going live</td></tr>
    <tr><td class="text-nowrap">Contact, Phone, Email</td><td>Printed on documents so customers and vendors can contact you</td></tr>
    <tr><td class="text-nowrap">Sign out when idle</td><td>The number of minutes without moving the mouse, pressing a key or touching the screen before the system signs you out (<code>0</code> = never) — it counts quietly and shows no number. Using any browser tab restarts the count in every tab, and the server ends the session too, even if the page is closed</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Upload the logo (saved as soon as you pick the file)</li>
    <li>Press <b>Edit</b> in the address box, fill it in, then press that box's <b>Save</b></li>
    <li>Fill in the form below, then press <b>Save</b> under the form</li>
    <li>Print a document to check the heading</li>
</ol>
<p class="text-secondary">The address box and the form below are saved separately — changes to the company details apply to documents printed from now on, including reprints of old documents</p>
