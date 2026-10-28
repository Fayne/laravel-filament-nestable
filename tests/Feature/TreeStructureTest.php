<?php

use Fayne\FilamentNestable\Support\TreeStructure;
use Fayne\FilamentNestable\Tests\Fixtures\Category;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::create('categories', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('ancestor')->nullable();
        $table->integer('position')->default(0);
    });
});

it('builds ordered custom-column trees and paths with string-safe keys', function () {
    $root = Category::create(['id' => '9007199254740993', 'name' => 'Root']);
    Category::create(['id' => 'child-uuid', 'name' => 'Child', 'ancestor' => $root->id]);
    $items = Category::orderBy('position')->get();
    $tree = TreeStructure::build($items, 'ancestor', 'name');
    expect($tree[0]['id'])->toBe('9007199254740993')->and($tree[0]['children'][0]['id'])->toBe('child-uuid');
    expect(TreeStructure::options($items, 'ancestor', 'name'))->toBe(['9007199254740993' => 'Root', 'child-uuid' => 'Root -> Child']);
});
it('accepts legacy zero roots while rejecting orphaned and cyclic stored trees', function (string $case) {
    if ($case === 'legacy') {
        Category::create(['id' => 'a', 'name' => 'A', 'ancestor' => '0']);
        expect(TreeStructure::build(Category::all(), 'ancestor'))->toHaveCount(1);

        return;
    }
    Category::create(['id' => 'a', 'name' => 'A', 'ancestor' => $case === 'orphan' ? 'missing' : 'b']);
    if ($case === 'cycle') {
        Category::create(['id' => 'b', 'name' => 'B', 'ancestor' => 'a']);
    }
    expect(fn () => TreeStructure::build(Category::all(), 'ancestor'))->toThrow(ValidationException::class);
})->with(['legacy', 'orphan', 'cycle']);

it('provides explicit model helpers without globally eager loading children', function () {
    $root = Category::create(['id' => 'root', 'name' => 'Root']);
    $child = Category::create(['id' => 'child', 'name' => 'Child', 'ancestor' => 'root']);
    Category::create(['id' => 'leaf', 'name' => 'Leaf', 'ancestor' => 'child']);
    expect($root->fresh()->relationLoaded('children'))->toBeFalse()->and($root->hasChildren())->toBeTrue()
        ->and($root->descendants()->modelKeys())->toBe(['child', 'leaf'])->and($child->fresh()->full_path)->toBe('Root -> Child');
    expect(Category::rootNestableItems()->first()->children->first()->children->first()->id)->toBe('leaf');
    expect(Category::optionsMap()['leaf'])->toBe('Root -> Child -> Leaf');
});
