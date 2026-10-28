<?php

namespace Fayne\FilamentNestable\Services;

use Fayne\FilamentNestable\Events\OrderingUpdated;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Illuminate\Validation\ValidationException;

final class TreeService
{
    public function reorder(TreeDefinition $definition, array $tree): void
    {
        $connection = $definition->query->getQuery()->getConnection();
        $connection->transaction(function () use ($definition, $tree, $connection): void {
            $items = (clone $definition->query)->lockForUpdate()->get()->keyBy(fn ($item) => (string) $item->getKey());
            $seen = [];
            $updates = [];
            $walk = function (array $nodes, ?string $parent = null, int $depth = 1) use (&$walk, &$seen, &$updates, $items, $definition): void {
                if (! array_is_list($nodes)) {
                    $this->invalid();
                }
                foreach ($nodes as $position => $node) {
                    if (! is_array($node)) {
                        $this->invalid();
                    }
                    $id = $node['id'] ?? null;
                    if ((! is_string($id) && ! is_int($id)) || (string) $id === '' || isset($seen[(string) $id]) || ! $items->has((string) $id) || $depth > $definition->maxDepth) {
                        $this->invalid();
                    }
                    $id = (string) $id;
                    $item = $items[$id];
                    $definition->authorize($item, 'update');
                    if ($definition->validate) {
                        ($definition->validate)($item, $depth);
                    }
                    $seen[$id] = true;
                    $updates[$id] = [$definition->parentColumn => $parent, $definition->weightColumn => $position];
                    if ($definition->levelColumn !== null) {
                        $updates[$id][$definition->levelColumn] = $depth;
                    }
                    $children = $node['children'] ?? [];
                    if (! is_array($children)) {
                        $this->invalid();
                    }
                    $walk($children, $id, $depth + 1);
                }
            };
            $walk($tree);
            if (count($seen) !== $items->count()) {
                $this->invalid();
            }
            $changes = [];
            foreach ($updates as $id => $values) {
                $item = $items[$id];
                $before = $item->only(array_keys($values));
                $item->forceFill($values);
                if (! $item->isDirty(array_keys($values))) {
                    continue;
                }
                $item->save();
                $changes[] = ['id' => (string) $id, 'before' => $before, 'after' => $item->only(array_keys($values))];
            }
            if ($changes !== []) {
                $event = new OrderingUpdated($definition->query->getModel()::class, $connection->getName(), $definition->scope, $changes);
                $connection->afterCommit(fn () => event($event));
            }
        });
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['tree' => __('filament-nestable::ui.invalid_tree')]);
    }
}
