<?php /** How to use the Units of measure page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The list of <b>Units of measure</b> used for items and raw materials, e.g. piece, box, kilogram — chosen in the <b>Unit of measure</b> field of the <b>Items</b> page, and also usable as weight / size units</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Installed with the basic units; you can add more</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The <b>Unit of measure</b> field in <b>Items</b>, then shown in every document and in stock</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Unit of measure (UM) — basic reference data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>A short abbreviation in capitals, e.g. <code>PC</code>, <code>BOX</code>, <code>KG</code></td></tr>
    <tr><td class="text-nowrap">Unit name</td><td>The full name that is easy to read, e.g. "Piece", "Box of 12"</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when no longer used — the unit is no longer offered for new items, but existing items can still use it</td></tr>
</table>

<h3>Add a unit of measure</h3>
<ol>
    <li>Click <b>Add</b> and enter the code and the name</li>
    <li>Click <b>Save</b> (or press Enter)</li>
</ol>
<p class="text-secondary">A unit of measure used by items cannot be deleted; turn off "Active" instead — the system does not convert units: buying, selling and stock all use the one unit set on the item</p>
