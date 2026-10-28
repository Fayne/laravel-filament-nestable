@foreach ($nodes as $node)
    <li data-id="{{ $node['id'] }}" wire:key="nestable-{{ $this->getId() }}-{{ $node['id'] }}" x-data="{ open: true }" @nestable-expand.window="open = $event.detail" @nestable-open.window="if ($event.detail === $el.dataset.id) open = true" class="nestable-node">
        <div @class(['nestable-row', 'nestable-row-inactive' => $node['has_status'] && ! $node['active']])>
            <button type="button" class="nestable-collapse" x-bind:style="(revision, $el.closest('li').querySelector(':scope > ol').querySelectorAll(':scope > li[data-id]').length) ? '' : 'visibility: hidden'" x-on:click="open = !open; $dispatch('nestable-branch-toggle')" x-bind:aria-expanded="open" aria-label="{{ __('filament-nestable::ui.toggle_node', ['label' => $node['label']]) }}">
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-4" x-bind:style="open ? '' : 'transform: rotate(-90deg)'" />
            </button>
            <span class="nestable-handle" role="img" aria-label="{{ __('filament-nestable::ui.drag_node', ['label' => $node['label']]) }}">
                <x-filament::icon icon="heroicon-m-bars-3" class="size-5" />
            </span>
            <div class="nestable-content">
                <span class="nestable-label">{{ $node['label'] }}</span>
                @if ($node['subtitle']) <span class="nestable-subtitle" title="{{ $node['subtitle'] }}">{{ $node['subtitle'] }}</span> @endif
            </div>
            <div class="nestable-actions">
                @if ($this->canAddSibling($node))
                    <x-filament::dropdown placement="bottom-end">
                        <x-slot name="trigger">
                            <x-filament::icon-button icon="heroicon-o-plus" color="success" :label="__('filament-nestable::ui.add_item')" />
                        </x-slot>
                        <x-filament::dropdown.list>
                            <x-filament::dropdown.list.item icon="heroicon-o-bars-3" :wire:click="'mountAction(\'createNode\', { after: '.\Illuminate\Support\Js::from((string) $node['id']).' })'">
                                {{ __('filament-nestable::ui.add_sibling') }}
                            </x-filament::dropdown.list.item>
                            <x-filament::dropdown.list.item icon="heroicon-o-arrow-turn-down-right" :disabled="! $this->canAddChild($node)" :tooltip="! $this->canAddChild($node) ? __('filament-nestable::ui.max_depth_reached') : null" :wire:click="'mountAction(\'createNode\', { parent: '.\Illuminate\Support\Js::from((string) $node['id']).' })'">
                                {{ __('filament-nestable::ui.add_child') }}
                            </x-filament::dropdown.list.item>
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                @elseif ($this->canAddChild($node))
                    <x-filament::icon-button icon="heroicon-o-plus" color="success" :label="__('filament-nestable::ui.add_child')" :wire:click="'mountAction(\'createNode\', { parent: '.\Illuminate\Support\Js::from((string) $node['id']).' })'" />
                @endif
                @if ($node['toggle_enabled'])
                    <x-filament::icon-button :icon="$node['active'] ? 'heroicon-o-eye' : 'heroicon-o-eye-slash'" :color="$node['active'] ? 'success' : 'gray'" :label="$node['active'] ? __('filament-nestable::ui.disable') : __('filament-nestable::ui.enable')" :wire:click="'toggleNode('.\Illuminate\Support\Js::from((string) $node['id']).')'" wire:loading.attr="disabled" />
                @endif
                @if ($node['edit_url'])
                    <x-filament::icon-button tag="a" :href="$node['edit_url']" icon="heroicon-o-pencil-square" color="gray" :label="__('filament-nestable::ui.edit')" />
                @endif
                @if ($node['deletable'])
                    <span title="{{ $this->deletionDisabledReason($node) }}">
                        <x-filament::icon-button icon="heroicon-o-trash" :color="$this->deletionDisabledReason($node) !== null ? 'gray' : 'danger'" :label="__('filament-nestable::ui.delete')" :disabled="$this->deletionDisabledReason($node) !== null" :wire:click="'deleteNode('.\Illuminate\Support\Js::from((string) $node['id']).')'" wire:confirm="{{ __('filament-nestable::ui.delete_this_node') }}" wire:loading.attr="disabled" />
                    </span>
                @endif
            </div>
        </div>
        <ol x-show="open && (revision, $el.querySelectorAll(':scope > li[data-id]').length > 0)" class="nestable-children" aria-label="{{ __('filament-nestable::ui.child_nodes', ['label' => $node['label']]) }}">
            @include('filament-nestable::tree-nodes', ['nodes' => $node['children']])
        </ol>
    </li>
@endforeach
