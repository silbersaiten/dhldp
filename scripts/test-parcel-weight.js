// Run with: node scripts/test-parcel-weight.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

for (const [file, panelSelector] of [
    ['admin_order.js', '.dhl_order_panel'],
    ['admin_orders.js', '.ordercarrier-panel'],
]) {
    const source = fs.readFileSync(path.join(__dirname, '../views/js', file), 'utf8');
    const match = source.match(/recalculateParcelWeight: function\(element\) \{([\s\S]*?)\n        \},/);
    assert.ok(match, 'Cannot find parcel weight handler');
    const recalculate = vm.runInNewContext('(function($, element) {' + match[1] + '\n})');

    function panel(packWeight, rows) {
        const element = {};
        const output = { value: null, val(value) { this.value = value; } };
        const blocks = rows.map(([amount, weight]) => ({
            find(selector) { return { val: () => selector === '.position_amount' ? amount : weight }; },
        }));
        const wrapper = {
            parents(selector) {
                if (selector === '.export_doc_position') {
                    return { parent: () => ({ find: () => blocks }) };
                }
                assert.equal(selector, panelSelector);
                return { find: () => output };
            },
            closest(selector) {
                assert.equal(selector, '.dhl-export-documents');
                return { attr: () => packWeight };
            },
        };
        return { element, output, $: value => value === element ? wrapper : value };
    }

    const first = panel('0.15', [['2', '0.3'], ['1', '0.4']]);
    recalculate(first.$, first.element);
    assert.equal(first.output.value, 1.15, file + ': packaging added once to all goods');
    recalculate(first.$, first.element);
    assert.equal(first.output.value, 1.15, file + ': repeated edits must not accumulate packaging');
    const second = panel('0.25', [['1', '0.5']]);
    recalculate(second.$, second.element);
    assert.equal(second.output.value, 0.75, file + ': use this order/shop packaging');
    assert.equal(first.output.value, 1.15, file + ': another order must remain unchanged');
    for (const packWeight of ['0', undefined]) {
        const noPackaging = panel(packWeight, [['1', '0.5']]);
        recalculate(noPackaging.$, noPackaging.element);
        assert.equal(noPackaging.output.value, 0.5);
    }
    const empty = panel('0.15', [['0', '1']]);
    recalculate(empty.$, empty.element);
    assert.equal(empty.output.value, 0, file + ': empty goods must not add packaging');
}
console.log('Parcel weight recalculation checks passed.');
