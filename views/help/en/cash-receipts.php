<?php /** How to use the Cash receipts page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>Records <b>money received from customers</b> against open invoices. You can receive several invoices at once, record the tax the customer withheld, and print the <b>Receipt</b> (ใบเสร็จรับเงิน). Amounts are in baht</p>
<p><b>Invoices waiting for payment</b> above the list shows invoices and debit notes (ใบเพิ่มหนี้) not yet fully paid, by due date (red = overdue, hidden when there are none). Press <b>Receive</b> and the system opens a new receipt with the customer and the full amount of that invoice</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Open <b>Invoices / tax invoices</b>, debit notes or <b>Billing notes</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Open receivables</b> go down · <b>Bank reconciliation</b> · e-Tax receipt (XML)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Receipt (CR) under Revenue Code s.105 bis — tax the customer withheld is backed by the customer's withholding tax certificate, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Dr Bank + Prepaid withholding tax / Cr Trade accounts receivable</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Press <b>Add</b> (or <b>New receipt</b> on the Open receivables page) and pick the customer. The system shows the open invoices by due date</li>
    <li>Pick the date and the <b>Bank / cash</b> account the money goes into (cash or a bank account)</li>
    <li>Enter the <b>Received</b> amount for each invoice. The arrow button next to the number fills in the <b>Full amount</b> — part payments are fine</li>
    <li>If the customer withheld tax, enter it in the <b>Tax withheld</b> field of that invoice (received + tax withheld = the amount cleared, no more than the balance)</li>
    <li>Check that the total matches the money actually received, then <b>Save</b>, and then <b>Print / PDF</b> the receipt</li>
</ol>
<p class="text-secondary">Example: invoice 10,700 (service fee before VAT 10,000), the customer withholds 3% = 300. Enter Received 10,400 and Tax withheld 300 — ask the customer for the 50 Tawi certificate and keep it</p>

<h3>Deducting credit notes</h3>
<p>Unused credit notes (ใบลดหนี้) show in the list with a <b>negative balance</b>. Enter a negative amount (or press full amount) to deduct it from invoices on the same receipt — the net amount received cannot be negative, and credit notes have no withholding tax</p>

<h3>How it posts</h3>
<p>Debit the chosen bank account (received) and <b>Prepaid withholding tax</b> / credit the customer's receivable — invoices received in full become <b>Paid</b>, and the billing notes that hold them are updated too</p>
<p>Receiving a <b>services</b> invoice (output VAT due on payment, Revenue Code s.78/1): the VAT for the share received falls due now — debit <b>Output VAT not yet due</b> / credit <b>Output VAT</b> (the last payment takes all that is left). This receipt prints as a <b>Receipt / tax invoice</b> with the service value and VAT, and is in the output VAT report of the month the money is received</p>

<h3>e-Tax as XML</h3>
<p>If a certificate is uploaded in <b>e-Tax / email settings</b>, there is an <b>XML e-Tax</b> button that gives a signed receipt (T01). Each line is an invoice that was paid</p>

<h3>Cancelling</h3>
<p><b>Cancel document</b> puts the invoices back to open and reverses the ledger entry with today's date — status <b>Posted</b> → <b>Cancelled</b>. The receipt date cannot be before the open accounting period</p>

<p class="text-secondary">Paying or receiving in <b>cash</b>: pick the account <b>CASH Petty cash (of the person making the entry)</b> — the money leaves or enters the petty cash box of the person who makes this document (see the Petty cash page). The document can be cancelled while that month of the box is not yet counted</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under a saved document: press <b>Attach</b> to attach several pictures or PDFs, e.g. the customer's transfer slip, the withholding tax certificate (50 Tawi) the customer issued for tax withheld from us — click to open, press × to remove — press the paper clip at the end of a row in the list to open that row's attachments window without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right of this page; removing needs the edit or delete right. Files can be attached in any status</p>
