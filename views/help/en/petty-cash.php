<?php /** How to use the Petty cash page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p><b>Petty cash</b> is cash that staff hold to pay small expenses. Everyone with rights to this page has their own "cash box" and <b>can only make entries in their own box</b> (not even an administrator can do it for them). There is no limit; withdraw as much as you like — the money taken in is the holder's responsibility and must be counted and reconciled at every month end. Every entry has a number (PC), can have files attached and can be traced back. Anyone with the view right can see every box</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Bank accounts</b> — the holder withdraws the money themselves or takes it over from another holder; <b>Cash receipts / Customer deposits</b> received in cash (the CASH account)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Expenses post to the ledger right away; full tax invoices (ใบกำกับภาษีเต็มรูป) go to the <b>Input VAT report</b> and the <b>VAT return (PP 30)</b> (ภ.พ.30); withholding tax issues a <b>withholding tax certificate, 50 Tawi</b> (หนังสือรับรองการหักภาษี ณ ที่จ่าย) (same number series as vendor payments) that goes to the <b>Withholding tax returns (PND 3 / 53)</b> (ภ.ง.ด.3/53); bank withdrawals and returns show in <b>Bank reconciliation</b>; the total shows on <b>Home</b> — <b>Close period</b> works only when every box has counted that month</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Petty cash payment voucher, Petty cash withdrawal / Petty cash return, Petty cash hand-over, Petty cash register with the cash count sheet</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>
        Withdraw from bank: Dr Petty cash / Cr Bank —
        Return to bank: Dr Bank / Cr Petty cash —
        Receive from another holder: <b>no posting</b> (it is still petty cash; it only moves within the register) —
        Receive money (income): Dr Petty cash / Cr the chosen income account (normally Other income) —
        Pay an expense: Dr the expense of each line + Dr Input VAT / Cr Withholding tax payable / Cr Petty cash (net) —
        Short or over on count: Dr/Cr Cash over and short / Petty cash at the month end
    </td></tr>
</table>

<h3>How to use it</h3>
<ol>
    <li><b>Withdraw from bank</b>: pick the bank account and enter the amount — the money goes into your box right away</li>
    <li><b>Receive from another holder</b>: the receiver records it, picking the sender and the amount (no more than the sender has). The system records 2 linked entries with different numbers: the sender's "Handed to…" and the receiver's "Received from…" — if it was recorded wrongly, the sender sees it when counting, and then the sender records receiving the money back</li>
    <li><b>Pay an expense</b>: the payee (or pick a vendor), then lines with <b>Description</b> (required), amount <b>before VAT</b>, VAT and cost center — no need to pick an account; it posts to miscellaneous expense as set in Accounting settings (the accounting team can reclassify it later)
        <ul>
            <li><b>VAT</b>: picked on each line (None / 7%) — if any line has VAT you must <b>pick a vendor</b> and enter the number and date of the full tax invoice (the tax ID and branch come from the vendor register). Input VAT is split out and can be claimed. For a cash bill or an abbreviated tax invoice (ใบกำกับอย่างย่อ) pick None</li>
            <li><b>Withholding</b>: pick the code on the line (base = amount before VAT). You must pick a vendor (the name, address and tax ID on the 50 Tawi come from the vendor register) and the PND 3 / 53 form — you get a 50 Tawi in the same number series as vendor payments, and the cash paid is the net after withholding</li>
        </ul>
    </li>
    <li><b>Receive money (income)</b>: money coming into the box from outside, e.g. selling scrap, change given back — enter who it came from, the income account (normally Other income), the amount and the receipt number</li>
    <li><b>Return to bank</b>: deposit the money left back into the bank</li>
    <li><b>Paying or receiving cash from other documents</b>: in <b>Vendor payments</b>, <b>Cash receipts</b> and <b>Customer deposits</b> pick the account <b>CASH Petty cash (of the person making the entry)</b> instead of a bank — the money leaves or enters the box of the person who makes the document (you cannot pay more than is in the box). It shows in the register as <b>Paid by a document / Received by a document</b> with the document number; change or cancel it on that document (cancelling the document removes the box entry too, if that month is not yet counted)</li>
    <li>Only someone holding cash can make entries: Pay an expense, Receive money (income) and Return to bank show only when there is money in the box — someone with no money yet starts with <b>Withdraw from bank</b> or <b>Receive from another holder</b></li>
    <li><b>Attach file</b>: a picture of the receipt or slip (shrunk automatically) or a PDF, attached when saving, or later in the Attachments section of the entry view, or by pressing the paper clip at the end of a row in the table (a green clip with a number = the number of files) — only the holder can attach or remove files, until the month is counted; others can open them</li>
    <li><b>Receipt no.</b>: entered when saving — <b>every expense of the month needs a receipt number, a tax invoice or an attached file</b>; otherwise the cash cannot be counted (the system lists the numbers that are missing one)</li>
    <li><b>Month-end count</b> (from the last day of the month): enter the number of notes and coins. The system adds them up and compares with the register balance; the difference posts to cash over and short. That month is locked and the counted amount carries forward to the next month</li>
</ol>

<h3>Month rules</h3>
<ul>
    <li>Each box can only make entries in its <b>Working month</b> (the month after the last count) — <b>until it is counted, no entries can go into the next month</b>, and no entries can go back into a counted month</li>
    <li>A box with no money and no entries does not need counting</li>
    <li><b>Receive from another holder cannot be changed or cancelled</b> (it changes two people's boxes at once) — if it is wrong, the sender records <b>Receive from another holder</b> to take it back from the receiver; files can be attached</li>
    <li><b>Edit / Cancel</b> works only on entries you made yourself, and only in a month not yet counted — an edit keeps the same number (and the same 50 Tawi number); the system reverses the old ledger entry and posts again. Money received that has already been spent cannot be reduced or cancelled</li>
    <li><b>Counted, then remembered a missing entry</b>: press <b>Undo count</b> (only for the latest count, before there are entries in the next month and before the accounting period is closed). The screen goes back to that month; add or fix the entries, then count again — back-dated entries get a number of that month, following the month's last number</li>
    <li>Numbers run by the month of the entry date (PC + year-month + sequence). Cancelled entries stay in the register with their number, so no number goes missing</li>
    <li>The <b>Everyone's petty cash</b> table for the chosen month: each person's brought-forward balance and the total, every entry with its holder and that person's balance, then money in, money out and balance per person and in total — you can view one person at a time. A yellow bar shows who has not yet counted a finished month. The <b>Export Excel</b> button gives the same table</li>
    <li>Boxes not yet counted for a finished month show in the <b>work queue</b> at the top, and the <b>Petty cash register</b> button prints the chosen month's entries with the count sheet for the holder and the checker to sign</li>
</ul>
