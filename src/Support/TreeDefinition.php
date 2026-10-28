<?php

namespace Fayne\FilamentNestable\Support;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class TreeDefinition
{
    public function __construct(
        public readonly Builder $query,
        private readonly Closure $authorize,
        public readonly string $parentColumn = 'parent_id',
        public readonly string $weightColumn = 'weight',
        public readonly ?string $levelColumn = null,
        public readonly int $maxDepth = 5,
        public readonly string $scope = 'default',
        public readonly string $titleColumn = 'name',
        public readonly ?string $subtitleColumn = null,
        public readonly ?string $toggleColumn = null,
        public readonly ?Closure $validate = null,
        public readonly ?Closure $editUrl = null,
        public readonly bool $canDelete = false,
    ) {
        $this->query->getModel()->setConnection($this->query->getQuery()->getConnection()->getName());
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException(__('filament-nestable::errors.max_depth_positive'));
        }
    }

    public function authorize(Model $node, string $ability): void
    {
        if (($this->authorize)($node, $ability) === false) {
            throw new AuthorizationException;
        }
    }

    public function allows(Model $node, string $ability): bool
    {
        try {
            $this->authorize($node, $ability);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function records(): Collection
    {
        return (clone $this->query)->reorder()->orderBy($this->weightColumn)->orderBy($this->query->getModel()->getKeyName())->get();
    }
}
