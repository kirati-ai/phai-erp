<?php /** Shared look of printed A4 documents (doc/print, accounting/wht-print). */ ?>
<style>
    body { background: #e9edf2; }
    .doc {
        --accent: #12855f; --line: #dfe4ea; --soft: #f2f7f5; --muted: #6b7380;
        width: 210mm; min-height: 297mm; margin: 0 auto 2rem; padding: 15mm 14mm 12mm; background: #fff; color: #000;
        font-family: "IBM Plex Sans Thai", "Leelawadee UI", Tahoma, "Noto Sans Thai", sans-serif;
        font-size: 12.5px; line-height: 1.45; position: relative; box-shadow: 0 4px 24px rgba(15, 23, 42, .12);
        display: flex; flex-direction: column;
    }
    .doc::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, #0b6e4f, #12855f 55%, #066fd1); }
    .doc .r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .doc-head { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 14px; }
    .doc-company { display: flex; gap: 12px; align-items: flex-start; min-width: 0; }
    .doc-logo { max-height: 64px; max-width: 140px; object-fit: contain; }
    .doc-company-name { font-size: 17px; font-weight: 600; margin-bottom: 2px; }
    .doc-company div:not(.doc-company-name) { color: #000; }
    .doc-title-box { text-align: right; flex: 0 0 auto; }
    .doc-title { font-size: 21px; font-weight: 600; color: var(--accent); letter-spacing: .2px; margin-bottom: 4px; }
    .doc-meta { margin-left: auto; border-collapse: collapse; }
    .doc-meta th { font-weight: 400; color: var(--muted); padding: 1px 10px 1px 0; text-align: right; white-space: nowrap; }
    .doc-meta td { text-align: left; white-space: nowrap; padding: 1px 0; }
    .doc-box { border: 1px solid var(--line); border-radius: 6px; padding: 8px 12px; }
    .doc-label { font-size: 10.5px; color: var(--muted); margin-bottom: 1px; }
    .doc-party-name { font-weight: 600; font-size: 13.5px; }
    .doc-lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .doc-lines th { background: var(--soft); color: #000; font-weight: 600; font-size: 11.5px; text-align: left; padding: 7px 8px; border-bottom: 2px solid var(--accent); white-space: nowrap; }
    .doc-lines td { padding: 6px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .doc-lines tbody tr:nth-child(even) td { background: #fafbfc; }
    .doc-lines tr.doc-sub td { padding: 0 8px 3px 18px; border-bottom: 0; background: transparent !important; font-size: 8.5px; color: #333; }
    .doc-lines tr:has(+ tr.doc-sub) td { border-bottom: 0; }
    .doc-lines:has(tr.doc-sub) tbody tr:nth-child(even) td { background: transparent; }
    .doc-lines tr.doc-sub:has(+ tr:not(.doc-sub)) td, .doc-lines tr.doc-sub:last-child td { border-bottom: 1px solid var(--line); padding-bottom: 6px; }
    .doc-summary { display: grid; grid-template-columns: 1fr 76mm; gap: 14px; margin-top: 12px; align-items: start; }
    .doc-words-text { background: var(--soft); border-radius: 6px; padding: 8px 12px; font-weight: 500; }
    .doc-totals { width: 100%; border-collapse: collapse; }
    .doc-totals th { font-weight: 400; color: #000; text-align: left; padding: 5px 10px; white-space: nowrap; }
    .doc-totals td { text-align: right; padding: 5px 10px; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .doc-totals tr + tr th, .doc-totals tr + tr td { border-top: 1px solid var(--line); }
    /* the bottom line of every document (net to pay / grand total): bigger than the 9px all table cells get */
    .doc-totals tr.grand th, .doc-totals tr.grand td { background: var(--accent); color: #fff; font-weight: 600; font-size: 15px !important; padding-top: 7px; padding-bottom: 7px; border: 0; }
    .doc-totals tr.bold th, .doc-totals tr.bold td { font-weight: 600; }
    .doc-totals tr.grand th { border-radius: 6px 0 0 6px; }
    .doc-totals tr.grand td { border-radius: 0 6px 6px 0; }
    .doc-notes { margin-top: 12px; }
    .doc-signs { display: grid; gap: 28px; margin-top: auto; padding-top: 44px; text-align: center; }
    .doc-sign-line { border-bottom: 1px solid #8a94a3; height: 40px; margin: 0 10px; }
    .doc-sign-label { margin-top: 5px; font-weight: 500; }
    .doc-sign-date { font-size: 10.5px; color: var(--muted); margin-top: 2px; }
    .doc-foot { display: flex; justify-content: space-between; margin-top: 18px; padding-top: 6px; border-top: 1px solid var(--line); font-size: 10px; color: #000; }

    /* documents (doc/print: .doc-form) — reports keep the look above. Title and number top right, the party beside a
       tinted box of the document's details, an item table that runs down to the totals, and a row of signature boxes. */
    .doc-form { --tint: #e8f4ee; padding-top: 13mm; }
    /* phai.css makes every table cell on screens 9px and not bold; a printed page is read at arm's length */
    .doc-form table td, .doc-form table tbody th { font-size: 12px !important; }
    .doc-form .doc-meta th, .doc-form .doc-totals tr.bold th, .doc-form .doc-totals tr.bold td, .doc-form .doc-totals tr.grand th,
    .doc-form .doc-totals tr.grand td, .doc-form table td strong { font-weight: 600 !important; }
    .doc-form .doc-lines tr.doc-sub td { font-size: 10.5px !important; }
    .doc-form .doc-head { align-items: center; margin-bottom: 12px; }
    .doc-form .doc-title { font-size: 23px; margin-bottom: 0; line-height: 1.25; }
    .doc-form .doc-nbr { font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; }
    .doc-info { display: grid; grid-template-columns: 1fr 72mm; gap: 14px; align-items: stretch; padding-bottom: 12px; border-bottom: 1px solid var(--line); }
    .doc-info-solo { grid-template-columns: 1fr 72mm; }
    .doc-info-solo .doc-meta { grid-column: 2; }
    .doc-party { padding: 2px 0; }
    .doc-party .doc-label { font-size: 11px; font-weight: 600; color: var(--accent); }
    .doc-party-name { font-size: 14px; }
    .doc-form .doc-meta { margin: 0; width: 100%; background: var(--tint); border-radius: 8px; border-collapse: separate; padding: 8px 12px; align-self: start; }
    .doc-form .doc-meta th { text-align: left; color: #000; font-weight: 600; padding: 2px 10px 2px 0; width: 1%; }
    .doc-form .doc-meta td { white-space: normal; }
    /* the item table runs down to the totals (its last row, .doc-fill, takes the room left on the page) */
    .doc-table { flex: 1 1 auto; display: flex; flex-direction: column; margin-top: 12px; min-height: 40mm; }
    .doc-form .doc-lines { margin-top: 0; flex: 1 1 auto; height: 100%; }
    .doc-form .doc-lines th { background: var(--tint); border-bottom: 0; padding: 8px; font-size: 12px; }
    .doc-form .doc-lines th:first-child { border-radius: 8px 0 0 8px; }
    .doc-form .doc-lines th:last-child { border-radius: 0 8px 8px 0; }
    .doc-form .doc-lines td { font-size: 12px; padding: 7px 8px; height: 1px; }
    .doc-form .doc-lines tbody tr:nth-child(even) td { background: transparent; }
    .doc-form .doc-lines tr.doc-sub td { font-size: 10.5px; color: #555; }
    .doc-form .doc-lines tr.doc-fill td { height: auto; border-bottom: 1px solid var(--line); padding: 0; }
    .doc-form .doc-summary { grid-template-columns: 1fr 80mm; margin-top: 10px; }
    .doc-form .doc-words-text { background: none; padding: 0; font-weight: 600; }
    .doc-form .doc-totals tr.grand th, .doc-form .doc-totals tr.grand td { background: var(--tint); color: #000; font-size: 17px !important; padding-top: 9px; padding-bottom: 9px; }
    .doc-form .doc-totals tr.grand td { color: var(--accent); }
    .doc-form .doc-notes { margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--line); display: grid; grid-template-columns: 22mm 1fr; }
    .doc-form .doc-notes .doc-label { font-size: 12px; font-weight: 600; color: #000; }
    .doc-form .doc-signs { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--line); gap: 10px; }
    .doc-sign { display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 0; position: relative; }
    .doc-form .doc-sign-label { order: 0; margin: 0 0 2px; font-weight: 600; font-size: 11.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    /* a signature is at most 40 × 15 mm: the 600 × 200 px image sign-pad.js makes prints sharp at that size */
    .doc-sign-area { height: 17mm; width: 100%; display: flex; align-items: flex-end; justify-content: center; border-bottom: 1px dotted #8a94a3; }
    .doc-sign-area img { max-height: 15mm; max-width: min(40mm, 100%); object-fit: contain; }
    .doc-sign-name { font-weight: 600; font-size: 11.5px; min-height: 17px; margin-top: 3px; max-width: 100%; overflow-wrap: anywhere; }
    .doc-form .doc-sign-date { margin-top: 0; font-size: 11px; color: #000; }
    .doc-stamp-area { width: 26mm; height: 26mm; display: flex; align-items: center; justify-content: center; }
    .doc-stamp-area img { max-width: 26mm; max-height: 26mm; object-fit: contain; }
    .doc-stamp-blank { border: 1px dashed #aab2bd; border-radius: 4px; }


    @page { size: A4; margin: 0; }
    @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .doc { margin: 0; box-shadow: none; min-height: 296mm; }
        .doc::before, .doc-lines th, .doc-lines tbody tr:nth-child(even) td, .doc-words-text, .doc-totals tr.grand th, .doc-totals tr.grand td, .doc-form .doc-meta {
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .doc-lines tr { break-inside: avoid; }
        .doc-lines thead { display: table-header-group; }
    }
</style>
