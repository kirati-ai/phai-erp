# PhaiERP UX/UI contract

Use this document for subsequent UI work. Keep shared behavior in the common shell, grid, datepicker and print templates. User instructions take precedence.

## Application shell
- Toggle sits at the left of the navbar, before the page title.
- Desktop: toggle switches expanded sidebar and icon-only sidebar. Hover never expands it. Clicking a collapsed group icon expands the sidebar and opens that group.
- Sidebar folders behave as an accordion: opening one closes every other folder and updates aria-expanded. Clicking the open folder closes it. Apply on desktop, mobile and expansion from icon-only mode.
- The ตั้งค่า (x.10) heading of each group folds, closed by default; opened folds are remembered in localStorage, and a fold opens when it holds the current page or a menu-search match.
- Clip sidebar brand content inside its header so invisible text cannot cover navbar controls.
- Mobile: navbar remains above the backdrop; sidebar begins below the navbar. Toggle, backdrop and Escape close it.
- Sidebar divider resizes desktop width, bounded to 180–480 px and 40% of viewport; remember width locally.
- No bottom collapse button. Clock uses Asia/Bangkok: dd-MM-yyyy HH:mm:ss expanded; HH:mm collapsed.
- Hide Ask AI navigation unless AI is configured and enabled and the user has permission.

## Shared tables
- Search icon is vertically centered. Search all public returned fields before pagination, including optional columns.
- Column chooser is an icon at the right of the table header. Outside click closes it.
- Public schema fields are optional; preserve initial columns. Never expose hidden credentials/state.
- Column visibility is stored in grid_layout by source/table and shared between users with access.
- Footer background matches header. Page sizes: 10, 20, 50, 100, 300, 500; initial size 20.
- Hover and keyboard row navigation must be consistent. Click and the eye icon open view (plain values; Edit on it turns them into inputs); the pencil icon opens the same dialog / document already in edit mode (inputs, Save, Cancel) — document lists open the page with ?edit=1; edit requires permission and an editable document status.
- Keep aggregate reports and editable line tables semantically correct; do not inject unrelated columns into totals or editors.

## Forms, dates and documents
- Loading (in `Phai.api`, every page): a 2 px bar at the top while a request is out (after 0.2 s); a spinner box over the page content (`.phai-loader`, not over sidebar / top bar) while the page loads its data and while anything is saved — later look-ups (search, pick lists) show only the bar; the button just pressed shows a spinner while its save/send runs (pages calling `Phai.busy` keep theirs); empty tables in `.phai-body` say "กำลังโหลด…" until data comes or requests settle.
- Compact controls: vertical padding 2 px. Content (`.phai-body`) text, buttons and labels 10 px; every input box (page, modal, pick search) 9 px; table header 9 px; table rows 9 px; a card heading inside a page 12 px (weight 500); the shell (sidebar, top bar) keeps its own size.
- Phones: short fields (codes, dates, numbers, status, col ≤ 3) sit two a row (`col-6`); only long text (names, item with description, addresses, remarks) takes the full width (`col-12`). Applies to forms, document heads and view pages (`crud/field`, `doc/edit` do it from `col`).
- View mode shows plain values, no input boxes; provide Back and permitted Edit actions.
- **Next step done = locked**: once the following document in the chain exists (receipt on a PO, shipment on an SO, payable on a landed cost, voucher/payment on a payable, bank reconciliation on a payment or receipt, ...), the document can no longer be edited or cancelled. The server refuses it (409/422) and the page hides Edit/Cancel; to change it, cancel the later document first.
- Edit mode always has **Cancel** (`btn btn-sm`, `ti-x`, "ยกเลิก") just before Save: it drops the changes and shows the saved record in view mode again (pages, modals, every module).
- **Every save (submit) button has ยกเลิก beside it** — `Phai.cancelButtons()` (phai.js, runs on load) adds it where the page has none (`[data-cancel]`, an `*-undo` button or a modal dismiss count as one): in a modal it closes it; else it calls `form.phaiCancel()` when the page sets one (e.g. a section of the work order clears what was typed); else it reloads the page (what was typed is dropped). It shows and hides with its save button. A page's own cancel button carries `data-cancel`; forms with nothing to go back to (login, install, AI chat) carry `data-no-cancel`; forms built later call `Phai.cancelButtons(root)`.
- Detail views and Thai datepicker use normal font weight. Datepicker has Thai month/day labels and Buddhist year display; ISO Gregorian dates remain the stored values.
- Saved business documents support print/PDF subject to print permission. Use shared document templates and embed the stored company logo as a data URL. Do not fabricate a missing logo.
- These code changes ship on new installs. Database layout preferences and uploaded logos require their database/storage data to be retained or restored.

