<?php /** How to use the Vendors page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>The register of <b>Vendors</b> (shops and companies we buy from or hire), chosen on purchase orders, payables, payments and withholding tax certificates, 50 Tawi (หนังสือรับรองการหักภาษี ณ ที่จ่าย) — fill it in once and the documents pick it up by themselves</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Information from the vendor (company certificate (หนังสือรับรองบริษัท), VAT registration PP 20 (ภ.พ.20), tax ID)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Purchase orders</b> → <b>Goods receipts</b> → <b>Payables</b> → <b>Payment vouchers</b> → <b>Vendor payments</b> → 50 Tawi</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Vendor master — the 13-digit tax ID and the branch are used on the 50 Tawi and on PND 3/53 (ภ.ง.ด.3/53)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — but the payables account and withholding tax code set here are the defaults for payables</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Vendor code</td><td>A short name in capitals, e.g. <code>V001</code> or <code>ABCSTEEL</code>; it cannot be changed later</td></tr>
    <tr><td class="text-nowrap">Tax ID</td><td><b>Required</b>, 13 digits, taken from the vendor's tax invoice (ใบกำกับภาษี); used in the input VAT report, the 50 Tawi and PND 3/53 — foreign vendors (country other than Thailand) do not need it</td></tr>
    <tr><td class="text-nowrap">Address</td><td><b>Required</b>: province, district, sub-district and postcode (Address tab) — printed on the 50 Tawi and on purchase orders</td></tr>
    <tr><td class="text-nowrap">Branch</td><td>The branch shown on the tax invoice: head office is <code>00000</code>, branch 1 is <code>00001</code></td></tr>
    <tr><td class="text-nowrap">Status</td><td><b>Active</b> can be chosen on documents · <b>On hold</b> and <b>Inactive</b> cannot be chosen on new purchase orders; old documents stay as they are</td></tr>
    <tr><td class="text-nowrap">Address tab</td><td>Fill in the separate fields (No., Moo, Building, Soi, Road …) and the system joins them into one address line for printing. Choose the province first, then the district, sub-district and postcode follow. Leave fields you do not have empty</td></tr>
    <tr><td class="text-nowrap">Credit terms / Currency / Tax</td><td>(Trading tab) <b>All three are required</b> — they are the defaults filled in when this vendor is chosen on a purchase order, e.g. 30 days credit, THB, VAT 7% — and can be changed on the purchase order</td></tr>
    <tr><td class="text-nowrap">Bank tab</td><td>The account the vendor wants payments transferred to, shown on the payment voucher</td></tr>
</table>

<h3>Add a vendor</h3>
<ol>
    <li>Press <b>Add</b> and enter the code, name, tax ID and branch</li>
    <li>On the <b>Address</b> tab, fill in the address as it appears on the vendor's tax invoice</li>
    <li>On the <b>Trading</b> tab, choose the credit terms, currency and tax, then press <b>Save</b></li>
</ol>
<p class="text-secondary">Many vendors: press the <b>Export to Excel</b> icon for a file with all the columns, add or change rows in the file, then press <b>Import from Excel</b> — existing codes are updated and new ones added; if any row is wrong, nothing in the file is saved</p>
<p class="text-secondary">Vendors that already have documents (requisitions, purchase orders, receipts, payables, payments, 50 Tawi) cannot be deleted; the system says "Cannot delete: this record is in use" — change the status to Inactive instead</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section at the bottom of the window of a saved record: press <b>Attach</b> to add pictures or PDFs, as many as you need, e.g. the PP 20, the company certificate, the bank book page (the account to pay into) — click a file to open it, press × to delete it — press the paper clip at the end of a row in the list to open that row's attachments window straight away, without opening the record (a green clip with a number = the number of attached files)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page, deleting needs the edit or delete right. You can attach in any status. Deleting a vendor also deletes its attached files</p>
