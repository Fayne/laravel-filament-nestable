<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Nestable

简体中文 | [English](docs/README.en.md)

适用于 **PHP 8.4+、Laravel 13、Filament 5、Livewire 4.4.8+** 的通用树结构编辑包。基于现有模型的父子关系和权重字段提供拖拽排序，不依赖 CMS Core、Page 或 Menu。

## 功能与边界

| 能力 | 说明 |
| --- | --- |
| 通用树页面 | Resource 可继承 TreePage，不限于菜单，也可用于分类等模型 |
| 拖拽交互 | 上下排序、横向缩进/退级、单一落点提示及展开/折叠 |
| 可选操作 | 配置编辑链接、状态切换与叶节点删除；新增节点由宿主实现 |
| 服务端校验 | 完整集合、重复/外部节点、循环/孤立节点、深度与逐节点授权 |
| 持久化与事件 | 事务写入 parent/weight/可选 level，提交后发送实际变更事件 |
| 中英文界面 | 内置 zh_CN 与 en；业务节点标题不自动翻译 |

## 1. 安装与模型准备

在宿主 Composer 配置本私有包的软件源后安装：

```bash
composer require fayne/laravel-filament-nestable
```

服务提供者自动发现；本包不创建业务表、不需要单独的初始化命令或 Panel 插件。使用已有模型的 `parent_id`、`weight` 和标题列；`level` 列可选，默认不写。父项为空表示根节点，兼容旧的 0 根节点值。

宿主负责创建表、Filament Resource、认证与 Policy。字段名可通过 `TreeDefinition` 配置，不需要采用菜单包的表结构。本包无独立 npm 构建，也不要求额外的 Tailwind 内容扫描。

## 2. 注册树编辑页面

假设宿主已有 `App\Models\Category`、`CategoryResource` 和对应页面。创建树页面：

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

在 `CategoryResource` 的 `getPages()` 中注册，静态路径放在动态记录路径之前：

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

示例查询编辑所有可见分类。多租户、区域或所属目录场景，必须在模型全局作用域或服务端查询中加入可信范围；`scope` 只是事件范围标识，不会自动过滤数据。不要从可编辑 Livewire 属性接收模型类、租户 ID 或授权方法。

## 3. 定义选项与编辑行为

| 选项 | 默认值 / 作用 |
| --- | --- |
| `query` / `authorize` | 必填；服务端 Eloquent 查询与逐节点授权回调 |
| `parentColumn` / `weightColumn` | `parent_id` / `weight` |
| `levelColumn` | `null`；指定时写入节点层级 |
| `maxDepth` / `scope` | `5` / `default` |
| `titleColumn` / `subtitleColumn` | `name` / `null` |
| `toggleColumn` / `editUrl` | `null`；指定后提供状态/编辑操作 |
| `canDelete` | `false`；启用后仍检查 delete Policy 与子节点 |
| `validate` | 可选服务端额外校验回调，见 TreeService 调用契约 |

拖动卡片上下排序，向右移动足够距离进入子层，向左退级；仅显示当前落点。点击“保存”才持久化排序。展开/折叠不会丢弃隐藏子节点；字符串、UUID 和大整数 ID 以字符串传输，避免 JavaScript 数值精度损失。

通用状态/删除操作直接持久化。通用删除仅处理叶节点，不自动级联删除，也不默认禁止删除启用节点；Menu 的启用删除限制属于 Menu 集成。宿主可覆写节点操作及 `canAddChild()`、`canAddSibling()`、`getTreeFooterActions()` 实现专属交互。

## 4. 模型辅助方法

```php
use Fayne\FilamentNestable\Contracts\ShouldNestable;
use Fayne\FilamentNestable\Traits\Nestable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model implements ShouldNestable
{
    use Nestable;
}
```

可覆写 `getParentFieldName()`、`getWeightFieldName()`、`getTitleFieldName()` 映射自定义列。显式方法包括 `parent()`、`children()`、`descendants()`、`hasChildren()`、`rootNestableItems()`、`optionsMap()` 与 `$model->full_path`。

这些辅助方法按模型作用域和连接查询，不自动授权访问，也不安装全局递归 eager-load。循环或孤立关系会返回验证错误，不能用静默忽略节点的方式掩盖损坏数据。树页面不强制模型使用此 Trait；它也可通过 TreeDefinition 配合普通 Eloquent 模型。

## 5. 持久化、事件与并发

`TreeService::reorder()` 接收完整作用域的树，仅包含 id/children：

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

`$definition` 是前面创建的服务端 TreeDefinition。服务先锁定并加载定义范围内的记录，逐项授权并校验完整集合，拒绝遗漏、重复、外部节点和超深结构，再在事务中写入配置列。

`Events\OrderingUpdated` 提供模型、数据库连接、scope 与实际 before/after 变化；外层事务提交后发送，无变化或回滚不发送成功事件。

有所属记录的树应先锁所属记录，新增、编辑、删除和排序使用同一写入协议。Menu 已提供其所属菜单锁；通用包不会替宿主推断锁对象。无所属记录时，只锁现有行不代表可以阻止并发插入。SQLite 测试不等于 MySQL/PostgreSQL 并发验收。

## 6. 中英文、测试与升级

界面翻译为 `resources/lang/zh_CN` 与 `resources/lang/en`，跟随宿主 Laravel locale。节点标题、业务标签和内容翻译由宿主处理；本包不提供翻译组、公开内容 API 或 CMS 初始化命令。

在本包目录执行：

```bash
composer install
composer test
composer format
node --test tests/drag-preview.test.cjs
```

目标环境还需验证真实指针拖拽、保存重开、授权、跨范围隔离及写入并发。升级旧 Nova 集成时，将 `Fayne\NovaNestable` 改为 `Fayne\FilamentNestable`，`keyValueMapForNovaSelect()` 改为 `optionsMap()`。旧 Field/Tool 和任意类名/方法请求接口不再提供；使用本页的 Resource 集成。

## 贡献代码

1. 创建功能分支。
2. 实现所需改动，并为新增功能或缺陷修复补充测试。
3. 确认相关测试通过，注明尚未完成的环境验收。
4. 修改公开用法时，同步更新中英文文档。
5. 提交合并请求。

## 许可证

本软件采用[专有许可证](LICENSE.md)，使用与分发须遵守其中的授权限制。