## View pages (one record, read-only)
Every view looks like a saved purchase order (`doc/edit` in view mode). A new view copies this layout; do not invent another.
- Document lists (`doc/list`) show one month at a time: ‹ month › beside the search, this month first, an empty month shows all (`config.month` = the date column, default the first `date` column; `false` turns it off). The API gets `month` + `month_key` and `Db::page()` filters.
- A queue row opens its document in a window to read only (the page with `?embed=1`: no menu, top bar or buttons).
- Toolbar (`.phai-toolbar`): Back (`btn btn-sm`, arrow-left) on the left, then the status badge (`badge bg-<color>-lt`). Actions on the right in this order: workflow actions as ghost buttons (`btn-ghost-primary`, `btn-ghost-secondary`, `btn-ghost-danger` for cancel/void), Print (`btn btn-sm`), then Edit last as the only `btn-primary`. Show only what the user may do in this status; no disabled clutter.
- Head card: `<div class="card phai-view-mode"><div class="card-body pb-3"><div class="row g-2">`, each field a `col-md-*` with `<label class="form-label">` over `<div class="phai-view-value">` (plain text, thin top rule, normal weight, no input boxes). Empty value: `–` with `is-empty`. Numbers and money: `text-end`, tabular figures, money 2 decimals, quantity with its unit. Dates `dd/mm/yyyy`. A code is shown with its name: `CODE · Name`. Status is the badge, not a field. Created/updated (who · when) go last.
- Below the head: the record's lines or history in their own card (shared grid, same columns everywhere the same data shows). When a view has both details and history, use compact tabs (`nav nav-tabs phai-tabs`) outside the cards: Details first, History second; tab panes hold plain cards without a header. Never put tabs in a card header (`card-header-tabs`): the grid column chooser sits at the top of the card and covers them.
- Actions that need input open a small modal (date, quantity, destination…) with Cancel / Save; errors under the field; success toast, then reload or open the resulting record.
- Master data and simple records (`Resource`, e.g. QC) view in their modal with the same plain-value fields; a status-like column is declared in `Resource::$badge` and shows as a badge beside the modal title instead of a field.
- Building blocks: `views/doc/view-fields.php` renders the head card from `[[label, value, col, numeric?]]`; `public/js/view.js` runs toolbar actions written as `<button data-post data-body data-confirm data-go>`; a page with its own Back/Print toolbar passes `'ownToolbar' => true` so the layout's generic document bar is not added. JS-driven document screens call `Phai.viewFields(form, true)` once saved.
- Converted: every `doc/edit` document (purchase requisition, purchase order, quotation, sales order, invoices — print as an action, `ownToolbar`), receipt, shipment, billing note, work order, stock count, payable, AP voucher, cash receipt / payment, journal, fixed asset, stock line. landed costs (`/landed-costs/{rc}`).
- Attached files: one box for every document (`views/partials/files.php` → `Phai.files`), a card under the saved record (below its lines and totals; in a `Resource` modal at the foot of the body), header เอกสารแนบ + count + แนบไฟล์ on the right, pictures as thumbnails, a PDF as its icon, × to remove; with none it says what to attach (`lang/*/files.php`). Every table of such records has a paperclip as the first row icon (`Phai.grid` `files`, or `Phai.files.button` + `mark` in a hand-made table: bank-rec and PP.30 history, petty cash) that opens the row's files in a dialog without opening the record; green with the number of files when there are any. A new, unsaved record shows no box.
- Example besides documents: stock line view `/stock/view?id=<ld_id>` (`views/inventory/view.php`).
- A list reached from another module's menu (e.g. Purchasing › raw material stock, `/stock?type=RM`) opens a record in a read-only modal with the same fields and tabs, so the user never jumps to another module's page and menu. Only the owning module's menu opens the full view page.

