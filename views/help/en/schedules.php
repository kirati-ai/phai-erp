<?php /** How to use the Delivery schedule page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>scheduled</b> purchase order or sales order is one long-term order that fixes the item and the price, then delivers in several instalments by date, e.g. order 20,000 nuts at one price, delivered 5,000 a month for four months — it works the same way as <b>Supplier Scheduling</b> (buying side) and <b>Customer Scheduling</b> (selling side) in large ERP systems: the instalments are issued as a <b>Release</b> (schedule release); a new release replaces the old one, and old releases are kept as history</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>A <b>Purchase order</b> / <b>Sales order</b> with Order type = Scheduled (instalments) (click the <b>Delivery schedule</b> button on the order) — on the buying side a release can come from <b>Material planning (MRP)</b>; on the selling side it comes from the schedule the customer sends</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Buying side: print and send to the vendor, then <b>Goods receipts</b> by instalment · Selling side: <b>Shipments</b> by instalment · every instalment of the release in use that has not arrived yet is demand / a scheduled receipt in <b>MRP</b> on the date of the instalment</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Scheduled order / long-term purchase agreement (blanket order) · supplier schedule (delivery schedule) · on the selling side, the delivery schedule the customer sends (customer schedule)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — posting happens at each receipt / shipment, as with a normal order</td></tr>
</table>

<h3>Where to open it</h3>
<p>The menu items <b>Purchasing › Vendor schedules</b> and <b>Sales › Customer schedules</b>: a list of all scheduled orders (not split by month, because an agreement covers many months) with the release in use, the firm date, the source and the <b>Next delivery</b> — click a row to open it, or click the <b>Delivery schedule</b> button on that order</p>

<h3>Releases</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Release no.</td><td>Release date-sequence, e.g. <code>20261011-01</code>. The latest release is the one <b>In use</b>; older releases can be viewed from the box beside the partner's name (read only)</td></tr>
    <tr><td class="text-nowrap">Firm until</td><td>Instalments up to this date are <b>Firm</b> (shipping schedule — deliver as shown); later instalments are <b>Planned</b> (planning schedule — for the partner to prepare materials and capacity, not yet an order to ship). The default is 14 days from the release date</td></tr>
    <tr><td class="text-nowrap">Source</td><td>From the order (the first release, made by the system when the order is saved: one instalment for the whole quantity; the first real release replaces it) · Set by us · From MRP (buying side) · Sent by the customer (selling side)</td></tr>
    <tr><td class="text-nowrap">Previous release</td><td>The quantity for the same date in the previous release — orange = changed, <b>new</b> = not in the previous release, a struck-through row marked <b>dropped</b> = in the previous release but not in this one</td></tr>
    <tr><td class="text-nowrap">Received / Shipped</td><td>Goods already received or shipped count against the earliest instalment first; an instalment that is already fulfilled cannot be changed</td></tr>
    <tr><td class="text-nowrap">All instalments</td><td>For the release in use = the ordered quantity of that line on the order; the amount and tax of the order are recalculated by themselves — it cannot go below what is already received / shipped</td></tr>
</table>

<h3>How to use</h3>
<ol>
    <li>Create a purchase order or sales order, choose <b>Order type = Scheduled (instalments)</b>, enter the total quantity and the price, save, then click <b>Delivery schedule</b></li>
    <li>Click <b>Edit (new release)</b> — an issued release is not overwritten (it has already gone to the partner); a change means a new release: enter the release date, firm until, source, reference (selling side = the customer's release number) and split into instalments (<b>Add instalment</b>) — while editing you see the type of each instalment and the comparison with the release in use — then <b>Save release</b> (it asks you to confirm first, because an issued release cannot be deleted; Enter moves to the next field and does not save) — the <b>Total</b> row at the bottom shows all instalments, split into firm / planned, compared with the previous release, received / shipped, and open</li>
    <li>Buying side: click <b>New release from MRP</b> and the system drafts a new release — instalments up to the firm date and instalments already received stay as they are (the vendor is already making them); later instalments get the quantities and dates on which MRP works out the goods are needed (calculated without this order's later instalments). You can change them before saving</li>
    <li>Click <b>Print / PDF</b> to send that release to the vendor (buying side) or to confirm it with the customer (selling side)</li>
    <li>Receive / ship as usual from the order. When all instalments are done, the order <b>stays open</b>, and new releases can add instalments for as long as the agreement lasts — when the agreement ends, click <b>Close PO / Close order</b> on the order</li>
</ol>

<h3>How the buying and selling sides differ</h3>
<ul>
    <li><b>Buying side (Supplier Scheduling)</b> — we issue the releases: set by us, or from MRP (large systems issue releases from MRP regularly, e.g. every week, and send them to the vendor by EDI or email) — MRP action messages (reschedule in, reschedule out, cancel) pointing at this order are the signal to issue a new release</li>
    <li><b>Selling side (Customer Scheduling)</b> — usually the customer issues the releases. Large customers (e.g. car assembly plants) send them by <b>EDI</b> straight into the system. Here you type them in from the schedule the customer sends (Source = Sent by the customer), or set them yourself as agreed (Source = Set by us)</li>
    <li><b>EDI</b> (Electronic Data Interchange) is sending business documents between the systems of two companies in a standard format, with nobody typing, e.g. the planning schedule (ANSI X12 830 / EDIFACT DELFOR), the shipping schedule (862 / DELJIT) and the advance ship notice (856 / DESADV) — the file format and channel must be agreed with each customer</li>
</ul>
