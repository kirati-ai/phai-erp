<?php /** How to use the Inventory statuses page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>The register of status codes for items in stock, with whether each status can be issued. The installation comes with <code>AC</code> ready to use and <code>HD</code> on hold</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Installed with <code>AC</code> and <code>HD</code></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>A reference register — the status of a stock line on the <b>Stock line</b> page uses 4 fixed values and does not read from this page</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Inventory status — inventory master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Status code</td><td>A short upper-case code, e.g. <code>AC</code>, <code>HD</code>, <code>QC</code></td></tr>
    <tr><td class="text-nowrap">Status name</td><td>e.g. "Ready to use", "Waiting for quality check"</td></tr>
    <tr><td class="text-nowrap">Available for issue</td><td>Stock in this status can be issued for sale or production</td></tr>
    <tr><td class="text-nowrap">Nettable</td><td>Counted in the stock on hand total</td></tr>
    <tr><td class="text-nowrap">Over-issue allowed</td><td>Allows issuing more than there is — normally left unticked</td></tr>
</table>

<h3>Status of a stock line</h3>
<p>The status on each stock line (lot/location) is set on the <b>Stock on hand</b> page › click the row › <b>Edit</b>. The <b>Status</b> field has 4 fixed values: Active, On hold, Expired, Quarantine — they do not come from the list on this page</p>
<p class="text-secondary">If you do not keep separate item statuses, you do not need to change this page</p>
