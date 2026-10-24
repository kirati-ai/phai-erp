<?php /** How to use the Work orders page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Order production of an item, issue materials on a <b>Material issue slip</b>, return what was not used on a <b>Material return slip</b>, and receive finished goods into stock — labor hours by routing step are booked for you when output is received, and the system compares standard cost with actual cost for you. Everything is on one page, top to bottom in the order of the work</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Material planning (MRP)</b> or made by hand — materials from the <b>Bills of materials</b>, hours from the <b>Routings</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Material issue slip → receive into stock + a <b>Quality control (QC)</b> record → close the work order → goods ready for the <b>Shipment</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Work order (WO) · Material issue / return slip (Material Issue / Return, series MI) · Finished goods receipt (the QC record) — used for production cost accounting</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Issue: Dr Work in process / Cr Raw materials (a return is the reverse, at the cost when issued) · Receive output: standard hours Dr Work in process / Cr Direct labor / Cr Applied manufacturing overhead, then Dr Finished goods inventory / Cr Work in process at standard cost · Closing: the work in process left goes Dr/Cr <b>Production cost variance</b></td></tr>
</table>

<h3>Work waiting</h3>
<p>Above the list are <b>MRP planned production waiting for a work order</b> (click <b>Create work order</b>) and <b>Work orders waiting to be released</b> (click <b>Release</b> on the row) — red rows are past their due date; when nothing is waiting, nothing is shown. The <b>For</b> column says which sales order/customer it is made for (or which item it goes into / safety stock) — the plan comes from the latest <b>Run MRP</b> (the material planning page)</p>

<h3>Status</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Firm</td><td>The header can be edited; materials are recalculated from the bill of materials every time you save — <b>Cancel document</b> = delete this order</td></tr>
    <tr><td class="text-nowrap">Released</td><td>After clicking <b>Release</b>: the quantity can no longer be changed; materials can be issued/returned and output received</td></tr>
    <tr><td class="text-nowrap">Closed</td><td>After clicking <b>Close order</b>: no more issues or receipts; the cost variance has been posted</td></tr>
</table>

<h3>Steps</h3>
<ol>
    <li>Click <b>Add</b>, choose the <b>Item made</b>, enter the <b>Qty ordered</b>, <b>Warehouse</b>, <b>Release</b> date and <b>Due</b> date, then save — the <b>Materials</b> section shows the <b>Required</b> quantity from the bill of materials (scrap allowance included)</li>
    <li>Click <b>Release</b> when ready to produce</li>
    <li><b>Materials</b>: a released order opens with the quantity still to issue on every row, taking the lots that expire first. If one lot is not enough, the system splits it into several rows itself; if you type more than a lot has, the extra moves to the next lot by itself. Add a lot/location with <b>+</b>, remove one with <b>×</b> — edit as needed before clicking <b>Save issue slip</b> to get one material issue slip (it can be printed for the person issuing, the warehouse and the approver to sign)</li>
    <li>Once everything on the bill of materials is issued, the issue fields hide (showing <b>Everything on the bill of materials is issued</b>) — to issue more, click <b>Issue more</b>; a <b>Reason</b> (e.g. scrap during production) is needed every time you issue more than the bill of materials — clicked by mistake? Click <b>Cancel</b> next to the save issue slip button to close it (nothing has been saved yet)</li>
    <li>Materials left over: click <b>Return materials</b>, choose a lot that was issued (the <b>Issued so far</b> column shows how much can be returned), then <b>Save return slip</b> — the goods go back into stock at the cost when issued</li>
    <li><b>Issue / return slips</b>: every slip with the items used (material, lot, location, quantity, value), who made it and the reason — the print icon is at the end of the slip; for a wrong slip click <b>Cancel this slip</b> (the back arrow) at the end of the slip: the goods go back to the same lot and location at the same cost, the issued quantity is reversed and the posting is reversed. The original slip stays, struck through with a <b>Cancelled</b> label — possible until the work order is closed</li>
    <li><b>Receive output</b>: the good field is filled with the quantity still to be made — good + scrap together must not be more than the quantity ordered (materials were issued for that quantity); enter <b>Good</b>, <b>Scrap</b>, location, expiry date (if any) and the <b>Inspector</b>, then click <b>Receive</b> — the system makes the <b>QC record</b> and books labor hours by routing step for you</li>
    <li><b>Labor</b> (read only; nobody has to time anything): every time output is received, the system books the standard hours of every step = run time × (good + scrap) + the first set-up time, at the work center's rate — the table shows per step: h / unit, set-up time, <b>Planned, whole order (h)</b> (visible before production starts = h / unit × quantity ordered + set-up), <b>Booked (h)</b> (comes with each receipt of output), labor cost and overhead booked. If the standard does not match reality, fix it in the item's <b>Routings</b></li>
    <li>When production is complete, click <b>Close order</b> — the confirmation message tells you the cost variance that will be posted</li>
</ol>

<h3>Fields to know</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Warehouse</td><td>The warehouse materials are issued from and goods are received into</td></tr>
    <tr><td class="text-nowrap">Available / Issued so far</td><td>When issuing = the quantity that location/lot had before the issue; when returning = the quantity this order still holds from that lot</td></tr>
    <tr><td class="text-nowrap">Lot of the output</td><td>For lot-controlled items, lot = the work order number, e.g. <code>WO2026100003</code>; no need to fill it in</td></tr>
    <tr><td class="text-nowrap">Scrap (receive output)</td><td>Counted on the work order and the QC record but does not go into stock — the cost of scrap stays in work in process and becomes a variance when the order is closed</td></tr>
    <tr><td class="text-nowrap">Date</td><td>Issues, returns, receipts and labor cannot be dated in the future</td></tr>
</table>

<h3>How cost is worked out (the Cost section)</h3>
<ul>
    <li><b>Standard per unit</b> = materials per unit from the bill of materials × cost when issued + hours by routing step (run time + set-up time averaged over the whole order) × labor rate/hr and (machine + overhead rate)/hr of the work center</li>
    <li>Output is received into stock at the same <b>Standard per unit</b> cost every time — no need to wait until everything is issued</li>
    <li><b>Actual</b> = materials issued (less returns) + standard hours of what was made (scrap included) — labor differs from standard only by the part for scrap</li>
    <li><b>Work in progress</b> = actual − already received into stock — during production it is the cost of the work not yet finished</li>
    <li><b>Variance</b> (shown when production is complete or the order is closed) = actual − standard of the good output received, split into materials (over-issue, scrap, price changes), labor and overhead — when the order is closed it posts to <b>Production cost variance</b> (set in Accounting settings), kept apart from cost of goods sold</li>
</ul>
<p class="text-secondary">The actual labor cost for the whole month (salaries/wages) is posted by accounting in journal entries: Dr Direct labor / Cr Cash/Accrued expenses — the balance left in the direct labor account (which work orders credited at standard) is the month's labor efficiency</p>
<p class="text-secondary">For items without a bill of materials and routing, the system uses a share of work in process instead of standard cost — work orders can also be created from <b>Material planning (MRP)</b></p>
