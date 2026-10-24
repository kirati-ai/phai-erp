<?php /** How to use the Roles & permissions page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>A <b>role</b> is a set of rights for a job, e.g. sales, purchasing, accounting — each user has one role (set on the <b>Users</b> page) and sees only the menus and data that role has rights to, including the answers of <b>Ask AI</b></p>
<p>On the left is the list of roles; on the right is the rights table of the chosen role, grouped by menu section</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The system's list of screens (programs)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Each of the <b>Users</b> has one role → the menus, screens, API and AI they see</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Roles &amp; permissions (Role-Based Access Control) — used to separate duties, e.g. the person who orders is not the person who pays</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting</td></tr>
</table>

<h3>What each right means</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">View</td><td>Open that page and see its data — if not ticked, that menu item is not shown</td></tr>
    <tr><td class="text-nowrap">Add / Edit / Delete</td><td>Create new entries, change them and delete them (or cancel documents) on that page — the Edit right also covers the page's key actions: adjusting stock to a count, allocating landed costs, registering / depreciating / disposing of assets, and closing the accounting period (there is no separate approval right)</td></tr>
    <tr><td class="text-nowrap">Print</td><td>Print documents and save PDFs</td></tr>
</table>
<p>Tick any right and the system ticks <b>View</b> for you; untick <b>View</b> and the page's other rights go too — the tick box in a column header ticks or unticks the whole column, and the tick box in a section row (e.g. Master data, Purchasing) ticks or unticks only the screens in that section</p>

<h3>Create a new role</h3>
<ol>
    <li>Press <b>Add</b>, enter the role code (e.g. <code>SALES</code>) and the role name (e.g. "Sales team"), then press <b>Save</b></li>
    <li>Click the role on the left and tick the rights for each page on the right</li>
    <li>Press <b>Save</b> above the rights table (ticks have no effect until you save)</li>
    <li>Go to <b>Users</b> and give this role to the staff member</li>
</ol>
<p class="text-secondary">The <code>ADMIN</code> role always has every right and cannot be changed or deleted — rename or deactivate other roles with the pencil icon next to the role name. A deactivated role cannot be picked on the Users page</p>
