<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Nestable

[简体中文](../README.md) | English

Generic tree editing for **PHP 8.4+, Laravel 13, Filament 5, and Livewire 4.4.8+**. Uses parent relationships and ordering columns on existing models, without depending on CMS Core, Page, or Menu.

## Features and scope

| Capability | Description |
| --- | --- |
| Generic tree page | Resources can extend TreePage for menus, categories, and other models |
| Drag interaction | Vertical ordering, horizontal nesting/outdenting, one drop indicator, and expand/collapse |
| Optional actions | Configurable edit links, status toggles, and leaf deletion; node creation belongs to the host |
| Server validation | Complete-set, duplicate/external-node, cycle/orphan, depth, and per-node authorization checks |
| Persistence and events | Transactional parent/weight/optional-level updates and actual-change events after commit |
| Chinese and English UI | Built-in zh_CN and en; business node titles are not translated automatically |

## 1. Installation and model preparation

Configure a Composer repository for this private package in the host application, then install:

```bash
composer require fayne/laravel-filament-nestable
```

The service provider is auto-discovered. The package creates no business tables and needs no separate initialization command or panel plugin. Existing models need `parent_id`, `weight`, and a title column; `level` is optional and is not written by default. A null parent represents a root, with legacy zero roots also supported.

The host supplies tables, Filament resources, authentication, and policies. Configure column names through `TreeDefinition`; Menu's schema is not required. There is no separate npm build or additional Tailwind content scan.

## 2. Register the tree editor

Assume the host already defines `App\Models\Category`, `CategoryResource`, and its resource pages. Create a tree page:

```php
namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Fayne\FilamentNestable\Filament\Pages\TreePage;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Illuminate\Support\Facades\Gate;

class CategoryTree extends TreePage
{
    protected static string $resource = CategoryResource::class;

    public function mount(): void
    {
        $this->loadTree();
    }

    protected function authorizeTreeAccess(): void
    {
        Gate::authorize('viewAny', Category::class);
    }

    protected function treeDefinition(): TreeDefinition
    {
        return new TreeDefinition(
            query: Category::query(),
            authorize: fn ($node, $ability) => Gate::authorize($ability, $node),
            maxDepth: 5,
            scope: 'categories',
            titleColumn: 'name',
            toggleColumn: 'is_active',
            editUrl: fn ($node) => CategoryResource::getUrl('edit', ['record' => $node]),
            canDelete: true,
        );
    }
}
```

Register it in `CategoryResource::getPages()`, placing static routes before dynamic record routes:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListCategories::route('/'),
        'tree' => Pages\CategoryTree::route('/tree'),
        'create' => Pages\CreateCategory::route('/create'),
        'edit' => Pages\EditCategory::route('/{record}/edit'),
    ];
}
```

The example edits all categories visible to its query. For tenants, regions, or owning catalogs, apply trusted scoping through model scopes or the server-side query. `scope` labels the event scope; it does not filter data automatically. Do not accept model classes, tenant IDs, or authorization methods from editable Livewire properties.

## 3. Definition options and editing behavior

| Option | Default / purpose |
| --- | --- |
| `query` / `authorize` | Required server-side Eloquent query and per-node authorization callback |
| `parentColumn` / `weightColumn` | `parent_id` / `weight` |
| `levelColumn` | `null`; writes node depth when configured |
| `maxDepth` / `scope` | `5` / `default` |
| `titleColumn` / `subtitleColumn` | `name` / `null` |
| `toggleColumn` / `editUrl` | `null`; enable status/edit actions when configured |
| `canDelete` | `false`; enabling still requires delete Policy and child checks |
| `validate` | Optional extra server validation callback; see the TreeService call contract |

Drag vertically to reorder, move far enough right to nest, and left to outdent. Only the current drop destination is shown. Ordering is persisted when **Save** is clicked. Folding does not discard hidden descendants. String, UUID, and large integer IDs are transmitted as strings to avoid JavaScript precision loss.

Generic toggle/delete actions persist directly. Generic deletion handles leaves only, never implicitly cascades, and does not prohibit active-node deletion by default; that restriction belongs to the Menu integration. Hosts can override node operations and `canAddChild()`, `canAddSibling()`, or `getTreeFooterActions()` for specialized interactions.

## 4. Model helpers

```php
use Fayne\FilamentNestable\Contracts\ShouldNestable;
use Fayne\FilamentNestable\Traits\Nestable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model implements ShouldNestable
{
    use Nestable;
}
```

Override `getParentFieldName()`, `getWeightFieldName()`, and `getTitleFieldName()` for custom columns. Explicit helpers include `parent()`, `children()`, `descendants()`, `hasChildren()`, `rootNestableItems()`, `optionsMap()`, and `$model->full_path`.

Helpers use model scopes and connections without authorizing access or installing global recursive eager-loading. Cycles and orphaned relationships produce validation errors rather than silently hiding damaged nodes. Tree pages do not require the trait; TreeDefinition also works with ordinary Eloquent models.

## 5. Persistence, events, and concurrency

`TreeService::reorder()` accepts the complete scoped tree with id/children only:

```php
$tree = [
    ['id' => '1', 'children' => [
        ['id' => '2', 'children' => []],
    ]],
    ['id' => '3', 'children' => []],
];

app(\Fayne\FilamentNestable\Services\TreeService::class)
    ->reorder($definition, $tree);
```

`$definition` is the server-side TreeDefinition created earlier. The service locks and loads records in that scope, authorizes every node, validates the complete set, rejects missing/duplicate/external nodes and excessive depth, and writes the configured columns transactionally.

`Events\OrderingUpdated` contains the model, connection, scope, and actual before/after changes. It is emitted after the outer transaction commits, with no success event on rollback or no change.

Owned trees should lock their owner first and use the same write protocol for creation, editing, deletion, and reordering. Menu supplies its owning-menu lock; the generic package cannot infer a host's lock owner. For ownerless trees, locking existing rows alone does not serialize concurrent inserts. SQLite tests are not MySQL/PostgreSQL concurrency acceptance.

## 6. Languages, tests, and upgrades

Interface translations live in `resources/lang/zh_CN` and `resources/lang/en`, following the host Laravel locale. Node titles, business labels, and content translations belong to the host. This package does not provide translation groups, public content APIs, or CMS initialization commands.

Run in this package directory:

```bash
composer install
composer test
composer format
node --test tests/drag-preview.test.cjs
```

Target-environment acceptance should include actual pointer dragging, save/reopen, authorization, cross-scope isolation, and concurrent writes. When upgrading a Nova integration, change `Fayne\NovaNestable` to `Fayne\FilamentNestable` and `keyValueMapForNovaSelect()` to `optionsMap()`. Old Fields/Tools and arbitrary class/method request APIs are not retained; use the Resource integration shown here.

## Contributing

1. Create a feature branch.
2. Implement the change and add tests for new behavior or bug fixes.
3. Verify the relevant tests and state any outstanding environment acceptance.
4. Update both language versions when public usage changes.
5. Submit a merge request.

## License

This software uses a [proprietary license](../LICENSE.md). Use and distribution are subject to its authorization restrictions.
