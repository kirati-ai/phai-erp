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


    @page { size: A4; margin: 0; }
    @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .doc { margin: 0; box-shadow: none; min-height: 296mm; }
        .doc::before, .doc-lines th, .doc-lines tbody tr:nth-child(even) td, .doc-words-text, .doc-totals tr.grand th, .doc-totals tr.grand td {
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .doc-lines tr { break-inside: avoid; }
        .doc-lines thead { display: table-header-group; }
    }
</style>
