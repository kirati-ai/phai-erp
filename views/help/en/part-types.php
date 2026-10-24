<?php /** How to use the Item types page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p><b>Item types</b> split items one level finer than <b>Item groups</b>, such as tables, chairs, shelves — pick one in the <b>Item type</b> field on the <b>Items</b> page. It has no effect on posting</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set up by you, as the level below <b>Item groups</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The <b>Item type</b> field on the <b>Items</b> page</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Item type, master data for grouping, not a document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post — accounts depend on the product line only</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Type code</td><td>A short code such as <code>TABLE</code>, <code>CHAIR</code>, <code>SHELF</code></td></tr>
    <tr><td class="text-nowrap">Type name</td><td>e.g. "Table"</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when no longer used — it will not show for picking on items</td></tr>
</table>
<p class="text-secondary">This item type is not the "Stock type" (FG / RM / FA), which is set on the <b>Product lines</b> — a type that items still use cannot be deleted</p>
