<?php /** How to use the Customers page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>The customer register, used to pick the customer in quotations, sales orders, invoices, billing notes and cash receipts. The name, address and tax ID from this page are printed on the tax invoice (ใบกำกับภาษี)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Typed in by you, together with <b>Credit terms</b>, <b>Currencies</b>, <b>Taxes</b> and <b>Price lists</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Quotations</b> → <b>Sales orders</b> → <b>Shipments</b> → <b>Invoices / tax invoices</b> → <b>Billing notes</b> → <b>Cash receipts</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Customer master, the core of CRM — name, address, tax ID and branch must be complete to issue a full tax invoice (ใบกำกับภาษีเต็มรูป) under Revenue Code s.86/4</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the customer's receivable is created when the invoice is issued</td></tr>
</table>

<h3>Key fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Customer code</td><td>A short code to search by, e.g. <code>C001</code> or <code>SIAMTOOLS</code> (turned into capitals for you)</td></tr>
    <tr><td class="text-nowrap">Tax ID</td><td><b>Required</b>: 13 digits (a company number, or the national ID number of a person) with the branch (head office <code>00000</code>) and the address, province, district, sub-district and postcode — printed on the tax invoice, the output VAT report and the e-Tax XML; foreign customers can leave it blank — on the Trading tab, credit terms, currency and tax must be chosen (they give the due date, currency and VAT of sales documents)</td></tr>
    <tr><td class="text-nowrap">Branch</td><td>5 digits: <code>00000</code> or blank = head office, <code>00012</code> = branch 00012</td></tr>
    <tr><td class="text-nowrap">Status</td><td>Only <b>Active</b> customers can be picked in quotations and sales orders — <b>On hold</b> / <b>Inactive</b> stop sales to that customer</td></tr>
    <tr><td class="text-nowrap">Credit terms</td><td>Filled into sales documents and used to work out the due date of the invoice, e.g. 30 days</td></tr>
    <tr><td class="text-nowrap">Currency / Tax</td><td>Defaults for sales documents, e.g. THB and VAT 7%</td></tr>
    <tr><td class="text-nowrap">Credit limit</td><td>The highest open balance allowed (baht) — 0 = no limit; see the section below</td></tr>
    <tr><td class="text-nowrap">Salesperson</td><td>Filled into quotations and sales orders</td></tr>
    <tr><td class="text-nowrap">Special price list</td><td>A price list code from the <b>Price lists</b> page, e.g. <code>VIP</code> — this customer gets the prices of that list</td></tr>
</table>

<h3>Address (Address tab)</h3>
<ul>
    <li>Fill in separate fields: No., Moo, Building, Floor, Room, Village, Soi, Yaek, Road — the system joins them into one line on documents</li>
    <li>Choose the <b>Province</b>, then the <b>District</b> and <b>Sub-district</b>; the postcode fills in by itself</li>
    <li>Text that fits no field goes in <b>Address line 2</b></li>
</ul>

<h3>Credit limit</h3>
<p>If <b>Check credit limit</b> is on in <b>Sales settings</b>, saving a sales order adds up the open receivables + what is ordered but not yet invoiced (incl. VAT, in baht) + this order. If that is over the limit, the order <b>is saved with a warning</b>; it is not blocked</p>
<p class="text-secondary">A customer that already has documents (quotations, sales orders, shipments, invoices, cash receipts, billing notes) cannot be deleted; the system says "Cannot delete: this record is in use" — set the status to Inactive instead, so old documents can still refer to it</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section at the bottom of the window of a saved record: click <b>Attach</b> to add pictures or PDFs, several at a time, e.g. the VAT registration certificate PP 20 (ภ.พ.20), the company affidavit or the credit application — click a file to open it, click × to remove it — click the paperclip at the end of a row in the list to open the attachments window of that row without opening the record (a green paperclip with a number = how many files are attached)</p>
<p class="text-secondary">Attaching needs the add or edit permission of this page, removing needs edit or delete. You can attach in any status. Deleting a customer also deletes its attached files</p>
