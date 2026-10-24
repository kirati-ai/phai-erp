<?php /** How to use the reason codes page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Reasons why stock goes up or down without a purchase or sales document, e.g. damaged goods, expired, lost, or extra found during a count. Each code is linked to an <b>Account</b> to which the system posts the value of the difference by itself</p>
<p>Picked on the <b>Stock counts</b> page (when creating a count) and the <b>Receive / issue</b> page (including the Price adjust tab) — with no reason, the system posts to the <b>Inventory adjustment</b> account in the accounting settings</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Chart of accounts</b> (the account linked to each code)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Stock counts</b> and <b>Receive / issue</b> (including price adjust)</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Reason Code — tells the cause and the account of a stock adjustment that has no purchase or sales document, so it can be audited later</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post by itself — stock up: Dr Inventory / Cr the code's account; stock down: Dr the code's account / Cr Inventory</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Reason code</td><td>A short code in capitals, e.g. <code>DMG</code>, <code>EXP</code>, <code>FOUND</code></td></tr>
    <tr><td class="text-nowrap">Reason</td><td>A description, e.g. "Damaged goods"</td></tr>
    <tr><td class="text-nowrap">Applies to</td><td><b>Receipt</b> (stock up) or <b>Issue</b> (stock down)</td></tr>
    <tr><td class="text-nowrap">Account</td><td>The account paired with the inventory account, e.g. damaged goods → miscellaneous expenses <code>59901</code>, found extra → other income <code>40901</code></td></tr>
    <tr><td class="text-nowrap">Cost center</td><td>Optional; the department responsible</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when no longer used; the code will not be offered any more</td></tr>
</table>

<h3>Codes that come with the installation</h3>
<ul>
    <li><code>OPEN</code> opening balance (receipt → retained earnings <code>30301</code>) — used when setting the starting stock through <b>Stock counts</b></li>
    <li><code>FOUND</code> extra stock found · <code>USE</code> internal use · <code>DMG</code> damaged · <code>EXP</code> expired · <code>LOST</code> lost</li>
</ul>
<p class="text-secondary">You can change the accounts to match the company's chart of accounts. This affects new entries only; entries already posted do not change</p>
