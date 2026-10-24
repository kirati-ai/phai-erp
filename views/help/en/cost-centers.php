<?php /** How to use the Cost centers page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p><b>Cost centers</b> split the balance of one account by department, division or job, e.g. electricity for production and electricity for the office both post to <code>50302</code>, but to different cost centers</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The company's structure of departments, divisions and jobs</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Picked on the lines of <b>Journal entries</b>, <b>Payables</b> and the <b>Asset register</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Cost center — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — a tag that splits amounts in ledger entries</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>A short, easy code, e.g. <code>ADM</code> office, <code>PRD</code> production, <code>SAL</code> sales</td></tr>
    <tr><td class="text-nowrap">Name</td><td>The name of the department or job</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when no longer used — old entries can still refer to it</td></tr>
</table>

<h3>Where it is used</h3>
<ul>
    <li><b>Journal entries</b> — the Cost center field on each line</li>
    <li><b>Payables</b> — Other charges lines</li>
    <li><b>Asset register</b> — an asset's depreciation posts to that asset's cost center</li>
</ul>
<p>See the amounts in the <b>Trial balance</b>: click an account to open its ledger, which shows the cost center on every line</p>
<p class="text-secondary">This field is optional — no need to create any if you do not want to split amounts. Only cost centers never used can be deleted</p>
