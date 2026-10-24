<?php /** How to use the e-Tax / email settings page (opened from the ? button, top right) */ ?>
<h3>What this page is for</h3>
<p>Send tax invoices (ใบกำกับภาษี) to the Revenue Department electronically. There are 2 ways; choosing one is enough</p>
<ul>
    <li><b>Top box — e-Tax Invoice by Email</b> (recommended for small businesses): the system emails the tax invoice PDF to the customer and sends a copy to ETDA for a time stamp. No digital certificate is needed</li>
    <li><b>Bottom box — XML e-Tax</b>: makes digitally signed XML files that you upload to the Revenue Department system yourself. It needs a company certificate. If you use the email way, you do not need to fill in this box</li>
</ul>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Your e-Tax registration with the Revenue Department, and the company's SMTP settings / digital certificate</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>e-Tax Invoice (email)</b> (this menu stays hidden until the email way is turned on or a certificate is uploaded for XML) and the Send e-Tax / XML e-Tax buttons on invoices, credit notes (ใบลดหนี้) and receipts (ใบเสร็จรับเงิน)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Settings for e-Tax Invoice by Email and for XML e-Tax Invoice & e-Receipt (ETDA recommendation 3-2560, ขมธอ.3-2560)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post — these are settings (kept in <code>config.php</code>)</td></tr>
</table>

<h3>Step 1 Register with the Revenue Department</h3>
<ol>
    <li>Go to <code>etax.rd.go.th</code> and apply for the e-Tax Invoice by Email service</li>
    <li>Register the company's <b>sender email</b> (the address that will send every tax invoice)</li>
    <li>Wait for the Revenue Department to approve, then turn on the "Use e-Tax Invoice by Email" switch</li>
</ol>
<p class="text-secondary">The conditions (such as the yearly income limit) and the steps to apply may change; check the Revenue Department website first</p>

<h3>Step 2 Fill in the top box</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Sender email</td><td>The email registered with the Revenue Department in step 1 — it must match</td></tr>
    <tr><td class="text-nowrap">Sender name</td><td>The name customers see in their mailbox, e.g. the company name</td></tr>
    <tr><td class="text-nowrap">Year in subject</td><td>Leave it on Buddhist. The email subject looks like <code>[06/10/2569][INV][IV2026100001]</code>, as ETDA requires</td></tr>
    <tr><td class="text-nowrap">Cc</td><td>Leave <code>csemail@etax.teda.th</code> as it is; do not change it</td></tr>
    <tr><td class="text-nowrap">SMTP …</td><td>The settings of the sender's email provider; see the next section</td></tr>
</table>

<h3>Where the SMTP settings come from</h3>
<p><b>Gmail / Google Workspace</b></p>
<ul>
    <li>SMTP server <code>smtp.gmail.com</code> · port <code>587</code> · encryption STARTTLS</li>
    <li>User name = the sender email</li>
    <li>Password = a 16-letter <b>App Password</b> (not the password you use to sign in to Gmail): open Google Account → Security → turn on "2-Step Verification" first → search for "App passwords" → create a new one, name it e.g. PhaiERP → copy it here</li>
</ul>
<p><b>Microsoft 365 / Outlook</b>: <code>smtp.office365.com</code> · port <code>587</code> · STARTTLS (the administrator must turn on "Authenticated SMTP" for that mailbox)</p>
<p><b>Company-domain email from a hosting provider</b>: ask your email administrator or hosting company for the SMTP server, port and encryption (often <code>mail.yourdomain</code>, port 465 SSL or 587 STARTTLS)</p>

<h3>Step 3 Test, then save</h3>
<ol>
    <li>Put your own email in the "Test email address" field and press <b>Send test</b> — a test email does not send a copy to ETDA</li>
    <li>When the email arrives, press <b>Save</b>. If it does not arrive, read the error message, then check the user name, password and port</li>
    <li>Send real tax invoices from the invoice page with the e-Tax button. See the sending history in Accounting → Receivables (AR) → e-Tax Invoice (email)</li>
</ol>
<p class="text-secondary">The password is kept in the config.php file on the server, not in the database, and is never shown back on this page — leave it blank when saving to keep the old one</p>

<h3>Bottom box: certificate (XML only)</h3>
<ul>
    <li>Buy an <b>electronic certificate for a juristic person</b> from a certificate authority (CA) the Revenue Department accepts, such as INET or Thai Digital ID — there is a yearly fee</li>
    <li>You get a <code>.p12</code> or <code>.pfx</code> file with a file password. Choose the file, enter the password, then press <b>Upload</b></li>
    <li>After that, download the XML of tax invoices, credit notes, debit notes (ใบเพิ่มหนี้) and receipts (one at a time, or a monthly zip) and upload them at <code>etax.rd.go.th</code></li>
</ul>
