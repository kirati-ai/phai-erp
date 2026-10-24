<?php /** How to use the Quality control (QC) page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Record the results of quality inspections of items or materials</p>
<ul>
    <li><b>Output from work orders</b>: nothing to fill in on this page — click <b>Receive</b> in the <b>Receive output</b> section of the work order and the system makes the QC record for you, with the work order number, item, lot, good, defective and inspector</li>
    <li><b>Anything else</b>, such as goods received from vendors: click <b>Add</b> on this page</li>
</ul>
<p class="text-secondary">A QC record is a document — once saved it <b>cannot be edited or deleted</b> (the end of the row only has view and print). A record that came from a work order can only change through that work order, and not at all once the work order is closed — a QC record does not change stock quantities or statuses</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Work orders</b> (receiving into stock makes the QC record by itself) or goods from <b>Goods receipts</b> from vendors (added by hand)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The receive output section of the <b>Work order</b> and the QC pass rate in the <b>Manufacturing overview</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Quality inspection record (QC)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — it records results and does not change stock</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">No.</td><td>The system gives the number when you save</td></tr>
    <tr><td class="text-nowrap">Inspection date</td><td>Set to today for you; can be changed</td></tr>
    <tr><td class="text-nowrap">Result</td><td><b>Passed</b> (no defects) / <b>Partly passed</b> (some good, some defective) / <b>Failed</b> (all defective) / <b>Rework</b> — for records from a work order the system picks the result from the quantities</td></tr>
    <tr><td class="text-nowrap">Item number</td><td>Must be a code that exists in the system</td></tr>
    <tr><td class="text-nowrap">Lot</td><td>The lot inspected — for goods you make, lot = the work order number; for goods you buy, lot = the vendor's tax invoice number</td></tr>
    <tr><td class="text-nowrap">Reference document</td><td>The number of the document the goods came from, e.g. work order number <code>WO2026100003</code> or the goods receipt number</td></tr>
    <tr><td class="text-nowrap">Qty inspected</td><td>The total quantity inspected — can be left blank; the system uses good + defective</td></tr>
    <tr><td class="text-nowrap">Good / Defective</td><td>Quantity passed / failed — from a work order, good is the quantity received into stock; defective does not go into stock</td></tr>
    <tr><td class="text-nowrap">Inspector</td><td>Filled with the name of the logged-in user (if the user is linked to an employee). You can choose another employee or type a name</td></tr>
    <tr><td class="text-nowrap">Remarks</td><td>What was found, e.g. "Scratches on the surface of 3 pieces"</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Click <b>Add</b>, choose the result, and enter the item number, lot and reference document</li>
    <li>Enter the quantity inspected and remarks, then save — check carefully before saving, because it cannot be changed afterwards</li>
    <li>Click the <b>Print</b> icon at the end of the row to get the quality check record (QC) for the inspector and the approver to sign</li>
    <li>Search past records by number, reference document (e.g. work order number), item number or lot — clicking a QC number in the receive output section of a work order also opens this page</li>
</ol>
<p class="text-secondary">If goods fail and must be kept from use, change the status of that stock line on the <b>Stock on hand</b> or <b>Raw material stock</b> page (click the row → Edit)</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section at the end of the data window of a saved record: click <b>Attach</b> to attach pictures or PDFs, several files allowed, e.g. photos of defects, lab results, the vendor's certificate of analysis (COA) — click a file to open it, click × to remove it — click the clip icon at the end of a row in the list to open that row's attachments window straight away without opening the document (a green clip with a number = the number of files attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status</p>
