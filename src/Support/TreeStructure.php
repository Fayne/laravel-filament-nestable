<?php

namespace Fayne\FilamentNestable\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TreeStructure
{
    public static function build(Collection $items, string $parentColumn = 'parent_id', string $titleColumn = 'name'): array
    {
        $byId = $items->keyBy(fn ($item) => (string) $item->getKey());
        $groups = [];
        foreach ($items as $item) {
            $parent = $item->getAttribute($parentColumn);
            $root = $parent === null || (string) $parent === '0';
            if (! $root && ! $byId->has((string) $parent)) {
                self::invalid();
            }
            $groups[$root ? '' : (string) $parent][] = $item;
        }
        $seen = [];
        $build = function (string $parent) use (&$build, &$seen, $groups, $titleColumn): array {
            $nodes = [];
            foreach ($groups[$parent] ?? [] as $item) {
                $key = (string) $item->getKey();
                if (isset($seen[$key])) {
                    self::invalid();
                }
                $seen[$key] = true;
                $nodes[] = ['id' => $key, 'label' => (string) $item->getAttribute($titleColumn), 'children' => $build($key)];
            }

            return $nodes;
        };
        $tree = $build('');
        if (count($seen) !== $items->count()) {
            self::invalid();
        }

        return $tree;
    }

    public static function options(Collection $items, string $parentColumn = 'parent_id', string $titleColumn = 'name', string $separator = ' -> '): array
    {
        $options = [];
        $walk = function (array $nodes, string $prefix = '') use (&$walk, &$options, $separator): void {
            foreach ($nodes as $node) {
                $path = $prefix === '' ? $node['label'] : $prefix.$separator.$node['label'];
                $options[$node['id']] = $path;
                $walk($node['children'], $path);
            }
        };
        $walk(self::build($items, $parentColumn, $titleColumn));

        return $options;
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['tree' => __('filament-nestable::ui.tree_cycle_or_missing_parent')]);
    }
}