## Production
- Finished goods /parts and raw materials /materials share pt_mstr and the same stock ledger. Product Line pl_mstr.pl_inventory_type is the sole FG/RM classification, linked through pt_mstr.pt_prod_line. Do not add an independent item classification. Older pt_inventory_type columns are retained but hidden and unused. Unclassified legacy lines remain visible in /parts until explicitly classified; inactive lines never hide existing items.
- Planning: pt_min_ord is minimum order quantity, pt_max_ord maximum per lot (0 unlimited), pt_mult_ord ordering multiple. pt_pack_qty describes packaging; pt_max_qty is storage ceiling, not order ceiling.
- /item-planning exposes existing pt_site_mstr fields: Safety Stock pt_safe_stk, Reorder Point pt_rop, Reorder Quantity pt_roq, purchase/production lead times, buyer, sourcing and default locations. Do not duplicate these into pt_mstr.
- Current MRP pools warehouses. It reserves the sum of active-site Safety Stock and sizes net requirements with minimum/multiple/maximum lots; conversion splits PR lines and work orders. Reorder Point/Quantity and site-specific lead times are recorded but are not yet used by this global MRP. Do not claim site-specific planning support.
- Purchased materials: source P (ซื้อมา). Manufactured items: source M (ผลิตเอง). Item group/type classify raw materials and finished goods; warehouse/location describe physical storage.
- Purchase order → purchase receipt increases stock in the selected warehouse/location. Opening stock may use authorized inventory receipt/adjustment.
- BOM /boms is per manufactured parent item. Existing formulas are listed on the left; Create BOM focuses the parent selector; Open loads existing formula or a blank formula for an existing item. Add components, usage per 1 parent unit, scrap allowance, effective dates, then save.
- Provide an Items/materials shortcut in the formula editor. Routing uses the same parent/editor interaction.
- BOM save checks add permission for a new formula, edit permission for an existing one; validate duplicates, cycles, positive quantity and effective dates.
- Work order creation snapshots effective one-level BOM requirements at release date. BOM changes do not automatically rewrite already released orders.
- Release work order → issue materials (stock decreases) → record labor → receive finished goods (stock increases) → close and settle remaining WIP variance.
- No physical purchase/issue/posting should be performed merely to test UI on the installed business database.

## Verification limits
Production review checks code and reads installed data. Full posting/costing/concurrency tests must run on a dedicated test database; tests/e2e/run.php recreates its test database and must never target the active installation.

## Product Line setup
Create Product Lines in ข้อมูลหลัก → สายผลิตภัณฑ์ (/product-lines). Set code/name, stock classification FG or RM, tax and account mappings in the Accounts tab. Codes can be FG/RM or any business-specific code; classification is a property of each line. Assign pt_prod_line in each item/material; no duplicate type selector. New items require an active classified line. Each item screen defaults to an active line of its type, but users may select another line to move the record between lists. Editing a line's classification affects all items linked to it and does not rewrite historical GL entries. Existing GEN is unclassified, so split mixed items by assigning separate classified lines instead of classifying GEN wholesale. Do not change installed item assignments or account mappings automatically. Installs seed FG/RM.

## Language
- Thai (main) and English (`Lang::LOCALES`). The flag of the other language sits beside ช่วยเหลือ in the top bar (a US flag on Thai screens, a Thai flag on English ones, drawn in SVG with its code: EN / TH); a click keeps it with the user and reloads. The login page has the same flag beside the light/dark switch.
- Every screen word comes from `lang/<locale>/*.php`; names stored in Thai (menus, programs) go through `Lang::name()`. Printed documents and PDFs stay Thai in both languages; data (names, descriptions) shows as entered.

## Themes
- Six themes from the dots in the user menu (`AuthController::THEMES`): ไผ่ green (default), หมอก white and grey, คราม blue, อัญชัน purple, ดินเผา terracotta — light, `<html data-phai-theme>` — and ไผ่ยามค่ำ dark (`data-bs-theme=dark`). A colour theme only sets its scale `--p-50` … `--p-950`; the `[data-phai-theme]` block at the end of `phai.css` puts the scale wherever ไผ่ has a green. New CSS uses variables (`--tblr-primary`, `--phai-deep`, `--phai-band`, …); a rule that has to write a green itself gets a `[data-phai-theme]` twin with `var(--p-…)`. Red stays for errors and amber for waiting work in every theme; prints stay light.

