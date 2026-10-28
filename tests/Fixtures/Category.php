<?php

namespace Fayne\FilamentNestable\Tests\Fixtures;

use Fayne\FilamentNestable\Contracts\ShouldNestable;
use Fayne\FilamentNestable\Traits\Nestable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model implements ShouldNestable
{
    use Nestable;

    public function getParentFieldName(): string
    {
        return 'ancestor';
    }

    public function getWeightFieldName(): string
    {
        return 'position';
    }

    protected $table = 'categories';

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';
}
