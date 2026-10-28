<?php

namespace Fayne\FilamentNestable\Tests;

use Fayne\FilamentNestable\NestableServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app): array
    {
        return array_filter([NestableServiceProvider::class, class_exists(\CategoryPanelProvider::class) ? \CategoryPanelProvider::class : null]);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:5qJq8O4L7PJkq4+9eX6xqz5VxA8w3iH+0mZkR4j7r7U=');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
    }
}
