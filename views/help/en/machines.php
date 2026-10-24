<?php /** How to use the machines page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>The register of machines in the factory: which work center each one is in, its brand, model and serial number, and its link to an entry in the <b>Asset register</b> so its depreciation can be followed</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td><b>Work centers</b> and the <b>Asset register</b></td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Used as a reference in production, and to follow the machine's depreciation in the asset register</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Machine register (Machine / Equipment Master)</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — depreciation is posted from the <b>Asset register</b></td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Machine code</td><td>A short code, e.g. <code>CNC-01</code>, <code>PRESS-02</code></td></tr>
    <tr><td class="text-nowrap">Work center</td><td>The work center where this machine is based</td></tr>
    <tr><td class="text-nowrap">Fixed asset code</td><td>The asset already registered in accounting, e.g. <code>MC-0001</code> — can be left empty if not registered yet</td></tr>
    <tr><td class="text-nowrap">Brand / Model / Serial number</td><td>Data from the machine's nameplate; searchable</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn off when the machine is sold or no longer used</td></tr>
</table>

<h3>Steps when buying a new machine</h3>
<ol>
    <li>Buy it through a <b>Purchase order</b> as an item in a product line of type FA (e.g. MC machinery), then receive it</li>
    <li>Register it in the <b>Asset register</b> (Accounting › Fixed assets (FA))</li>
    <li>Come back to this page, click <b>Add</b>, pick the work center and the fixed asset code you just registered</li>
</ol>
<p class="text-secondary">This page is a register only. The cost of using a machine comes from the <b>Machine rate/hr</b> on the <b>Work centers</b> page</p>
