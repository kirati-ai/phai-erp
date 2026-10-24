<?php /** How to use the Production employees page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>The list of people who work in the factory. It is used to record labor on work orders, and it is the list to pick from in the <b>Requested by</b> field (purchase requisitions) and the <b>Inspector</b> field (quality control) — there is no payroll</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Work centers</b> and <b>Users</b> (one employee links to one user)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Labor recorded on <b>Work orders</b>, the requester on <b>Purchase requisitions</b>, the inspector in <b>Quality control (QC)</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Employee master — not a payroll system</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post by itself — the labor rate per hour is used to cost labor into work in process</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Employee code</td><td>e.g. <code>E001</code></td></tr>
    <tr><td class="text-nowrap">Sign-in user</td><td>If this employee also signs in to the system, pick their user name — one user can link to only one employee. Leave it blank for people who do not use the system</td></tr>
    <tr><td class="text-nowrap">Home work center</td><td>The work center where they usually work. It is used as the labor's work center when labor is recorded without choosing an operation or work center</td></tr>
    <tr><td class="text-nowrap">Type</td><td>Permanent / Temporary / Subcontractor</td></tr>
    <tr><td class="text-nowrap">Labor rate/hr</td><td>This person's pay per hour, e.g. <code>55</code> — enter 0 or leave it blank to use the work center's Labor rate/hr instead</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when the person leaves — the name no longer shows in the lists to pick from, but the history stays</td></tr>
</table>

<h3>What linking an employee to a user gives you</h3>
<ul>
    <li>On a new purchase requisition the Requested by field, and on a new quality check the Inspector field, are filled with the signed-in person's name (you can change it to someone else or type a name)</li>
    <li>The <b>Labor</b> tab of a work order picks the signed-in person's employee first</li>
</ul>

<h3>Steps</h3>
<ol>
    <li>Create the user first in <b>System settings › Users</b> (if this person will sign in)</li>
    <li>Come back to this page, press <b>Add</b>, enter the code, name, home work center and labor rate, then pick the sign-in user</li>
</ol>
<p class="text-secondary">An employee who already has labor recorded on a work order cannot be deleted — turn off Active instead</p>
