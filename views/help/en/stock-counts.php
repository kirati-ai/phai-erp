<?php /** How to use the Stock counts page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Count the real goods in the warehouse and adjust the system to match, and <b>set up opening stock</b> when you start using the system</p>
<p>When you create a count, the system issues numbered <b>count tags</b> (PI tags), one tag per location + item + lot, from that warehouse's stock. Print the tags and hang them on the stacks; the counter writes the quantity and signs each tag. Collect the tags and enter <b>Counted</b>; the system compares it with the <b>System</b> quantity at the time you enter it, then press <b>Post adjustments</b></p>

<p>Above the list are the <b>Counts not posted yet</b>, with how many tags are counted / the total. Press <b>Open count</b> to carry on counting — red rows are past their count date</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Stock on hand</b> of the chosen warehouse (made into the list to count)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Stock adjustment (CYC-CNT) → <b>Stock movements</b> and the IC journal</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Physical / cycle count — the period-end physical inventory that the auditor comes to observe</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Counted more: Dr Inventory / Cr the reason code's account (none chosen = Inventory adjustment) · counted less: the other way round</td></tr>
</table>

<h3>Counting for the period</h3>
<ol>
    <li><b>What to count</b>: <b>Whole warehouse</b> (the year-end count, the whole warehouse is frozen) or <b>Chosen locations (cycle count)</b> (counting part by part through the year) — tags are issued and stock is frozen only for the chosen locations; the rest of the warehouse can receive and issue as normal. Extra items found must be in the chosen locations</li>
    <li><b>Create the count only when you are ready to count</b> (do not create it ahead of time, because creating it freezes the stock in that warehouse straight away). Press <b>New count</b>, pick the warehouse, the date and a reason (optional), then save — the new count has status <b>Counting</b></li>
    <li>Open the count and press <b>Print tags</b>: you get 8 tags a page. Cut them and hang one on every stack — the tags show no system quantity (a blind count, so counters count the real goods instead of copying a number)</li>
    <li>The counter writes the quantity, signs and notes the time on the tag. For goods found without a tag, use a blank tag and write it by hand</li>
    <li>Collect the tags, put the name in the <b>Counted by</b> box (as written on the tag), then enter <b>Counted</b> by tag number (Enter goes to the next line). The <b>Difference</b> is worked out at once. Press <b>Save counts</b> to keep your work and carry on later — the counter's name is saved only on tags whose number changed</li>
    <li>For goods from blank tags, fill in the bottom row (location, item, lot, quantity, unit cost) and press <b>Add</b> to get the next tag number</li>
    <li>For tags not used (no goods, printed too many), press <i class="ti ti-ban"></i> <b>Void tag</b> — the tag stays in the list, struck through, so the tag numbers are always complete. Press <i class="ti ti-arrow-back-up"></i> to use that tag again</li>
    <li>Turn on <b>Differences only</b>; for tags with a big difference press <i class="ti ti-repeat"></i> <b>Recount</b> and send someone else to count — the system keeps the first count and the counter's name under the box, and waits for the new count</li>
    <li>Press <b>Post adjustments</b> — every tag must be <b>counted or void</b>. If tags are still open, the system lists the missing tag numbers. The system adjusts stock by the differences (type "Count adjustment") and closes the count as <b>Posted</b></li>
    <li><b>Print / PDF</b> is the count summary sheet (system quantity, counted, difference, counter, void tags) for the counters and the checker to sign</li>
</ol>

<h3>Setting up opening stock</h3>
<ol>
    <li>Create a count and choose the reason <code>OPEN</code> (opening balance)</li>
    <li>Add the goods you have, one line at a time in the bottom row: location, item, lot, quantity and <b>Unit cost</b> (blank = the cost on the item page) — for a lot-controlled item with no lot entered, the count number is used as the lot</li>
    <li>Press <b>Post adjustments</b>: the stock comes in at the cost you entered, posted against retained earnings</li>
</ol>

<table class="table table-sm">
    <tr><td class="text-nowrap">Reason</td><td>Sets the account the value of the difference posts to (see <b>Inventory › Reason codes</b>); none chosen = the Inventory adjustment account</td></tr>
    <tr><td class="text-nowrap">System</td><td>The quantity in the system when the count was created (freeze) — from creating the count until you press <b>Post adjustments</b> (or delete the count), the goods on every tag are <b>frozen</b>: they cannot be received, shipped, issued or transferred, and the system names the count that holds them — finish counting and post quickly. Goods not on the count (other warehouses, other locations) still move as normal</td></tr>
    <tr><td class="text-nowrap">Unit cost</td><td>Used for lines added by hand and for counts higher than the system quantity</td></tr>
</table>
<p class="text-secondary">A posted count cannot be undone; if it was wrong, make a new count — a count still Counting can be deleted with the <b>Delete</b> button without touching stock</p>

<h3>Attached files</h3>
<p>The <b>Attachments</b> section under the tag table: press <b>Attach</b> to add pictures or PDFs, several files if you like, such as the signed count sheets / tags — click a file to open it, press × to remove it — press the paper clip at the end of a row in the list to open that row's attachments straight away without opening the document (a green clip with a number = how many files are attached)</p>
<p class="text-secondary">Attaching needs the add or edit right on this page; removing needs the edit or delete right. You can attach in any status. Deleting a count deletes its attached files too</p>
