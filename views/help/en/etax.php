<?php /** How to use the e-Tax Invoice (email) page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The log of every <b>e-Tax Invoice by Email</b> sending, both successful and failed — sent with the <b>Send e-Tax</b> button on the invoice / credit note pages. Each sending goes to 1 buyer, with a copy to the ETDA system, which time-stamps it and passes it on to the buyer</p>
<p><b>Documents waiting for e-Tax</b> (only when e-Tax is enabled) above the list shows tax invoices (ใบกำกับภาษี), credit notes (ใบลดหนี้) and debit notes (ใบเพิ่มหนี้) that are not cancelled and have never been sent successfully, counting from the month e-Tax started. Click <b>Send e-Tax</b> to open the document and send it from there</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Invoices / tax invoices</b> and <b>Invoices / credit notes</b> (Send e-Tax button)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The buyer + a copy to ETDA, which time-stamps it and sends the data to the Revenue Department</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Electronic tax invoice (ใบกำกับภาษีอิเล็กทรอนิกส์), e-Tax Invoice by Email, under Revenue Code s.86/4 and the Revenue Department Director-General's notification on e-Tax</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the tax invoice was posted when it was issued</td></tr>
</table>

<h3>Columns</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Kind</td><td><code>INV</code> tax invoice · <code>CDN</code> credit note · <code>DBN</code> debit note · <code>CIV</code> tax invoice cancellation notice</td></tr>
    <tr><td class="text-nowrap">Subject</td><td>In the format ETDA sets, e.g. <code>[06/10/2569][INV][IV2026100001]</code>; credit and debit notes add the number of the tax invoice they refer to at the end</td></tr>
    <tr><td class="text-nowrap">Error</td><td>Why a sending failed, e.g. a wrong SMTP password or a file larger than 3 MB</td></tr>
    <tr><td class="text-nowrap">Status</td><td><b>Sent</b> or <b>Failed</b></td></tr>
</table>
<p>Click a row to open that document. If it failed, fix the cause (see <b>e-Tax / email settings</b>) and click <b>Send e-Tax</b> on the document again</p>

<h3>When to send</h3>
<ul>
    <li>Invoices, credit notes and debit notes are sent after saving — enter the buyer's email (the default comes from the customer record); the system makes the PDF/A-3 by itself</li>
    <li>For an invoice that is already cancelled, click <b>Send e-Tax</b> again to send the cancellation notice (CIV)</li>
</ul>

<h3>XML of the month (XML method)</h3>
<p>If a certificate is uploaded in <b>e-Tax / email settings</b>, a month field and an <b>XML of the month</b> button appear at the top: you get a zip file with the signed XML of every tax invoice, credit note, debit note and receipt (ใบเสร็จรับเงิน) of that month (documents that could not be made are listed in <code>errors.txt</code>). Send it with "Upload XML" at <code>etax.rd.go.th</code> by the 15th of the following month</p>
<p class="text-secondary">Use either the email method or the XML method, not both — a cancelled document has no XML; issue a credit note instead</p>
