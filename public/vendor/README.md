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
