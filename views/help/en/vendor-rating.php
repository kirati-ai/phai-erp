<?php /** How to use the Vendor evaluation page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p><b>Vendor evaluation</b> from what really happened in the date range — how often goods came on time, how much had to be returned, how many days delivery took — to choose the next vendor and to negotiate with existing ones</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Purchase orders</b> (order date, wanted date), <b>Goods receipts</b> (receipt date, value) and <b>Purchase returns</b> (quantity returned)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Helps choose vendors on <b>Purchase requisitions</b> (Preferred vendor) and <b>Purchase orders</b> — for grade C, look for a backup vendor</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Vendor scorecard — part of <b>SRM</b> (Supplier Relationship Management) and the ISO 9001 requirement to evaluate suppliers</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post (report)</td></tr>
</table>

<h3>How it is worked out</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">On time</td><td>Receipt lines whose receipt date ≤ the wanted date of the purchase order line (if none, the purchase order's due date) ÷ all lines received</td></tr>
    <tr><td class="text-nowrap">Returned</td><td>Quantity returned in the period ÷ quantity received</td></tr>
    <tr><td class="text-nowrap">Lead time (days)</td><td>Receipt date − purchase order date, averaged (days)</td></tr>
    <tr><td class="text-nowrap">Score / Grade</td><td>60% on time + 40% no returns — A ≥ 90, B ≥ 75, lower than that C</td></tr>
</table>
