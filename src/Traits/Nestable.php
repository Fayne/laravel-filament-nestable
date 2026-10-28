<?php

namespace Fayne\FilamentNestable\Traits;

use Fayne\FilamentNestable\Support\TreeStructure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait Nestable
{
    public function getParentFieldName(): string
    {
        return 'parent_id';
    }

    public function getWeightFieldName(): string
    {
        return 'weight';
    }

    public function getTitleFieldName(): string
    {
        return 'name';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, $this->getParentFieldName());
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, $this->getParentFieldName())->orderBy($this->getWeightFieldName())->orderBy($this->getKeyName());
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function descendants(): Collection
    {
        $items = $this->newQuery()->get();
        $groups = $items->groupBy($this->getParentFieldName());
        $result = $this->newCollection();
        $seen = [(string) $this->getKey() => true];
        $walk = function ($key) use (&$walk, &$seen, $groups, $result): void {
            foreach ($groups->get($key, collect()) as $item) {
                $id = (string) $item->getKey();
                if (isset($seen[$id])) {
                    throw ValidationException::withMessages(['tree' => __('filament-nestable::ui.tree_cycle')]);
                }
                $seen[$id] = true;
                $result->push($item);
                $walk($id);
            }
        };
        $walk((string) $this->getKey());

        return $result;
    }

    public static function rootNestableItems(): Collection
    {
        $model = new static;
        $items = $model->newQuery()->orderBy($model->getWeightFieldName())->orderBy($model->getKeyName())->get();
        TreeStructure::build($items, $model->getParentFieldName(), $model->getTitleFieldName());
        $groups = $items->groupBy(fn ($item) => $item->{$model->getParentFieldName()} === null || (string) $item->{$model->getParentFieldName()} === '0' ? '' : (string) $item->{$model->getParentFieldName()});
        $attach = function ($parent) use (&$attach, $groups) {
            return $groups->get($parent, collect())->map(function ($item) use (&$attach) {
                $item->setRelation('children', $attach((string) $item->getKey()));

                return $item;
            });
        };

        return $attach('');
    }

    public static function optionsMap(string $separator = ' -> '): array
    {
        $model = new static;

        return TreeStructure::options($model->newQuery()->orderBy($model->getWeightFieldName())->orderBy($model->getKeyName())->get(), $model->getParentFieldName(), $model->getTitleFieldName(), $separator);
    }

    public function getFullPathAttribute(): string
    {
        $model = $this;
        $path = [];
        $seen = [];
        while ($model) {
            $id = (string) $model->getKey();
            if (isset($seen[$id])) {
                throw ValidationException::withMessages(['tree' => __('filament-nestable::ui.tree_cycle')]);
            }$seen[$id] = true;
            array_unshift($path, (string) $model->{$model->getTitleFieldName()});
            $parent = $model->{$model->getParentFieldName()};
            if ($parent === null || (string) $parent === '0') {
                break;
            }$model = $model->parent()->first();
            if (! $model) {
                throw ValidationException::withMessages(['tree' => __('filament-nestable::ui.parent_missing')]);
            }
        }

        return implode(' -> ', $path);
    }
}
