<?php /** How to use the Users page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>The list of people who can sign in to the system. Each has a username, a password and one <b>Role</b> that decides what they can see and do (permissions are set on the <b>Roles &amp; permissions</b> page)</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Roles &amp; permissions</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every screen and API checks permissions by role. The username is recorded on every entry and in the change history. It can be linked to <b>Production employees</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>User account register — part of internal control</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Username</td><td>Used to sign in: English letters, digits, <code>.</code> <code>_</code> <code>-</code>, e.g. <code>somchai.s</code> — it cannot be changed later</td></tr>
    <tr><td class="text-nowrap">Full name / Email</td><td>Of that employee</td></tr>
    <tr><td class="text-nowrap">Role</td><td>Pick from the active roles</td></tr>
    <tr><td class="text-nowrap">Password</td><td>At least 8 characters, required when adding a new user — when editing, leave it blank if not changing it</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when the employee leaves — they can no longer sign in, but the history of what they did stays</td></tr>
</table>

<h3>Adding a user</h3>
<ol>
    <li>Check that a suitable role exists; if not, create it first in <b>Roles &amp; permissions</b></li>
    <li>Press <b>Add</b>, fill in the username, name and email, pick the role, set a temporary password, then press <b>Save</b></li>
    <li>Give the username and password to the employee, and have them change the password themselves from the user name menu at the top right</li>
</ol>

<h3>An employee forgot the password</h3>
<p>Click the employee's name, enter a new password in the <b>New password</b> box, then save — after 5 wrong passwords in 15 minutes the account is locked for a while; wait a moment and try again</p>
<p class="text-secondary">Users cannot be deleted; turn them off instead, so the data change history can always refer to them — you cannot disable yourself or change your own role</p>
