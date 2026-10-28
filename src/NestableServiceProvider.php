<?php

namespace Fayne\FilamentNestable;

use Illuminate\Support\ServiceProvider;

class NestableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-nestable');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-nestable');
    }
}
