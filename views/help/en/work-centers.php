<?php /** How to use the Work centers page (opened from the Help button at the top right) */ ?>
<h3>What this page is for</h3>
<p>A <b>work center</b> is a place in the factory where work is done, e.g. the cutting section, the welding section, the assembly line. It is picked on the <b>Steps</b> tab of the bill of materials, on machines, on employees and when labor is recorded on work orders</p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Set up from the factory layout</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Steps in <b>Bills of materials</b>, <b>Machines</b>, <b>Production employees</b> and labor recorded on <b>Work orders</b></td></tr>
    <tr><td class="text-nowrap">Document name</td><td>Work center — manufacturing master data that holds the labor, machine and overhead rates per hour</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Does not post by itself — these rates charge labor and overhead into work in process when labor is recorded</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Work center code</td><td>A short code, e.g. <code>CUT</code>, <code>WELD</code>, <code>ASSY</code></td></tr>
    <tr><td class="text-nowrap">Department</td><td>The department in charge; used for searching only</td></tr>
    <tr><td class="text-nowrap">Labor rate/hr</td><td>The average labor cost per hour of the people in this center (baht), e.g. <code>60</code></td></tr>
    <tr><td class="text-nowrap">Machine rate/hr</td><td>The cost of running the machines per hour, e.g. electricity, machine depreciation</td></tr>
    <tr><td class="text-nowrap">Overhead rate/hr</td><td>Other factory costs allocated per hour</td></tr>
    <tr><td class="text-nowrap">Active</td><td>Turn it off when the work center is no longer used</td></tr>
</table>

<h3>Where these rates are used</h3>
<ul>
    <li><b>Bills of materials</b>, Steps tab: cost per unit = time per piece × (labor + machine + overhead)</li>
    <li><b>Work orders</b>, Labor tab: actual labor = hours × the employee's labor rate per hour; if the employee has no rate set, the work center's <b>Labor rate/hr</b> is used. Actual overhead = hours × (Machine rate/hr + Overhead rate/hr) of the work center, also charged into work in process</li>
</ul>

<h3>Steps</h3>
<ol>
    <li>Press <b>Add</b>, enter the code, name and hourly rates, then save</li>
    <li>Go to <b>Bills of materials</b> and pick the work center on the item's Steps tab</li>
</ol>
<p class="text-secondary">A work center used in a step of a bill of materials or by a machine cannot be deleted; deactivate it instead</p>
