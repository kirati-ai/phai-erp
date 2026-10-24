<?php /** How to use the Purchasing settings page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>The purchasing rules for the whole company. There are two; set them once and they apply to every document</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set once when you start using the system</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Applies to every document in <b>Purchase orders</b> and <b>Goods receipts</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Purchasing settings (table <code>po_ctrl</code>) — not a document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — these are system rules</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Purchase orders only from requisitions</td><td>On = the <b>Purchase orders</b> page has no Add button; open a requisition first, then press <b>Make PO</b> on the requisition. Suits companies that want every purchase to have a requester · Off = purchase orders can be created directly</td></tr>
    <tr><td class="text-nowrap">Over-receipt tolerance (%)</td><td>How many percent over the PO quantity can be received <b>without a warning</b>, e.g. enter <code>5</code>: order 100, receive 105, no warning — receiving more than that still saves, but the warning "More than ordered" shows under the field and after saving · enter <code>0</code> = warn every time more than ordered is received</td></tr>
</table>

<h3>Setting it up</h3>
<ol>
    <li>Tick or untick "Purchase orders only from requisitions"</li>
    <li>Enter the over-receipt percentage, then press <b>Save</b> — it applies from the next receipt</li>
</ol>
<h3>Document numbers</h3>
<p>The table at the bottom of the page sets the prefix, format and next number of this module's documents (purchase requisition, purchase order, goods receipt, purchase return); then press the table's <b>Save</b> — it applies to new documents only. See the format details in System settings → <b>Document numbering</b>, which has every module in one place</p>
