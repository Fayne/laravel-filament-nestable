const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function editor() {
    const source = fs.readFileSync(path.join(__dirname, '../resources/views/tree.blade.php'), 'utf8');
    const state = vm.runInNewContext(`(${source.match(/x-data="([\s\S]*?)" @nestable-saved/)[1]})`, { Alpine: { mutateDom: (move) => move() }, cancelAnimationFrame: () => {} });
    state.$refs = { root: { parentElement: { getBoundingClientRect: () => ({ top: 0, left: 0 }) } } };
    state.$dispatch = () => {};
    state.depth = () => 1;
    state.height = () => 1;
    return state;
}

function target() {
    const list = { closest: () => null, insertBefore(node, before) { this.inserted = { node, before }; }, closest: () => null };
    const children = { ...list, querySelector: () => null };
    const node = { parentElement: list, nextElementSibling: { id: 'next' }, querySelector: () => children,
        getBoundingClientRect: () => ({ top: 100, bottom: 300, left: 20, width: 400 }) };
    const row = { closest: () => node, getBoundingClientRect: () => ({ top: 100, bottom: 148, height: 48, left: 20, width: 400 }) };
    node.closest = () => node;
    list.parentElement = { getBoundingClientRect: () => ({ left: 0, top: 0 }) };
    return { node, row, list, children };
}

for (const [y, mode] of [[102, 'before'], [123, 'before'], [124, 'after'], [146, 'after']]) {
    test(`previews only the current ${mode} destination without moving any nodes`, () => {
        const state = editor();
        const { node, row, list, children } = target();
        state.dragged = { contains: () => false, classList: { remove() {} } };
        state.previewRow({ clientY: y, clientX: 0 }, row);
        assert.equal(state.indicator.mode, mode);
        assert.equal(state.destination.list, mode === 'child' ? children : list);
        assert.equal(list.inserted, undefined);
        assert.equal(children.inserted, undefined);
        const destination = state.destination;
        state.commitDrop();
        assert.equal(destination.list.inserted.node.contains(node), false);
        assert.equal(destination.list.inserted.before, destination.before);
        assert.equal(state.indicator, null);
        assert.equal(state.dragged, null);
    });
}

test('replaces the previous hint and clears invalid self or descendant destinations', () => {
    const state = editor();
    const { node, row } = target();
    state.dragged = { contains: () => false, classList: { remove() {} } };
    state.previewRow({ clientY: 102 }, row);
    state.previewRow({ clientY: 146 }, row);
    assert.equal(state.indicator.mode, 'after');
    state.dragged = node;
    state.previewRow({ clientY: 124 }, row);
    assert.equal(state.destination, null);
    assert.equal(state.indicator, null);
    state.dragged = { contains: () => true };
    state.previewRow({ clientY: 124 }, row);
    assert.equal(state.destination, null);
});

for (const [left, mode] of [[-20, 'after'], [20, 'after'], [51, 'after'], [52, 'child']]) {
    test(`horizontal position ${left} produces ${mode} only after a full indent`, () => {
        const state = editor();
        const { row } = target();
        state.dragged = { contains: () => false, classList: { remove() {} } };
        state.dragLeft = left;
        state.indent = 32;
        state.previewRow({ clientY: 140, clientX: left }, row);
        assert.equal(state.indicator.mode, mode);
    });
}

test('does not nest above the midpoint even after moving right by a full indent', () => {
    const state = editor();
    const { row } = target();
    state.dragged = { contains: () => false, classList: { remove() {} } };
    state.dragLeft = 80;
    state.previewRow({ clientY: 123 }, row);
    assert.equal(state.indicator.mode, 'before');
});

test('rejects a destination when the dragged subtree would exceed maximum depth', () => {
    const state = editor();
    const { row } = target();
    state.dragged = { contains: () => false, classList: { remove() {} } };
    state.dragLeft = 52;
    state.maxDepth = 2;
    state.height = () => 2;
    state.previewRow({ clientY: 140 }, row);
    assert.equal(state.destination, null);
    assert.equal(state.indicator, null);
});

test('moving left by one indent places the node after the target parent', () => {
    const state = editor();
    const { node, row } = target();
    const root = { insertBefore() {}, closest: () => null };
    const next = { id: 'next-root' };
    const parent = { parentElement: root, nextElementSibling: next,
        querySelector: () => ({ getBoundingClientRect: () => ({ left: -12, top: 40, height: 48 }) }),
        getBoundingClientRect: () => ({ left: -12, top: 40, bottom: 300, width: 432 }) };
    node.parentElement.closest = () => parent;
    state.dragged = { contains: () => false, classList: { remove() {} } };
    state.dragLeft = -12;
    state.previewRow({ clientY: 140 }, row);
    assert.equal(state.destination.list, root);
    assert.equal(state.destination.before, next);
    assert.equal(state.indicator.mode, 'after');
});

test('cancelling a drag removes its floating preview and leaves the tree untouched', () => {
    const state = editor();
    let removed = false;
    let restored = false;
    state.dragged = { classList: { remove: () => { restored = true; } } };
    state.ghost = { remove: () => { removed = true; } };
    state.pending = { id: 1 };
    state.frame = 12;
    state.destination = {};
    state.indicator = {};
    state.resetDrag();
    assert.equal(removed, true);
    assert.equal(restored, true);
    assert.equal(state.pending, null);
    assert.equal(state.indicator, null);
    assert.equal(state.destination, null);
    assert.equal(state.frame, null);
});