## Fixed assets
- Accounting → Fixed Assets (/fixed-assets) and Asset Categories (/fixed-asset-categories). Separate program permissions; add/edit/delete draft, edit also for the financial actions, print PDFs. Transfer requires edit; no hover-triggered actions.
- Configure category accounts (asset, accumulated depreciation, depreciation expense, gain/loss), method SL/DB/NONE, useful life and annual DB rate. Snapshot account mappings when activating an asset; later category edits do not rewrite activated assets.
- Register stores identity, serial, vendor/source document, site/location, cost centre/custodian, dates, cost/residual, method/life/rate and opening depreciation/month. Base detail view is plain values; workflow forms are collapsed until selected. Only unactivated drafts may be edited; only drafts without history may be deleted.
- BOOKED activation imports previously booked cost/opening depreciation without duplicate GL postings; POST activation debits asset cost and credits an explicit non-control account. No automatic AP or VAT posting from activation.
- SL: (cost−salvage)/life months. DB: current NBV × annual rate/12. Full calendar months from in-service month; no daily proration. At end of useful life settle remaining depreciable value to salvage. NONE does not depreciate.
- Depreciation must be contiguous, unique per asset/month, in an open period and no later than today. Monthly batch preview/selection supports 1–500 assets; a failed row rolls back the whole batch. Every lifecycle write locks the asset row.
- Transfer records site, location, cost centre and custodian; it does not change accounts or create GL entries. Sale/write-off removes original cost and accumulated depreciation, records proceeds and gain/loss. Complete depreciation through previous month; no depreciation in disposal month. Use a clearing account with separate sales/VAT/cash documents to avoid duplicate proceeds.
- Corrections reverse only the latest effective lifecycle event, restoring register state and reversing its GL entry in an open period. Keep original history; do not delete postings. FA journal references are FA-transaction-id.
- Current register report, per-asset card/history and monthly depreciation report print as PDFs with stored company logo. Monthly depreciation report excludes reversed rows from totals; GL reversal dates can fall in a different month.
- Tests run in a randomly named isolated database and never change config.php or post to the installed ledger. Fresh installs include the schema/seeds.
- This module is the book register; separate tax books, daily proration, revaluation and automatic purchase-invoice integration are not implemented.

## Help

- No explanatory text on a screen. A screen that needs guidance (where to get values, examples) gets `views/help/<lang>/<name>.php` (falls back to `th`) and passes `'help' => '<name>'` to `View::render`; Resource screens use their page path automatically (e.g. `item-planning`, `settings-inventory`). The layout then shows a small `ti-help-circle` + "ช่วยเหลือ" at the right of the top bar, beside the user menu, that opens an offcanvas on the right. Fields may carry example values as placeholders.

## One flow

- A field, figure or rule added to one document goes everywhere that data flows, without being asked: the documents before and after it in the chain (payable → voucher → payment → 50 ทวิ → PND report; quotation → order → shipment → invoice → billing → receipt), its view/edit screens, its print, its work queue, its API, its posting, its help page, the sample data, the manual and the tests. A figure shown in several places is computed in one place (e.g. `WhtPlan` for withholding) so every page agrees.
- Totals of every document sit in the same summary box, on its own below the lines card (never inside the table card or its footer) below its lines (`table.phai-summary`, grand total row `phai-summary-grand`): payables, vouchers, payments, cash receipts, orders and invoices. Withholding shown as `หัก ณ ที่จ่าย x%` rows then `ยอดจ่ายสุทธิ`; the highlight (`phai-summary-grand`, bold on prints) always goes on the bottom line — the amount actually paid or received — so with withholding it moves from the total to `ยอดจ่ายสุทธิ` (the total stays bold, and the amount in words on prints follows `ยอดจ่ายสุทธิ`); a settled document shows what is still owed (`ยอดค้าง`), never "paid" next to a net figure.
