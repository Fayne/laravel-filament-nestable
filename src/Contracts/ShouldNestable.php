<?php

namespace Fayne\FilamentNestable\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface ShouldNestable
{
    public function parent(): BelongsTo;

    public function children(): HasMany;

    public function getParentFieldName(): string;

    public function getWeightFieldName(): string;

    public function getTitleFieldName(): string;
}
