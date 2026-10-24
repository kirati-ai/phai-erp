/**
 * Excel files without a library: a .xlsx is a zip of XML parts.
 *
 *   const blob = await Phai.xlsx.write({name: 'Parts', title: 'สินค้า', note: '...', columns: [{label, width}], rows: [[cell, ...]]});
 *   Phai.xlsx.save(blob, 'parts.xlsx');
 *   const rows = await Phai.xlsx.read(file);   // first sheet as arrays of values (numbers stay numbers)
 *
 * A cell is a string, a number, null, or {v, t: 'date' | 'num', scale, red} for typed cells.
 */
(function () {
    'use strict';

    // ------------------------------------------------------------ zip

    const crcTable = (() => {
        const t = new Uint32Array(256);
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
            t[n] = c >>> 0;
        }
        return t;
    })();

    function crc32(bytes) {
        let c = 0xFFFFFFFF;
        for (let i = 0; i < bytes.length; i++) c = crcTable[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    async function pipe(bytes, stream) {
        const out = new Response(new Blob([bytes]).stream().pipeThrough(stream));
        return new Uint8Array(await out.arrayBuffer());
    }

    /** files: {path: string} -> zip Blob (deflated when the browser can). */
    async function zip(files) {
        const enc = new TextEncoder();
        const parts = [];
        const central = [];
        let offset = 0;
        for (const [path, text] of Object.entries(files)) {
            const name = enc.encode(path);
            const raw = enc.encode(text);
            const deflate = typeof CompressionStream === 'function';
            const data = deflate ? await pipe(raw, new CompressionStream('deflate-raw')) : raw;
            const crc = crc32(raw);
            const head = new DataView(new ArrayBuffer(30));
            head.setUint32(0, 0x04034b50, true);
            head.setUint16(4, 20, true);
            head.setUint16(6, 0x0800, true); // UTF-8 names
            head.setUint16(8, deflate ? 8 : 0, true);
            head.setUint32(14, crc, true);
            head.setUint32(18, data.length, true);
            head.setUint32(22, raw.length, true);
            head.setUint16(26, name.length, true);
            parts.push(head, name, data);

            const dir = new DataView(new ArrayBuffer(46));
            dir.setUint32(0, 0x02014b50, true);
            dir.setUint16(4, 20, true);
            dir.setUint16(6, 20, true);
            dir.setUint16(8, 0x0800, true);
            dir.setUint16(10, deflate ? 8 : 0, true);
            dir.setUint32(16, crc, true);
            dir.setUint32(20, data.length, true);
            dir.setUint32(24, raw.length, true);
            dir.setUint16(28, name.length, true);
            dir.setUint32(42, offset, true);
            central.push(dir, name);
            offset += 30 + name.length + data.length;
        }
        const size = central.reduce((n, p) => n + p.byteLength, 0);
        const end = new DataView(new ArrayBuffer(22));
        end.setUint32(0, 0x06054b50, true);
        end.setUint16(8, central.length / 2, true);
        end.setUint16(10, central.length / 2, true);
        end.setUint32(12, size, true);
        end.setUint32(16, offset, true);
        return new Blob([...parts, ...central, end], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    }

    /** zip Blob -> {path: Uint8Array} */
    async function unzip(blob) {
        const buf = new Uint8Array(await blob.arrayBuffer());
        const view = new DataView(buf.buffer);
        let end = -1;
        for (let i = buf.length - 22; i >= Math.max(0, buf.length - 65557); i--) {
            if (view.getUint32(i, true) === 0x06054b50) { end = i; break; }
        }
        if (end < 0) throw new Error('not a zip');
        const count = view.getUint16(end + 10, true);
        let p = view.getUint32(end + 16, true);
        const dec = new TextDecoder();
        const files = {};
        for (let n = 0; n < count; n++) {
            const method = view.getUint16(p + 10, true);
            const size = view.getUint32(p + 20, true);
            const nameLen = view.getUint16(p + 28, true);
            const extraLen = view.getUint16(p + 30, true);
            const commentLen = view.getUint16(p + 32, true);
            const local = view.getUint32(p + 42, true);
            const name = dec.decode(buf.subarray(p + 46, p + 46 + nameLen));
            const start = local + 30 + view.getUint16(local + 26, true) + view.getUint16(local + 28, true);
            const data = buf.subarray(start, start + size);
            files[name] = method === 8 ? await pipe(data, new DecompressionStream('deflate-raw')) : data;
            p += 46 + nameLen + extraLen + commentLen;
        }
        return files;
    }

    // ------------------------------------------------------------ write

    const xml = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '');

    function colName(i) {
        let s = '';
        for (i++; i > 0; i = Math.floor((i - 1) / 26)) s = String.fromCharCode(65 + ((i - 1) % 26)) + s;
        return s;
    }

    // cellXfs: 0 plain, 1 title, 2 note, 3 header, 4 text, 5 int, 6 money, 7 decimal, 8 date, 9 header (required)
    const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        + '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        + '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="#,##0.00##"/></numFmts>'
        + '<fonts count="5">'
        + '<font><sz val="9"/><name val="Tahoma"/></font>'
        + '<font><b/><sz val="12"/><color rgb="FF1E293B"/><name val="Tahoma"/></font>'
        + '<font><sz val="8"/><color rgb="FF64748B"/><name val="Tahoma"/></font>'
        + '<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>'
        + '<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>'
        + '</fonts>'
        + '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        + '<fill><patternFill patternType="solid"><fgColor rgb="FF206BC4"/></patternFill></fill>'
        + '<fill><patternFill patternType="solid"><fgColor rgb="FFD63939"/></patternFill></fill></fills>'
        + '<borders count="2"><border/><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right>'
        + '<top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom></border></borders>'
        + '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        + '<cellXfs count="10">'
        + '<xf/>'
        + '<xf fontId="1" applyFont="1"/>'
        + '<xf fontId="2" applyFont="1"/>'
        + '<xf fontId="3" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        + '<xf borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
        + '<xf numFmtId="3" borderId="1" applyNumberFormat="1" applyBorder="1"/>'
        + '<xf numFmtId="4" borderId="1" applyNumberFormat="1" applyBorder="1"/>'
        + '<xf numFmtId="165" borderId="1" applyNumberFormat="1" applyBorder="1"/>'
        + '<xf numFmtId="164" borderId="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
        + '<xf fontId="4" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        + '</cellXfs></styleSheet>';

    /** 'YYYY-MM-DD' -> Excel serial day. */
    function serial(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? Date.UTC(+m[1], +m[2] - 1, +m[3]) / 86400000 + 25569 : null;
    }

    function cellXml(ref, c, header) {
        if (header) return `<c r="${ref}" s="${c && c.red ? 9 : 3}" t="inlineStr"><is><t>${xml(c && typeof c === 'object' ? c.v : c ?? '')}</t></is></c>`;
        if (c === null || c === undefined || c === '') return `<c r="${ref}" s="4"/>`;
        if (typeof c === 'object' && c.t === 'date') {
            const d = serial(c.v);
            return d === null ? `<c r="${ref}" s="4"/>` : `<c r="${ref}" s="8"><v>${d}</v></c>`;
        }
        if (typeof c === 'object' && c.t === 'num') {
            const n = Number(c.v);
            if (!Number.isFinite(n)) return `<c r="${ref}" s="4"/>`;
            return `<c r="${ref}" s="${c.scale === 0 ? 5 : c.scale === 2 ? 6 : 7}"><v>${n}</v></c>`;
        }
        if (typeof c === 'number') return `<c r="${ref}" s="${Number.isInteger(c) ? 5 : 7}"><v>${c}</v></c>`;
        return `<c r="${ref}" s="4" t="inlineStr"><is><t xml:space="preserve">${xml(c)}</t></is></c>`;
    }

    function textWidth(v) {
        // Thai vowel and tone marks take no width
        return String(v ?? '').replace(/[ัิ-ฺ็-๎]/g, '').length;
    }

    /** Row number of the table header: below the title and note, after a blank row. */
    function headRow(s) {
        const top = (s.title ? 1 : 0) + (s.note ? 1 : 0);
        return top ? top + 2 : 1;
    }

    function sheetXml(s) {
        const cols = s.columns;
        const head = headRow(s);
        const last = head + s.rows.length;
        const widths = cols.map((c, i) => {
            let w = c.width || textWidth(c.label) + 4;
            if (!c.width) {
                for (const r of s.rows.slice(0, 500)) {
                    const v = r[i] && typeof r[i] === 'object' ? (r[i].t === 'date' ? '00/00/0000' : Number(r[i].v).toLocaleString('en-US', { minimumFractionDigits: 2 })) : r[i];
                    w = Math.max(w, textWidth(v) + 2);
                }
            }
            return Math.min(60, Math.max(8, w));
        });

        let rows = '';
        let r = 1;
        if (s.title) rows += `<row r="${r}" ht="20" customHeight="1">${cellXml('A' + r, s.title)}</row>`.replace('s="4"', 's="1"'), r++;
        if (s.note) rows += `<row r="${r}">${cellXml('A' + r, s.note)}</row>`.replace('s="4"', 's="2"'), r++;
        rows += `<row r="${head}" ht="18" customHeight="1">${cols.map((c, i) => cellXml(colName(i) + head, c.red ? { v: c.label, red: true } : c.label, true)).join('')}</row>`;
        s.rows.forEach((row, n) => {
            const rr = head + 1 + n;
            rows += `<row r="${rr}">${cols.map((_, i) => cellXml(colName(i) + rr, row[i])).join('')}</row>`;
        });

        const lastCol = colName(Math.max(cols.length, 1) - 1);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            + '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            + '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            + `<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="${head}" topLeftCell="A${head + 1}" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>`
            + '<sheetFormatPr defaultRowHeight="13.5"/>'
            + `<cols>${widths.map((w, i) => `<col min="${i + 1}" max="${i + 1}" width="${w}" customWidth="1"/>`).join('')}</cols>`
            + `<sheetData>${rows}</sheetData>`
            + (s.rows.length ? `<autoFilter ref="A${head}:${lastCol}${last}"/>` : '')
            + '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            + '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            + '</worksheet>';
    }

    function sheetName(name, used) {
        let n = String(name || 'Sheet').replace(/[\[\]:*?/\\]/g, ' ').trim().slice(0, 31) || 'Sheet';
        for (let i = 2; used.has(n); i++) n = n.slice(0, 28) + ' ' + i;
        used.add(n);
        return n;
    }

    /** One sheet object, or a list of them. */
    async function write(sheets) {
        sheets = Array.isArray(sheets) ? sheets : [sheets];
        const used = new Set();
        const names = sheets.map((s) => sheetName(s.name || s.title, used));
        const filters = sheets.map((s, i) => s.rows.length
            ? `<definedName name="_xlnm._FilterDatabase" localSheetId="${i}" hidden="1">'${xml(names[i].replace(/'/g, "''"))}'!$A$${headRow(s)}:$${colName(Math.max(s.columns.length, 1) - 1)}$${headRow(s) + s.rows.length}</definedName>`
            : '').join('');
        const files = {
            '[Content_Types].xml': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                + '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                + '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                + '<Default Extension="xml" ContentType="application/xml"/>'
                + '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                + '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                + sheets.map((_, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')
                + '</Types>',
            '_rels/.rels': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                + '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                + '</Relationships>',
            'xl/workbook.xml': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                + '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
                + names.map((n, i) => `<sheet name="${xml(n)}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')
                + '</sheets>'
                + (filters ? `<definedNames>${filters}</definedNames>` : '')
                + '</workbook>',
            'xl/_rels/workbook.xml.rels': '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                + '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                + sheets.map((_, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')
                + `<Relationship Id="rId${sheets.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>`
                + '</Relationships>',
            'xl/styles.xml': STYLES,
        };
        sheets.forEach((s, i) => { files[`xl/worksheets/sheet${i + 1}.xml`] = sheetXml(s); });
        return zip(files);
    }

    function save(blob, filename) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename.replace(/[\\/:*?"<>|]/g, '_');
        document.body.append(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    // ------------------------------------------------------------ read

    function parse(bytes) {
        return new DOMParser().parseFromString(new TextDecoder().decode(bytes), 'application/xml');
    }

    const tags = (node, name) => [...node.getElementsByTagNameNS('*', name)];
    const textOf = (node) => tags(node, 't').map((t) => t.textContent).join('');

    /** First sheet of a .xlsx file as rows of values: text, number, boolean or null. */
    async function read(file) {
        const files = await unzip(file);
        const strings = files['xl/sharedStrings.xml'] ? tags(parse(files['xl/sharedStrings.xml']), 'si').map(textOf) : [];

        let path = 'xl/worksheets/sheet1.xml';
        if (files['xl/workbook.xml'] && files['xl/_rels/workbook.xml.rels']) {
            const first = tags(parse(files['xl/workbook.xml']), 'sheet')[0];
            const id = first?.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            const rel = tags(parse(files['xl/_rels/workbook.xml.rels']), 'Relationship').find((r) => r.getAttribute('Id') === id);
            if (rel) {
                const target = rel.getAttribute('Target');
                path = target.startsWith('/') ? target.slice(1) : 'xl/' + target;
            }
        }
        if (!files[path]) throw new Error('no sheet');

        const out = [];
        for (const row of tags(parse(files[path]), 'row')) {
            const r = Number(row.getAttribute('r')) - 1;
            const cells = [];
            for (const c of tags(row, 'c')) {
                const ref = /^([A-Z]+)/.exec(c.getAttribute('r') || '')?.[1];
                const i = ref ? [...ref].reduce((n, ch) => n * 26 + ch.charCodeAt(0) - 64, 0) - 1 : cells.length;
                const type = c.getAttribute('t');
                const v = tags(c, 'v')[0]?.textContent;
                let value = null;
                if (type === 's') value = strings[Number(v)] ?? null;
                else if (type === 'inlineStr') value = textOf(c);
                else if (type === 'str' || type === 'e') value = v ?? null;
                else if (type === 'b') value = v === '1';
                else if (v !== undefined && v !== '') value = Number(v);
                if (typeof value === 'string') value = value.trim() === '' ? null : value;
                cells[i] = value;
            }
            out[r >= 0 ? r : out.length] = Array.from(cells, (v) => v ?? null);
        }
        return Array.from(out, (r) => r || []);
    }

    window.Phai = window.Phai || {};
    window.Phai.xlsx = { write, read, save };
})();
