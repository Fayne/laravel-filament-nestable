<?php

namespace Fayne\FilamentNestable\Filament\Pages;

use Fayne\FilamentNestable\Services\TreeService;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Fayne\FilamentNestable\Support\TreeStructure;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

abstract class TreePage extends Page
{
    protected string $view = 'filament-nestable::tree';

    #[Locked]
    public array $tree = [];

    abstract protected function authorizeTreeAccess(): void;

    abstract protected function treeDefinition(): TreeDefinition;

    public function getTreeFooterActions(): array
    {
        return [];
    }

    public function getTreeMaxDepth(): int
    {
        return $this->treeDefinition()->maxDepth;
    }

    public function canAddChild(array $node): bool
    {
        return false;
    }

    public function canAddSibling(array $node): bool
    {
        return false;
    }

    public function deletionDisabledReason(array $node): ?string
    {
        return null;
    }

    protected function loadTree(): void
    {
        $this->authorizeTreeAccess();
        $definition = $this->treeDefinition();
        $items = $definition->records();
        foreach ($items as $item) {
            $definition->authorize($item, 'view');
        }
        $tree = TreeStructure::build($items, $definition->parentColumn, $definition->titleColumn);
        $byId = $items->keyBy(fn ($item) => (string) $item->getKey());
        $decorate = function (array $nodes, int $depth = 1) use (&$decorate, $byId, $definition): array {
            foreach ($nodes as &$node) {
                $item = $byId[$node['id']];
                $node['subtitle'] = $definition->subtitleColumn ? (string) $item->getAttribute($definition->subtitleColumn) : null;
                $node['edit_url'] = $definition->editUrl && $definition->allows($item, 'update') ? ($definition->editUrl)($item) : null;
                $node['has_status'] = $definition->toggleColumn !== null;
                $node['toggle_enabled'] = $definition->toggleColumn !== null && $definition->allows($item, 'update');
                $node['active'] = $definition->toggleColumn ? (bool) $item->getAttribute($definition->toggleColumn) : false;
                $node['deletable'] = $definition->canDelete && $definition->allows($item, 'delete');
                $node['depth'] = $depth;
                $node['children'] = $decorate($node['children'], $depth + 1);
            }

            return $nodes;
        };
        $this->tree = $decorate($tree);
    }

    public function saveTree(array $tree): void
    {
        $this->authorizeTreeAccess();
        try {
            $this->persistTree($tree);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['tree' => collect($exception->errors())->flatten()->all()]);
        }
        $this->loadTree();
        $this->resetValidation('tree');
        $this->dispatch('nestable-saved');
        Notification::make()->title(__('filament-nestable::ui.tree_saved'))->success()->send();
    }

    protected function persistTree(array $tree): void
    {
        app(TreeService::class)->reorder($this->treeDefinition(), $tree);
    }

    public function toggleNode(string $key): void
    {
        $this->authorizeTreeAccess();
        $definition = $this->treeDefinition();
        abort_unless($definition->toggleColumn !== null, 403);
        $definition->query->getQuery()->getConnection()->transaction(function () use ($definition, $key): void {
            $node = (clone $definition->query)->lockForUpdate()->findOrFail($key);
            $definition->authorize($node, 'update');
            $node->setAttribute($definition->toggleColumn, ! (bool) $node->getAttribute($definition->toggleColumn));
            $node->save();
        });
        $this->loadTree();
    }

    public function deleteNode(string $key): void
    {
        $this->authorizeTreeAccess();
        $definition = $this->treeDefinition();
        abort_unless($definition->canDelete, 403);
        $definition->query->getQuery()->getConnection()->transaction(function () use ($definition, $key): void {
            $node = (clone $definition->query)->lockForUpdate()->findOrFail($key);
            $definition->authorize($node, 'delete');
            if ($node->newQuery()->where($definition->parentColumn, $node->getKey())->exists()) {
                throw ValidationException::withMessages(['tree' => __('filament-nestable::ui.handle_child_nodes_before_deleting_this_node')]);
            }
            $node->delete();
        });
        $this->loadTree();
    }
}
