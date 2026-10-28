# Migration inventory

| Original | Target / decision |
| --- | --- |
| composer.json / Provider | Independent Filament package, own lock/vendor, NestableServiceProvider loads views |
| LICENSE.md | Retained verbatim |
| README.md / docs | Archived under legacy-reference; new README documents current API |
| Contracts/ShouldNestable | Typed relation and column-name contract |
| Traits/Nestable | Explicit model helpers; no globally recursive eager load; cycle/orphan checks |
| Nova/NestableField / config mapping | Resource TreePage + server-defined TreeDefinition; no arbitrary model/method payload |
| Controller ordering | Scoped TreeService, validate first, transaction and after-commit event |
| Controller toggle/delete | TreePage actions resolve scoped node and Policy; explicit columns; leaf-only deletion |
| NovaNestableOrderingUpdated / ActionEvent | OrderingUpdated change event after outer commit; no Nova history table dependency |
| Vue tool/field / routes / middleware | Shared Blade/Alpine resource tree page; no Nova/Inertia controller routes |
| Collapsible mixin | Per-node open state + global fold/unfold controls, children stay in DOM |
| Console model/resource/config/migration stubs | Host integration example and category test fixture; no automatic business schema generation |
| CategorySeeder | Test fixtures only, no host business seeding |
| Vite/package.json/dist | No independent JS bundle; Filament/Alpine runtime; essential drag hit-area styles in component |

The Menu integration reuses shared writes/UI while retaining its owner lock and business validation. The independent category tests prove no Core/Page/Menu dependency. Browser acceptance uses a disabled, named Menu fixture; no production exposure was enabled.
