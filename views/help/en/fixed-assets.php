<?php /** How to use the fixed asset register page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The company's <b>Fixed asset register</b> (machines, vehicles, computers, buildings) from registration, through monthly depreciation and location transfers, until sale or write-off. Status: <b>Draft</b> → <b>Active</b> → <b>Disposed</b></p>
<p>Above the list are waiting jobs (hidden when there are none, only for users with edit rights): <b>Assets waiting to be activated</b> are drafts — click <b>Activate</b> to open that asset; and <b>Depreciation waiting to be posted</b> is each month up to last month that has no depreciation yet (red = more than one month behind) — click <b>Post depreciation</b> to open the monthly depreciation page for that month</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Bought through a <b>Purchase order</b> → <b>Goods receipts</b> (fixed asset product line) → <b>Payables</b> or a journal entry, then registered here</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Monthly depreciation → <b>Trial balance</b> (closing a period needs all depreciation posted) → disposal</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Fixed Asset Register — depreciation under Royal Decree No. 145 (the maximum tax rates)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Activate: Dr Fixed assets / Cr the account chosen · Depreciation: Dr Depreciation expense / Cr Accumulated depreciation · Disposal: Dr Money received + Accumulated depreciation (± gain/loss) / Cr Fixed assets</td></tr>
</table>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Asset category</td><td>Brings in the depreciation method, useful life, rate and accounts from <b>Fixed asset categories</b> (method, life and rate can be changed for this asset only)</td></tr>
    <tr><td class="text-nowrap">Acquisition cost / Salvage value</td><td>Cost including installation (before VAT) and the value expected to be left at the end of its life, e.g. cost 120,000, salvage 1 baht</td></tr>
    <tr><td class="text-nowrap">Acquisition date / In-service date</td><td>Depreciation is charged for full months from the month it goes into service, not day by day</td></tr>
    <tr><td class="text-nowrap">Site / Location / Cost center / Custodian</td><td>Where the asset is. Depreciation is posted to this cost center — pick the custodian from the employee list, or type a name</td></tr>
    <tr><td class="text-nowrap">Vendor</td><td>Pick from the vendor list</td></tr>
    <tr><td class="text-nowrap">Purchase document reference</td><td>Pick from the goods receipts of fixed asset (FA) line items = the vendor's tax invoice/delivery note number on that receipt. The system fills in the vendor, acquisition date, unit cost and category (the receipt already posted the asset, so at activation choose <b>Cost already booked</b>) — for other purchases type the document number yourself</td></tr>
    <tr><td class="text-nowrap">Opening accumulated depreciation / Opening balance up to month</td><td>Only when bringing in an existing register: the depreciation already charged, and the last month that amount covers (yyyy-mm)</td></tr>
</table>

<h3>Registering</h3>
<ol>
    <li>Click <b>Add</b>, fill in the data, then <b>Save draft</b> (a draft can be edited and deleted)</li>
    <li>Check the <b>Projected depreciation</b> table, then open the <b>Activate / acquire asset</b> section and choose how the cost is posted:
        <ul>
            <li><b>Cost already booked / opening balance</b> — bought through a purchase order/payable or a journal entry already, or bringing in an existing register: nothing is posted again</li>
            <li><b>Post the cost now</b> — choose the <b>Credit account</b>; the system posts debit the asset account / credit that account (no payable or input VAT is created)</li>
        </ul>
    </li>
</ol>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section below the asset data: click <b>Attach</b> to attach pictures or PDFs, several files if needed, e.g. the tax invoice, warranty, pictures of the asset, title deed, manual — click to open, click × to delete — click the paper clip at the end of a row in the list to open the attachments window of that row without opening the document (a green clip with a number = the number of files attached). Attaching needs the add or edit right, deleting needs the edit or delete right. You can attach in any status. Deleting a draft asset deletes its attachments too</p>

<h3>Monthly depreciation</h3>
<p>The <b>Monthly depreciation</b> button on the list page: enter the month, click <b>Calculate / check</b> to see the check result of each asset, tick and click <b>Post selected</b> (or <b>Post depreciation</b> for one asset on the asset page) — debit depreciation expense / credit accumulated depreciation, dated the last day of the month</p>
<p>Months must be posted in sequence, must have ended, and must be in an open period. The same month cannot be posted twice. The last month is adjusted to leave exactly the salvage value</p>

<h3>Transfer, sale, reversal</h3>
<ul>
    <li><b>Transfer location / custodian</b> — change the site, cost center, location or custodian; no posting</li>
    <li><b>Dispose / write off</b> — first post depreciation up to the month before disposal (there is no depreciation in the disposal month). Enter the net sale price and the <b>Proceeds / disposal clearing account</b>: the system clears the cost and accumulated depreciation, and the difference goes to the gain/loss on disposal account — record output VAT and the money received in a separate accounting document, using the clearing account so nothing is posted twice</li>
    <li><b>Reverse latest</b> — corrects the latest entry that was wrong. Enter a date (in an open period, not before the original entry) and a reason. The old history stays</li>
</ul>
<p>Print from <b>Print / PDF</b> on the asset page, <b>Asset register / PDF</b> on the list page and <b>Depreciation report / PDF</b> on the monthly depreciation page</p>
<p class="text-secondary">Drafts, transfers, acquiring the asset, posting depreciation, disposal and reversal use the edit right of the asset register</p>
