<?php /** Help: signing envelopes (e-Document Sign) */ ?>
<h3>What this page is</h3>
<p>Send documents from outside (PDF or pictures) to people in the company to <b>sign in order</b>, for example assistant manager → manager → owner. Every signature box shows when it was <b>received, opened and signed</b>. When everyone has signed, everyone gets the same <b>final document</b> with its attachments and the evidence of signing.</p>

<h3>Where it fits</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Comes from</td><td>Menu <b>Signed documents → Envelopes</b>, <b>New envelope</b> (needs the add right on Envelopes). Signers see it under <b>Waiting for me</b> and the envelope button in the top bar.</td></tr>
    <tr><td class="text-nowrap">Goes to</td><td>Once signed, the final PDF goes to everyone in the envelope by email (when mail is set up) and stays under <b>My documents</b>. Check a file on <b>Check a document</b>.</td></tr>
    <tr><td class="text-nowrap">Document</td><td>An electronically signed document with its evidence of signing</td></tr>
    <tr><td class="text-nowrap">Posting</td><td>Nothing is posted</td></tr>
</table>

<h3>Make and send an envelope</h3>
<ol>
    <li><b>Main document</b>: the file with the boxes to sign (PDF or a picture; save a Word file as PDF first). <b>Attachment</b>: supporting files, no boxes. Every file is cleaned before it is kept (JavaScript, links, form fields and anything hidden go). Password-protected PDFs are refused.</li>
    <li><b>Signers</b>: add them one by one: pick the user, type the position, pick the role Sign / Approve / Receive a copy (gets only the final document). The arrows move a signer between steps; people in the same step sign at the same time; a step starts when the one before is fully signed.</li>
    <li><b>Place fields</b>: pick a signer (the coloured dot), pick a field on the right, then click the page. Every signer needs at least one signature or initials field.</li>
    <li>Look at <b>Before sending</b>, then <b>Send</b>. A sent envelope cannot be changed: withdraw it and make a new one.</li>
</ol>

<h3>Editor tools</h3>
<table class="table table-sm">
    <tr><td class="text-nowrap">Select</td><td>Click a field, Shift+click for more, or drag a box around them</td></tr>
    <tr><td class="text-nowrap">Move / size</td><td>Drag it, drag its bottom-right corner, arrow keys move 1 pt (Shift = 10 pt), or type the position in millimetres</td></tr>
    <tr><td class="text-nowrap">Many pages</td><td><b>Copy to</b> all pages / all but the first / odd or even / pages you choose (e.g. 2-5, 8). Tick <b>Linked</b> to change them all at once, and <b>Remove this field from</b> many pages in one go.</td></tr>
    <tr><td class="text-nowrap">Arrange</td><td>With several selected: align edges or centres, space evenly, make the same size</td></tr>
    <tr><td class="text-nowrap">Keys</td><td>Ctrl+C / Ctrl+V copy and paste, Ctrl+D duplicate, Ctrl+Z / Ctrl+Y undo and redo, Delete removes, Esc cancels</td></tr>
    <tr><td class="text-nowrap">Sender's own</td><td><b>Text box</b> (size, bold, italic, underline, colour, alignment) and <b>word stamps</b> such as APPROVED, URGENT, CERTIFIED TRUE COPY</td></tr>
</table>

<h3>Signing</h3>
<ul>
    <li>Open it from <b>Waiting for me</b>; the first opening is the "opened" time. Fill in the fields in your colour; the signature is your saved <b>My signature</b> or one drawn now. Then <b>Sign and send</b> (or <b>Approve and send</b>).</li>
    <li><b>Decline</b> needs a reason; the envelope stops and the sender and those who signed are told.</li>
    <li>On the envelope page the sender can <b>Remind</b>, <b>Withdraw</b>, or <b>Change signer</b> for someone who has not signed.</li>
</ul>

<h3>The final document and checking it</h3>
<ul>
    <li>Every page has a footer: the envelope number, page x / total, which file and page, a content code and a QR code to the check page. The page's own content is shrunk a little so the footer never covers it.</li>
    <li>The evidence pages give every file's page count, fingerprints (SHA-256) of each file and each page, the signers with the times they received, opened and signed, their IP, and the full history kept as a hash chain.</li>
    <li><b>Check a document</b>: pick a file; it says whether it matches what was signed, or which pages differ. The file is not kept.</li>
    <li>All times are the server's clock: let the server set its time from the internet.</li>
    <li>People <b>outside the company</b> cannot be sent to yet (that waits for the phai.monrak.net service).</li>
</ul>
