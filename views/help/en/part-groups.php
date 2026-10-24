<?php /** How to use the Item groups page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p><b>Item groups</b> sort items into categories for viewing and searching, e.g. wooden furniture, steel furniture, spare parts — you choose one in the <b>Item group</b> field of the <b>Items</b> page</p>
<p>Unlike <b>Product lines</b>, item groups have no effect on posting; they are only a handy sub-division (the layers are: product line → item group → item type)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>You set them up to match the company's own categories</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The <b>Item group</b> field on the <b>Items</b> page, used for searching and grouping</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Item group — master data for categorising, not a document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — accounts depend on the product line only</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Group code</td><td>A short code, e.g. <code>WOOD</code>, <code>STEEL</code>, <code>SPARE</code></td></tr>
    <tr><td class="text-nowrap">Item group name</td><td>e.g. "Wooden furniture"</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when the group is no longer used — it will not be offered on items</td></tr>
</table>
<p class="text-secondary">A group that items use cannot be deleted; turn "Active" off instead — you do not have to set up groups if you do not need them</p>
