<?php /** How to use the Bank reconciliation page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>Compare the <b>money in and out of the bank account in the system</b> with the <b>bank statement</b> at every month end (or more often) to find money that has not arrived, cheques not yet cleared, or entries someone forgot to record. Amounts are in baht, for the account code linked to the bank account</p>
<p><b>Banks with lines to reconcile</b> at the top lists the bank accounts with lines up to today that are not reconciled yet, how many there are, and the last reconciliation date (hidden when there are none). Press <b>Bank reconciliation</b> to pick that account</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Money in and out posted to the bank account (<b>Cash receipts</b>, <b>Vendor payments</b>, <b>Journal entries</b>) + the bank statement</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Forgotten items (bank fees, interest) are recorded in <b>Journal entries</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Bank reconciliation statement</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it only matches entries already posted against the statement</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Choose the <b>Bank / cash</b> account and the <b>Statement date</b> (it must be after the last reconciliation date)</li>
    <li>The system lists the lines of the account not yet reconciled, up to the statement date — tick every line that <b>appears on the statement</b> (you can click anywhere on the row)</li>
    <li><b>Balance after</b> at the top = last reconciled balance + money in ticked − money out ticked. It must equal the closing balance on the real statement; when it does, press <b>Save</b> (this balance is kept as the statement balance of that reconciliation)</li>
</ol>

<h3>Balance after does not match the statement</h3>
<ul>
    <li>Lines on the statement that are not in the system (bank fees, interest, transfers not yet recorded as received) — record the document or an entry in <b>Journal entries</b> first, e.g. a bank fee: debit <code>50802</code> Bank fees / credit the bank account, then come back and tick it</li>
    <li>An amount differs: check the amount in the source document</li>
</ul>
<p>Lines you do not tick (deposits in transit, cheques the payee has not cashed yet) stay open for you to tick next time</p>

<h3>History, print, undo</h3>
<p>Below the table are the saved reconciliations. The print button gives the <b>Bank reconciliation statement</b>: statement balance + deposits in transit − payments the bank has not recorded yet = book balance — only the <b>latest one</b> can be undone, and its lines come back to be ticked again</p>
<p class="text-secondary">The first time, the last reconciled balance is 0, so you also tick the lines from the opening balance. Reconciling posts nothing to the accounts</p>

<h3>Attached files</h3>
<p>Press the paper clip on a row of the reconciliation history to open the <b>Attachments</b> window of that reconciliation (a green clip with a number = number of files): press <b>Attach</b> to add pictures or PDFs, as many as you need, e.g. the bank statement for the period — click a file to open it, press × to delete it</p>
<p class="text-secondary">Attaching needs the add or edit right on this page, deleting needs the edit or delete right. You can attach in any status. Undoing a reconciliation also deletes its attached files</p>
