<?php /** How to use the Menus page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>All the items of the left menu, used to rename menu items, change their order, change icons or add your own links (e.g. a link to a website you use often) — the installed menu works as is; you do not need to change it</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Installed in the order Master data → Purchasing → Inventory → Manufacturing → Sales → Accounting → System settings</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Everyone's left menu, showing only what their role may use</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>System menu — the menu order follows the ERP work flow</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Group</td><td>The number of the menu section, e.g. <code>1</code> Master data, <code>9</code> System settings</td></tr>
    <tr><td class="text-nowrap">Code</td><td>The code of the row, e.g. <code>1.11</code> — a row ending in <code>.0</code> is the section header (the name and icon of the section)</td></tr>
    <tr><td class="text-nowrap">Menu name</td><td>The text shown in the menu</td></tr>
    <tr><td class="text-nowrap">Link</td><td>The page to open, e.g. <code>/parts</code> or <code>/parts?type=RM</code> (opens already filtered) — enter <code>#</code> = a small heading inside the section, e.g. "Setup", "Transactions"</td></tr>
    <tr><td class="text-nowrap">Program code required</td><td>The menu item shows only to people with the <b>View</b> permission of this screen, e.g. <code>parts</code> — <code>*</code> = everyone sees it</td></tr>
    <tr><td class="text-nowrap">Icon</td><td>A Tabler icon name, e.g. <code>ti ti-package</code></td></tr>
    <tr><td class="text-nowrap">Sequence</td><td>Smaller numbers go on top — the sequence of the section headers (<code>.0</code>) sorts the sections</td></tr>
    <tr><td class="text-nowrap">Open in</td><td><b>Same page</b> or <b>New tab</b> (good for links to outside websites)</td></tr>
</table>

<h3>Move a menu item up or down</h3>
<ol>
    <li>Click the menu row you want, then click Edit</li>
    <li>Change the <b>Sequence</b> to a number between those of the two items it should sit between; if the numbers clash, move the number of the next item as well</li>
    <li>Click <b>Save</b> and refresh the page; the left menu changes to match</li>
</ol>
<p class="text-secondary">Deleting a menu row does not delete the screen or the permissions; it only removes the way in from the menu — do not delete the "Purchasing overview" item (<code>/purchase</code>), because the system then resets the whole menu to the default</p>
