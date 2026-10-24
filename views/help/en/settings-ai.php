<?php /** How to use the AI settings page (opened from the Help button, top right) */ ?>
<h3>What this page is for</h3>
<p>Turns on the <b>Ask AI</b> menu so users can ask about the data in the system in everyday language, e.g. "purchase orders not yet received", and shows the AI cost of each person</p>
<p>The AI can only read data; it does not create or change anything, and it sees only the data the person asking may view under <b>Roles &amp; permissions</b></p>

<h3>Where it sits in the system</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>The read APIs of the screens and the <b>Roles &amp; permissions</b> of the person asking</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>The <b>Ask AI</b> menu</td></tr>
    <tr><td class="text-nowrap">Document name</td><td>AI assistant — an add-on, not a core ERP module</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>No posting — the AI can only read</td></tr>
</table>

<h3>Fields</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Provider</td><td><b>Claude (Anthropic)</b> or <b>Gemini (Google)</b> — you sign up and pay for the usage with the provider yourself</td></tr>
    <tr><td class="text-nowrap">Model</td><td>Can be left blank; the system uses the model shown faintly in the field. Enter one yourself if you want another model, by its name on the provider's website</td></tr>
    <tr><td class="text-nowrap">API key</td><td>The key you create on the provider's website: Claude at <code>console.anthropic.com</code> → API Keys, Gemini at <code>aistudio.google.com</code> → Get API key — once saved it is not shown again; leave it blank to keep the saved one</td></tr>
    <tr><td class="text-nowrap">Input price / Output price</td><td>The price in US dollars per 1 million tokens of the model you use, from the provider's pricing page — only used to work out the <b>Usage</b> table; it has no effect on what you are actually billed</td></tr>
</table>

<h3>Turn on AI</h3>
<ol>
    <li>Create an API key on the provider's website (setting a monthly spending limit there too is safer)</li>
    <li>Choose the provider, paste the API key and enter the prices</li>
    <li>Turn on the <b>Enable AI</b> switch and click <b>Save</b></li>
    <li>Give the <b>View</b> permission of the "Ask AI" screen to the roles that will use it, in <b>Roles &amp; permissions</b> (ADMIN can use it straight away)</li>
    <li>The <b>Ask AI</b> menu appears under the home page in the left menu; try a question</li>
</ol>

<h3>Usage table</h3>
<p>The number of questions, tokens and estimated cost (US dollars) of each user, for this month and in total — to see who uses it how much</p>
<p class="text-secondary">All these values are kept in the config.php file on the server, not in the database — if a message says the file cannot be written, ask the server administrator to allow writing to config.php</p>
