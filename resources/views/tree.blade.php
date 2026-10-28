<x-filament-panels::page>
    <style>
        .nestable-editor { --nestable-border: #e5e7eb; --nestable-bg: #fff; --nestable-muted: #6b7280; }
        .dark .nestable-editor { --nestable-border: #374151; --nestable-bg: #111827; --nestable-muted: #9ca3af; }
        .nestable-editor ol { list-style: none; }
        .nestable-row { display: flex; align-items: center; gap: .625rem; min-height: 3rem; padding: .375rem .75rem; border: 1px solid var(--nestable-border); border-radius: .75rem; background: var(--nestable-bg); }
        .nestable-node + .nestable-node { margin-top: .375rem; }
        .nestable-collapse { display: flex; flex: 0 0 1rem; color: var(--nestable-muted); }
        .nestable-handle { display: flex; flex-shrink: 0; color: var(--nestable-muted); cursor: grab; touch-action: none; user-select: none; }
        .nestable-row-inactive { background: #f8fafc; }
        .dark .nestable-row-inactive { background: #1f2937; }
        .nestable-row-inactive .nestable-label { color: var(--nestable-muted); }
        .nestable-expand-toggle { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 2.25rem; height: 2.25rem; border-radius: .5rem; color: var(--nestable-muted); }
        .nestable-expand-toggle:hover { background: var(--nestable-border); }
        .nestable-footer { padding-top: 1rem; }
        .nestable-dragging { opacity: .35; }
        .nestable-handle:active { cursor: grabbing; }
        .nestable-content { display: flex; flex: 1; align-items: baseline; gap: 1rem; min-width: 0; }
        .nestable-label { overflow-wrap: anywhere; }
        .nestable-subtitle { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .875rem; color: var(--nestable-muted); }
        .nestable-actions { display: flex; flex-shrink: 0; align-items: center; gap: .5rem; }
        .nestable-editor { position: relative; }
        .nestable-drop-indicator { position: absolute; z-index: 20; border-top: 3px solid #2563eb; pointer-events: none; }
        .nestable-drop-indicator span { position: absolute; right: 0; bottom: 3px; padding: .125rem .5rem; border-radius: .25rem; background: #2563eb; color: white; font-size: .75rem; }
        .nestable-children { margin-inline-start: 2rem; padding-top: .375rem; min-height: 1.5rem; }
        .nestable-toolbar { position: sticky; top: 4rem; z-index: 10; display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; padding-block: .75rem; margin-bottom: .5rem; background: var(--nestable-bg); }
        @media (max-width: 640px) {
            .nestable-children { margin-inline-start: 1rem; }
            .nestable-content { flex-direction: column; gap: 0; }
            .nestable-subtitle { max-width: 100%; }
            .nestable-row { gap: .375rem; padding-inline: .5rem; }
        }
    </style>
    <div class="nestable-editor" data-max-depth="{{ $this->getTreeMaxDepth() }}" :data-dirty="dirty ? 'true' : 'false'" x-data="{
        expanded: true,
        dirty: false,
        dragged: null,
        pending: null,
        ghost: null,
        frame: null,
        pointer: null,
        dragLeft: 0,
        indent: 32,
        maxDepth: 5,
        revision: 0,
        destination: null,
        indicator: null,
        destroy() {
            this.resetDrag();
        },
        startPointer(event) {
            const handle = event.target.closest('.nestable-handle');
            if (!handle || event.button !== 0 || this.pending) return;
            event.preventDefault();
            const node = handle.closest('li[data-id]');
            const bounds = node.querySelector(':scope > .nestable-row').getBoundingClientRect();
            this.pending = { node, x: event.clientX, y: event.clientY, bounds, id: event.pointerId };
            this.pointer = { x: event.clientX, y: event.clientY };
            this.$refs.root.parentElement.setPointerCapture(event.pointerId);
        },
        movePointer(event) {
            if (!this.pending || event.pointerId !== this.pending.id) return;
            this.pointer = { x: event.clientX, y: event.clientY };
            if (!this.dragged && Math.hypot(event.clientX - this.pending.x, event.clientY - this.pending.y) < 5) return;
            event.preventDefault();
            if (!this.dragged) {
                this.dragged = this.pending.node;
                const row = this.dragged.querySelector(':scope > .nestable-row');
                this.indent = parseFloat(getComputedStyle(this.dragged.querySelector(':scope > ol')).marginInlineStart) || 32;
                this.maxDepth = Number(this.$refs.root.parentElement.dataset.maxDepth);
                this.ghost = row.cloneNode(true);
                for (const element of [this.ghost, ...this.ghost.querySelectorAll('*')]) {
                    for (const attribute of [...element.attributes]) {
                        if (/^(id$|wire:|x-|@|:)/.test(attribute.name)) element.removeAttribute(attribute.name);
                    }
                }
                Object.assign(this.ghost.style, { position: 'fixed', zIndex: 100, pointerEvents: 'none', margin: '0', width: `${this.pending.bounds.width}px`, opacity: '.9', boxShadow: '0 8px 24px #0003', background: getComputedStyle(row).backgroundColor });
                this.ghost.setAttribute('aria-hidden', 'true');
                Alpine.mutateDom(() => document.body.append(this.ghost));
                this.dragged.classList.add('nestable-dragging');
            }
            if (!this.frame) this.frame = requestAnimationFrame(() => this.paintDrag());
        },
        paintDrag() {
            this.frame = null;
            if (!this.dragged) return;
            this.dragLeft = this.pending.bounds.left + this.pointer.x - this.pending.x;
            this.ghost.style.left = `${this.dragLeft}px`;
            this.ghost.style.top = `${this.pending.bounds.top + this.pointer.y - this.pending.y}px`;
            const scroll = this.pointer.y < 70 ? -12 : (this.pointer.y > window.innerHeight - 70 ? 12 : 0);
            if (scroll) window.scrollBy(0, scroll);
            this.previewPosition(this.pointer.x, this.pointer.y);
            if (scroll) this.frame = requestAnimationFrame(() => this.paintDrag());
        },
        finishPointer(event) {
            if (!this.pending || event.pointerId !== this.pending.id) return;
            if (this.dragged) {
                this.dragLeft = this.pending.bounds.left + event.clientX - this.pending.x;
                this.previewPosition(event.clientX, event.clientY);
                this.commitDrop();
            } else this.resetDrag();
        },
        resetDrag() {
            if (this.frame) cancelAnimationFrame(this.frame);
            const surface = this.$refs.root?.parentElement;
            if (this.pending && surface?.hasPointerCapture?.(this.pending.id)) surface.releasePointerCapture(this.pending.id);
            if (this.ghost) Alpine.mutateDom(() => this.ghost.remove());
            this.dragged?.classList.remove('nestable-dragging');
            this.dragged = this.pending = this.ghost = this.frame = this.pointer = null;
            this.destination = this.indicator = null;
        },
        previewPosition(x, y) {
            this.destination = this.indicator = null;
            const rootBounds = this.$refs.root.getBoundingClientRect();
            if (x < rootBounds.left - 20 || x > rootBounds.right + 20 || y < rootBounds.top - 24 || y > rootBounds.bottom + 48) return;
            const rows = [...this.$refs.root.querySelectorAll('.nestable-row')].filter(row => row.getClientRects().length && !this.dragged.contains(row));
            if (!rows.length) return;
            const row = rows.reduce((nearest, row) => {
                const bounds = row.getBoundingClientRect();
                const prior = nearest.getBoundingClientRect();
                return Math.abs(y - (bounds.top + bounds.height / 2)) < Math.abs(y - (prior.top + prior.height / 2)) ? row : nearest;
            });
            this.previewRow({ clientY: y }, row);
        },
        depth(node) {
            let depth = 1;
            while (node = node.parentElement.closest('li[data-id]')) depth++;
            return depth;
        },
        height(node) {
            const children = [...node.querySelector(':scope > ol').children].filter(child => child.matches('li[data-id]'));
            return 1 + Math.max(0, ...children.map(child => this.height(child)));
        },
        previewRow(event, row) {
            this.destination = this.indicator = null;
            let node = row.closest('li[data-id]');
            if (!this.dragged || this.dragged === node || this.dragged.contains(node)) return;
            let bounds = row.getBoundingClientRect();
            let mode = event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';
            if (mode === 'after' && this.dragLeft >= bounds.left + this.indent) {
                mode = 'child';
            } else {
                while (this.dragLeft <= bounds.left - this.indent) {
                    const parent = node.parentElement.closest('li[data-id]');
                    if (!parent) break;
                    node = parent;
                    bounds = node.querySelector(':scope > .nestable-row').getBoundingClientRect();
                }
            }
            const depth = this.depth(node) + (mode === 'child' ? 1 : 0);
            if (depth + this.height(this.dragged) - 1 > this.maxDepth) return;
            const list = mode === 'child' ? node.querySelector(':scope > ol') : node.parentElement;
            const before = mode === 'before' ? node : (mode === 'after' ? node.nextElementSibling : list.querySelector(':scope > li[data-id]'));
            if (this.dragged.contains(list) || this.dragged === before) return;
            const area = mode === 'after' ? node.getBoundingClientRect() : bounds;
            this.setDestination(list, before, mode, area, mode === 'before' ? area.top : area.bottom);
        },
        setDestination(list, before, mode, bounds, top) {
            const origin = this.$refs.root.parentElement.getBoundingClientRect();
            const indent = mode === 'child' ? this.indent : 0;
            this.destination = { list, before };
            this.indicator = { mode, style: `top: ${top - origin.top}px; left: ${bounds.left - origin.left + indent}px; width: ${Math.max(0, bounds.width - indent)}px` };
        },
        commitDrop() {
            if (!this.dragged || !this.destination) return this.resetDrag();
            const { list, before } = this.destination;
            if (this.dragged.parentElement !== list || this.dragged.nextElementSibling !== before) this.dirty = true;
            Alpine.mutateDom(() => list.insertBefore(this.dragged, before));
            this.revision++;
            const parent = list.closest('li[data-id]');
            if (parent) this.$dispatch('nestable-open', parent.dataset.id);
            this.resetDrag();
        },
        read(list) {
            return Array.from(list.children).filter(node => node.matches('li[data-id]')).map(node => ({
                id: node.dataset.id, children: this.read(node.querySelector(':scope > ol'))
            }));
        }
    }" @nestable-saved.window="dirty = false" @nestable-branch-toggle="$nextTick(() => expanded = [...$refs.root.querySelectorAll('.nestable-collapse')].filter(button => button.style.visibility !== 'hidden').every(button => button.getAttribute('aria-expanded') === 'true'))" @pointerdown="startPointer($event)" @pointermove.window="movePointer($event)" @pointerup.window="finishPointer($event)" @pointercancel.window="resetDrag()" @lostpointercapture="if (pending) resetDrag()" @keydown.escape.window="resetDrag()" @dragstart.prevent>
        <div x-show="indicator" x-cloak class="nestable-drop-indicator" :style="indicator?.style">
            <span x-text="indicator?.mode === 'before' ? @js(__('filament-nestable::ui.drop_before')) : (indicator?.mode === 'after' ? @js(__('filament-nestable::ui.drop_after')) : (indicator?.mode === 'child' ? @js(__('filament-nestable::ui.drop_child')) : @js(__('filament-nestable::ui.drop_end'))))"></span>
        </div>
        <div class="nestable-toolbar">
            <button type="button" class="nestable-expand-toggle" x-on:click="expanded = !expanded; $dispatch('nestable-expand', expanded)" :aria-label="expanded ? @js(__('filament-nestable::ui.collapse_all')) : @js(__('filament-nestable::ui.expand_all'))" :title="expanded ? @js(__('filament-nestable::ui.collapse_all')) : @js(__('filament-nestable::ui.expand_all'))">
                <x-filament::icon icon="heroicon-o-arrows-pointing-in" class="size-5" x-show="expanded" />
                <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-5" x-show="!expanded" x-cloak />
            </button>
            <x-filament::button x-on:click="$wire.saveTree(read($refs.root))" wire:loading.attr="disabled">{{ __('filament-nestable::ui.save_tree') }}</x-filament::button>
        </div>
        <ol x-ref="root" style="min-height: 2.5rem" class="space-y-2 min-h-10">
            @include('filament-nestable::tree-nodes', ['nodes' => $tree])
        </ol>
        <div class="nestable-footer">
            @foreach ($this->getTreeFooterActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </div>
    @error('tree') <p role="alert" class="text-danger-600">{{ $message }}</p> @enderror
</x-filament-panels::page>
