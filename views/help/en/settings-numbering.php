<?php /** How to use the document numbering page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>Document numbers of every module in one place (the settings page of each module — purchasing, inventory, manufacturing, sales, accounting — has the same table with only that module's documents). It sets what the number of each document type (purchase order, invoice, receipt, etc.) looks like and what the next number is. The system gives the number itself when you save a document; you do not type it</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Starting values from the installation (prefixes such as PO SO)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The number of every document when it is saved</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Document Numbering — tax invoices (ใบกำกับภาษี) must be numbered in sequence (s.86/4), so numbers should not go backwards</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the document number is the reference of the accounting entries</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Prefix</td><td>Letters A-Z, <b>exactly 2</b>, e.g. <code>PO</code>, <code>IV</code></td></tr>
    <tr><td class="text-nowrap">Format</td><td>
        <b>Year-month + 4 digits</b> → <code>PO2026100001</code> starts again at 0001 every month (default)<br>
        <b>Short year-month + 4 digits</b> → <code>PO26100001</code> starts again every month<br>
        <b>Year + 5 digits</b> → <code>PO202600001</code> starts again every year<br>
        <b>6 digits</b> → <code>PO000001</code> keeps counting, never starts again</td></tr>
    <tr><td class="text-nowrap">Next number</td><td>The running number the next document will get (without the prefix and year-month)</td></tr>
    <tr><td class="text-nowrap">Example</td><td>Shows the number you will get as soon as you change a value; use it to check before saving</td></tr>
</table>

<h3>Changing document numbers</h3>
<ol>
    <li>Change the prefix, format or next number of the row you want; see the result in the <b>Example</b> column</li>
    <li>Click <b>Save</b> — this affects new documents only; numbers of existing documents do not change</li>
</ol>
<p>Example: moving from an old system that has already issued 25 invoices this month → set the <b>Next number</b> of invoices to <code>26</code></p>
<p class="text-secondary">Do not set the next number lower than numbers already used in the same month/year, because they will clash with existing documents — formats with a year or month start again at 1 by themselves in each new month/year</p>
