# Vendored Frontend Assets for Offline Use

All third-party frontend libraries and web fonts in this directory are bundled to allow PhaiERP
to operate completely offline without any internet connection.

## Bundled Libraries

### 1. Tabler Core
- **Version:** 1.6.1
- **Path:** `public/vendor/tabler/`
- **Files:** `tabler.min.css`, `tabler.min.js`
- **Source:** [Tabler](https://tabler.io/) via jsDelivr CDN (`@tabler/core@1.6.1`)
- **License:** MIT (see `public/vendor/tabler/LICENSE`)

### 2. Tabler Icons Webfont
- **Version:** 3.48.0
- **Path:** `public/vendor/tabler-icons/`
- **Files:** `tabler-icons.min.css`, `fonts/tabler-icons.woff2`, `fonts/tabler-icons.woff`, `fonts/tabler-icons.ttf`
- **Source:** [Tabler Icons](https://tabler.io/icons) via jsDelivr CDN (`@tabler/icons-webfont@3.48.0`)
- **License:** MIT (see `public/vendor/tabler-icons/LICENSE`)

### 3. IBM Plex Sans Thai Font
- **Version:** 5.0.8 (IBM Plex Sans Thai v11)
- **Path:** `public/fonts/ibm-plex-sans-thai/`
- **Weights:** 400 (Regular), 500 (Medium), 600 (SemiBold) in WOFF2 format
- **Files:** `ibm-plex-sans-thai.css` and `.woff2` files for Thai, Latin, Latin-ext, Cyrillic-ext subsets
- **Source:** [IBM Plex](https://github.com/IBM/plex) / Google Fonts via `@fontsource/ibm-plex-sans-thai`
- **License:** SIL Open Font License 1.1 (see `public/fonts/ibm-plex-sans-thai/LICENSE`)

### 4. PDF.js
- **Version:** 4.10.38
- **Path:** `public/vendor/pdfjs/`
- **Files:** `pdf.min.js`, `pdf.worker.min.js` (the `build/pdf.min.mjs` and `build/pdf.worker.min.mjs` of the npm package, renamed to `.js` so every web server sends them as JavaScript)
- **Source:** [PDF.js](https://mozilla.github.io/pdf.js/) via the npm registry (`pdfjs-dist@4.10.38`)
- **License:** Apache License 2.0 (see `public/vendor/pdfjs/LICENSE`)
- **Used by:** e-Document Sign (`js/esign-pdf.js`): shows the pages of an envelope to place and fill its fields

### 5. Handwriting fonts: Charm, Charmonman, Itim, Mali, Sriracha
- **Path:** `public/fonts/handwriting/` (`handwriting.css` and the `.ttf` files, Regular only)
- **Files:** Charm, Mali and Sriracha are the same files as the Phai Sign service's `public/fonts/handwriting/`; Itim and Charmonman from the google/fonts repository (`ofl/itim`, `ofl/charmonman`), 2026-11-14
- **Source:** Google Fonts ([Charm](https://github.com/cadsondemak/charm), [Charmonman](https://github.com/cadsondemak/Charmonman), [Itim](https://github.com/cadsondemak/itim), [Mali](https://github.com/cadsondemak/Mali), [Sriracha](https://github.com/cadsondemak/sriracha), all by Cadson Demak); Thai and Latin
- **License:** SIL Open Font License 1.1 (see `OFL-*.txt` beside them)
- **Used by:** e-Document Sign: a font a field may choose in the envelope editor, drawn on the final PDF
