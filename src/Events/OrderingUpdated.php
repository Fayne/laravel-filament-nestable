<?php

namespace Fayne\FilamentNestable\Events;

final readonly class OrderingUpdated
{
    public function __construct(public string $model, public ?string $connection, public string $scope, public array $changes) {}
}
