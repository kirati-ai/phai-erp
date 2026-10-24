<?php /** How to use the Trial balance page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>The <b>Trial balance</b> for the chosen date range: each account shows opening, debit, credit and closing. Total debits must always equal total credits (if not, the words "Out of balance" appear) — this is also where you close the accounting period with <b>Close period</b></p>
<p><b>Period ended, waiting to be closed</b> appears at the top when the open period has ended (only for users allowed to close periods). Press <b>Close period</b> and confirm</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Every entry in <b>Journal entries</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Close the period, then prepare the financial statements</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Trial balance — a report, not a document</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — closing the period only moves the open period on (the month's depreciation must all be posted first)</td></tr>
</table>

<h3>Reading the figures</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Opening / Closing</td><td>Balance before and after the range — plus = debit side, minus = credit side (liabilities, equity and revenue are normally negative)</td></tr>
    <tr><td class="text-nowrap">Revenue / expenses</td><td>Start from zero every 1 January (fiscal year = calendar year); the profit or loss of earlier years shows inside the <b>Retained earnings account</b></td></tr>
</table>

<h3>See the details</h3>
<ol>
    <li>Choose the date range (default: start of the open period to today)</li>
    <li>Click an account to open its <b>general ledger</b>: every line in the range, with cost center and running balance</li>
    <li>Click a line to open that entry in the journal</li>
</ol>

<h3>Close period</h3>
<p>The <b>Open period</b> shows next to the button. No document can be dated before day 1 of the open period. When the month has ended and everything is checked (bank reconciliation, depreciation, tax reports), press <b>Close period</b> — if any asset still lacks its depreciation for that month, the system will not close, because after closing that month's depreciation could no longer be posted</p>
<ul>
    <li>Close one month at a time, and only a month that has ended</li>
    <li>After closing, nothing can be posted back into that month — if an old document is cancelled later, the system reverses it with a date in the open period</li>
    <li>You need the edit right on the Trial balance page</li>
</ul>
<p class="text-secondary">A closed period cannot be reopened; check everything before you confirm</p>
