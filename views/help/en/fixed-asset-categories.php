<?php /** How to use the Fixed asset categories page (opened from the Help button at the top right) */ ?>
<h3>What this page is</h3>
<p>Categories of fixed assets. Each category sets the <b>depreciation method and useful life</b> and the <b>4 accounts</b> used for posting. Assets in the <b>Asset register</b> take their defaults from the category you choose</p>
<p>A new installation already has these (all straight line): <code>BD</code> Buildings 240 months, <code>MC</code> Machinery and equipment 120 months, <code>OF</code> Office equipment 60 months, <code>VH</code> Vehicles 60 months</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The company's accounting policy (method and useful life) and the <b>Chart of accounts</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The <b>Asset register</b> takes its defaults from it · FA-type <b>Product lines</b> link to a category so that received goods go to the asset account</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Fixed asset category — master data</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the category's 4 accounts are used when assets are registered, depreciated and disposed of</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Depreciation method</td><td><b>Straight line</b> = (cost − salvage value) ÷ life in months, the same every month · <b>Declining balance</b> = remaining value × yearly rate ÷ 12 · <b>No depreciation</b>, e.g. land</td></tr>
    <tr><td class="text-nowrap">Useful life (months)</td><td>1–1200 months, e.g. 5 years = 60</td></tr>
    <tr><td class="text-nowrap">Declining rate per year (%)</td><td>Used with the declining balance method (above 0, up to 100)</td></tr>
    <tr><td class="text-nowrap">Asset account</td><td>The cost account, e.g. <code>14301</code> Machinery</td></tr>
    <tr><td class="text-nowrap">Accumulated depreciation account</td><td>e.g. <code>14302</code> — monthly depreciation credits this account</td></tr>
    <tr><td class="text-nowrap">Depreciation expense account</td><td>An expense, e.g. <code>50901</code> — monthly depreciation debits this account</td></tr>
    <tr><td class="text-nowrap">Gain / loss on disposal account</td><td>Used when an asset is sold or written off, e.g. <code>40204</code></td></tr>
</table>
<p>All 4 accounts must be different, active and not control accounts</p>

<h3>Use with FA product lines</h3>
<p>If you buy assets through purchase orders, put the item in an FA-type <b>Product line</b> linked to this category. When the goods are received, the amount goes to the category's asset account; then register the asset with <b>Cost already booked</b></p>
<p class="text-secondary">Changing a category later does not affect assets already registered, because each asset keeps the category's accounts on itself when it is registered</p>
