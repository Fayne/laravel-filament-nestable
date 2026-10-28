<?php

use Fayne\FilamentNestable\Filament\Pages\TreePage;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Fayne\FilamentNestable\Tests\Fixtures\Category;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

class CategoryTreeResource extends Filament\Resources\Resource
{
    protected static ?string $model = Category::class;

    public static function getPages(): array
    {
        return ['index' => CategoryTreePage::route('/')];
    }
}
class CategoryTreePage extends TreePage
{
    protected static string $resource = CategoryTreeResource::class;

    protected function authorizeTreeAccess(): void
    {
        abort_unless(config('nestable-test.access', true), 403);
    }

    protected function treeDefinition(): TreeDefinition
    {
        return new TreeDefinition(Category::where('tenant', 1), fn ($node, $ability) => config('nestable-test.write', true), parentColumn: 'ancestor', weightColumn: 'position', levelColumn: 'level', scope: 'category:1', toggleColumn: 'is_active', canDelete: true);
    }

    public function mount(): void
    {
        $this->loadTree();
    }
}
class CategoryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('test')->path('test')->resources([CategoryTreeResource::class]);
    }
}
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
    Category::create(['id' => '9007199254740993', 'name' => 'Root']);
    Category::create(['id' => 'uuid-child', 'name' => 'Child']);
    Category::create(['id' => 'foreign', 'name' => 'Foreign', 'tenant' => 2]);
});
it('renders and saves a generic category tree with string-safe keys', function () {
    Livewire::test(CategoryTreePage::class)->assertSee('Root')->assertSee('Child')->assertDontSee('Foreign')
        ->call('saveTree', [['id' => '9007199254740993', 'children' => [['id' => 'uuid-child']]]])->assertHasNoErrors();
    expect(Category::find('uuid-child')->ancestor)->toBe('9007199254740993');
});
it('renders compact rows with separate drag handles and accessible actions', function () {
    $html = Livewire::test(CategoryTreePage::class)->html();
    expect($html)->not->toContain('@js(');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//li[@data-id and @draggable]')->length)->toBe(0)
        ->and($xpath->query('//span[@class="nestable-handle" and @aria-label]')->length)->toBe(2)
        ->and($xpath->query('//div[@class="nestable-row"]')->length)->toBe(2)
        ->and($xpath->query('//button[@aria-label="Disable"]')->length)->toBe(2)
        ->and($xpath->query('//button[@aria-label="Delete"]')->length)->toBe(2)
        ->and($xpath->query('//ol[contains(@style,"border-left")]')->length)->toBe(0);
    expect($xpath->query('//div[@class="nestable-drop-indicator"]')->length)->toBe(1)
        ->and($xpath->query('//li[contains(@class,"nestable-drop")]')->length)->toBe(0);
});
it('reauthorizes tree access and direct node mutations', function () {
    config(['nestable-test.access' => false]);
    Livewire::test(CategoryTreePage::class)->assertForbidden();
    config(['nestable-test.access' => true]);
    $page = Livewire::test(CategoryTreePage::class);
    config(['nestable-test.write' => false]);
    $page->call('toggleNode', 'uuid-child')->assertForbidden();
    expect((bool) Category::find('uuid-child')->is_active)->toBeTrue();
});
it('scopes toggle and delete calls and refuses deleting a non-leaf', function () {
    $page = Livewire::test(CategoryTreePage::class)->call('toggleNode', 'uuid-child')->assertHasNoErrors();
    expect((bool) Category::find('uuid-child')->is_active)->toBeFalse();
    $page->call('saveTree', [['id' => '9007199254740993', 'children' => [['id' => 'uuid-child']]]])->call('deleteNode', '9007199254740993')->assertHasErrors(['tree']);
    expect(Category::count())->toBe(3);
    Livewire::test(CategoryTreePage::class)->call('toggleNode', 'foreign')->assertNotFound();
});

it('does not allow a denied delete to remove any scoped node', function () {
    $page = Livewire::test(CategoryTreePage::class);
    config(['nestable-test.write' => false]);
    $page->call('deleteNode', 'uuid-child')->assertForbidden();
    expect(Category::count())->toBe(3);
});
it('deletes a permitted leaf without touching other scopes', function () {
    Livewire::test(CategoryTreePage::class)->call('deleteNode', 'uuid-child')->assertHasNoErrors();
    expect(Category::find('uuid-child'))->toBeNull()->and(Category::find('foreign'))->not->toBeNull();
});
