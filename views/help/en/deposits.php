<?php /** How to use the customer deposits page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Record a <b>deposit</b> a customer pays on a sales order before the goods are delivered. You get a <b>Deposit receipt / tax invoice (ใบรับเงินมัดจำ/ใบกำกับภาษี)</b>, because money received before delivery is a tax point (s.78). The tax invoice issued at shipping then deducts this deposit by itself</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>A <b>Sales order</b> that is still open and in baht — click the <b>Receive deposit</b> button on the sales order</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td><b>Shipment → Invoice / tax invoice</b> of the same sales order: the deposit (before VAT) is deducted from the value of the goods and VAT is charged only on the rest; it can be deducted over several invoices until used up — goes into the <b>Output VAT report</b> and the <b>VAT return (PP 30)</b> (ภ.พ.30) of the month the money came in</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Deposit receipt / tax invoice — Revenue Code s.78 and s.86/4</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Deposit received: Dr Bank / Cr Customer deposits (before VAT) / Cr Output VAT — tax invoice that deducts it: Dr Trade receivables (the rest + VAT), Dr Customer deposits / Cr Sales revenue (full value), Cr Output VAT (the rest)</td></tr>
</table>

<h3>How to use</h3>
<ol>
    <li>Open the sales order and click <b>Receive deposit</b> (or pick from the list of open sales orders on the new page, searching by number, customer or customer PO number). The system shows the order total and the deposits already received</li>
    <li>Pick the bank account and enter <b>Received (incl. VAT)</b>. The system splits it into the value before tax and the VAT at the sales order's rate. Total deposits cannot exceed the sales order total</li>
    <li>Save, then click <b>Print / PDF</b> for the customer</li>
    <li>When the goods ship and the tax invoice is issued, the deposit is deducted by itself (oldest first). Status <b>Not fully deducted</b> → <b>Deducted</b> — cancel the tax invoice and the deposit comes back to be deducted again</li>
    <li><b>Cancel document</b> works only for a deposit that has not been deducted yet. The system reverses the entries dated today</li>
</ol>
<p class="text-secondary">A tax invoice that deducts a deposit cannot produce e-Tax XML yet; use the PDF instead</p>

<p class="text-secondary">Paid/received in <b>cash</b>: pick the account <b>CASH Petty cash (of the person entering it)</b> — the money leaves/enters the petty cash box of the person who makes this document (see the Petty cash page). The document can be cancelled while that month of the box is not yet counted</p>
