<?php

use Fayne\FilamentNestable\Events\OrderingUpdated;
use Fayne\FilamentNestable\Services\TreeService;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Fayne\FilamentNestable\Tests\Fixtures\Category;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::create('categories', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('ancestor')->nullable();
        $table->integer('position')->default(0);
        $table->integer('level')->default(1);
        $table->integer('tenant')->default(1);
        $table->boolean('is_active')->default(true);
    });
    foreach (['a', 'b', '9007199254740993'] as $id) {
        Category::create(['id' => $id, 'name' => $id]);
    }
    Category::create(['id' => 'foreign', 'name' => 'Foreign', 'tenant' => 2]);
    Event::fake([OrderingUpdated::class]);
});
function categoryDefinition(?Closure $authorize = null, int $maxDepth = 3): TreeDefinition
{
    return new TreeDefinition(Category::where('tenant', 1), $authorize ?? fn () => true, parentColumn: 'ancestor', weightColumn: 'position', levelColumn: 'level', maxDepth: $maxDepth, scope: 'categories:tenant:1');
}
it('saves scoped cross-level moves and dispatches changes after commit', function () {
    (new TreeService)->reorder(categoryDefinition(), [['id' => 'b', 'children' => [['id' => 'a', 'children' => [['id' => '9007199254740993', 'children' => []]]]]]]);
    expect(Category::find('a')->ancestor)->toBe('b')->and(Category::find('9007199254740993')->level)->toBe(3)->and(Category::find('foreign')->ancestor)->toBeNull();
    Event::assertDispatched(OrderingUpdated::class, fn ($event) => $event->scope === 'categories:tenant:1' && count($event->changes) === 2);
});
it('rejects invalid complete trees atomically', function (array $tree, int $depth) {
    $before = Category::all()->toArray();
    expect(fn () => (new TreeService)->reorder(categoryDefinition(maxDepth: $depth), $tree))->toThrow(ValidationException::class);
    expect(Category::all()->toArray())->toBe($before);
    Event::assertNotDispatched(OrderingUpdated::class);
})->with([
    'missing' => [[['id' => 'a', 'children' => []]], 3],
    'duplicate' => [[['id' => 'a', 'children' => []], ['id' => 'a', 'children' => []], ['id' => 'b', 'children' => []]], 3],
    'foreign' => [[['id' => 'a'], ['id' => 'b'], ['id' => '9007199254740993'], ['id' => 'foreign']], 3],
    'depth' => [[['id' => 'a', 'children' => [['id' => 'b', 'children' => [['id' => '9007199254740993']]]]]], 2],
    'self' => [[['id' => 'a', 'children' => [['id' => 'a']]], ['id' => 'b'], ['id' => '9007199254740993']], 3],
    'bad children' => [[['id' => 'a', 'children' => 'invalid'], ['id' => 'b'], ['id' => '9007199254740993']], 3],
]);
it('reauthorizes all nodes before writing', function () {
    $definition = categoryDefinition(fn ($node) => $node->id !== 'a');
    expect(fn () => (new TreeService)->reorder($definition, [['id' => 'b'], ['id' => 'a'], ['id' => '9007199254740993']]))->toThrow(AuthorizationException::class);
    expect(Category::find('b')->position)->toBe(0);
    Event::assertNotDispatched(OrderingUpdated::class);
});
it('does not emit a success event when an outer owner transaction rolls back', function () {
    $db = Category::resolveConnection();
    $db->beginTransaction();
    (new TreeService)->reorder(categoryDefinition(), [['id' => 'b'], ['id' => 'a'], ['id' => '9007199254740993']]);
    Event::assertNotDispatched(OrderingUpdated::class);
    $db->rollBack();
    expect(Category::find('a')->position)->toBe(0);
    Event::assertNotDispatched(OrderingUpdated::class);
});

it('keeps its transaction on the query connection when the default connection changes', function () {
    $definition = categoryDefinition();
    config(['database.connections.secondary' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'secondary']);
    Category::saving(function ($item) {
        if ($item->id === 'a') {
            throw new RuntimeException('fixture failure');
        }
    });
    try {
        expect(fn () => (new TreeService)->reorder($definition, [['id' => '9007199254740993'], ['id' => 'b'], ['id' => 'a']]))->toThrow(RuntimeException::class, 'fixture failure');
        expect(Category::on('sqlite')->find('b')->position)->toBe(0);
    } finally {
        config(['database.default' => 'sqlite']);
    }
});
