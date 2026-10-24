// Regression: opening Add must restore dropdown defaults after viewing another record.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
global.window = {};
global.document = { querySelector: () => null, addEventListener: () => {} };
vm.runInThisContext(fs.readFileSync(require('path').join(__dirname, '../public/js/phai.js'), 'utf8'));
const select = { name: 'pt_prod_line', tagName: 'SELECT', type: 'select-one', value: 'GEN',
    options: [{ value: 'FG', defaultSelected: true }, { value: 'GEN', defaultSelected: false }, { value: 'RM', defaultSelected: false }] };
const form = { elements: [select], querySelectorAll: () => [] };
window.Phai.form.fill(form, null);
assert.strictEqual(select.value, 'FG');
window.Phai.form.fill(form, { pt_prod_line: 'RM' });
assert.strictEqual(select.value, 'RM');
window.Phai.form.fill(form, null);
assert.strictEqual(select.value, 'FG');
window.Phai.form.fill(form, { pt_prod_line: null });
assert.strictEqual(select.value, '');
select.options.forEach((option) => { option.defaultSelected = false; });
window.Phai.form.fill(form, null);
assert.strictEqual(select.value, 'FG');
console.log('5 dropdown default regression checks passed.');
