<?php /** How to use the Sub-accounts page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The list of <b>sub-account codes</b> (short codes of up to 2 characters), kept for splitting the balance of one account code, e.g. by branch or project</p>
<p>For now the document and journal screens <b>have no field for choosing a sub-account</b> — most businesses do not need to fill in this page. To split expenses by department or job, use <b>Cost centers</b>, which you can choose on journal lines and on payables</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The splits the company wants (branches, projects)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>No screen uses it yet — use <b>Cost centers</b> instead</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Sub-account — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>A short code, e.g. <code>01</code>, <code>BN</code></td></tr>
    <tr><td class="text-nowrap">Name</td><td>What the code means, e.g. "Bang Na branch"</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when the code is no longer used</td></tr>
</table>
