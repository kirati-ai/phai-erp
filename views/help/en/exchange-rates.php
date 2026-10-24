<?php /** How to use the Exchange rates page (opened from the Help button, top right) */ ?>
<h3>What this page is</h3>
<p>Exchange rates of foreign currencies <b>by date</b>, used to convert purchase orders, quotations, sales orders and invoices in a foreign currency into baht</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The rates the company uses (e.g. the Bank of Thailand reference rates)</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Purchase and sales documents in a foreign currency take the rate for their date to convert into baht</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Exchange rate — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — only used to convert amounts when documents post</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Currency</td><td>Chosen from the <b>Currencies</b> page — no need to enter the base currency (THB); the system always uses a rate of 1</td></tr>
    <tr><td class="text-nowrap">Date</td><td>The date this rate starts to apply</td></tr>
    <tr><td class="text-nowrap">Rate (THB)</td><td>How many baht for 1 unit of that currency, e.g. USD = <code>35.50</code>, JPY = <code>0.2350</code> (not per 100 yen)</td></tr>
</table>

<h3>How the system picks a rate</h3>
<ul>
    <li>It uses the rate of the document date; if that day has none, it uses the latest rate <b>before</b> the document date</li>
    <li>So you do not need a rate for every day — enter one when the rate changes, e.g. at the start of each week, or each time you are about to issue documents</li>
    <li>Reference rates are on the Bank of Thailand website or the website of the company's bank</li>
</ul>

<h3>Add a rate</h3>
<ol>
    <li>Click <b>Add</b>, choose the currency, enter the date and the rate</li>
    <li>Click <b>Save</b> — a currency can have only one rate per day; if you entered a wrong one, open that row and change it</li>
</ol>
<p class="text-secondary">Changing a past rate does not change documents already saved</p>
