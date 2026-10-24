<?php /** How to use the Product lines page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>A <b>Product line</b> is a big group of items that tells the system what kind of thing an item is and <b>which account codes it posts to</b>. Every item must belong to one product line</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Chart of accounts</b> (inventory, cost of goods sold, sales, purchases accounts) and <b>Fixed asset categories</b> for type FA</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Every item in <b>Items</b> — purchasing, inventory, production and sales documents find their accounts from the item's product line</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Product line — the link between items and the general ledger, split into FG finished goods / RM raw materials / FA fixed assets</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post by itself — it decides which accounts other documents post to</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Code</td><td>e.g. <code>FG</code>, <code>RM</code>, <code>FG-FURNITURE</code>, <code>RM-STEEL</code></td></tr>
    <tr><td class="text-nowrap">Stock type</td><td>
        <b>Finished goods (FG)</b> = things you sell ·
        <b>Raw materials (RM)</b> = things bought to use in production (cannot be sold directly; convert to FG first) ·
        <b>Fixed assets (FA)</b> = machines and equipment bought to use</td></tr>
    <tr><td class="text-nowrap">Fixed asset category</td><td>Required only when the type is FA — goods received post to that category's asset account</td></tr>
    <tr><td class="text-nowrap">Accounts tab</td><td>The inventory, sales revenue, cost of goods sold, purchases and work in process accounts of the items in this line — for any field left blank the system uses the default account in <b>Accounting › Accounting settings</b></td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when no longer used — new items cannot choose this line</td></tr>
</table>

<h3>Add a product line</h3>
<ol>
    <li>If you want separate accounts, go to <b>Accounting › Chart of accounts</b> and add the account codes first</li>
    <li>Click <b>Add</b>, enter the code and name, and choose the <b>Stock type</b></li>
    <li>Open the <b>Accounts</b> tab, choose the accounts you want, then click <b>Save</b></li>
    <li>Go to <b>Items</b> and choose this line in the item's <b>Product line</b> field</li>
</ol>
<p class="text-secondary">A new installation already has the FG and RM lines and an FA line for each asset category — a line that items still use cannot be deleted; changing the accounts only affects entries posted after the change</p>
