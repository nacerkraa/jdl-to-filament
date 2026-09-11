# jdl-to-filament

Generate Laravel migrations, Eloquent models, and Filament v5 resources
directly from a [JHipster JDL](https://www.jhipster.tech/jdl/intro) file -
including the entity-relationship diagram you can draw for free in
[JDL Studio](https://start.jhipster.tech/jdl-studio/).

```
JDL file (with a real diagram) → migrations + models + Filament resources
```

Built incrementally, step by step - see "How this was built" below if
you want the reasoning behind each design decision.

## Requirements

```json
"require": {
    "php": "^8.3",
    "filament/filament": "~5.0",
    "laravel/framework": "^13.17",
    "laravel/tinker": "^3.0"
}
```

Also needs **Node.js** (any reasonably recent version) on your machine -
this package shells out to the real JHipster JDL parser (`jhipster-core`,
an npm package) rather than reimplementing JDL's grammar in PHP.

## Installation

This isn't on Packagist yet, so install it as a local **path repository**
for now (perfect for developing it further before publishing):

```json
// composer.json, in your Laravel app
{
    "repositories": [
        {
            "type": "path",
            "url": "../jdl-to-filament"
        }
    ],
    "require": {
        "nacer/jdl-to-filament": "*"
    }
}
```

(Adjust the `url` to wherever you put this package relative to your app.
Once it's pushed to GitHub, `"type": "vcs"` + the repo URL works the same
way without needing a local path - or once it's on Packagist, no
`repositories` entry is needed at all.)

```bash
composer require nacer/jdl-to-filament
```

Then install the Node dependency (one-time, this runs `npm install`
inside the package's bundled `node/` folder for you):

```bash
php artisan jdl:install-node
```

Optionally publish the config file if you want to change default output
paths:

```bash
php artisan vendor:publish --tag=jdl-to-filament-config
```

## Usage

**One command, the whole pipeline:**
```bash
php artisan jdl:scaffold schema.jdl
```
Runs, in order: generate models → generate DTOs (entities with a JDL
`dto` option) → generate services (entities with a JDL `service` option)
→ generate migrations → `php artisan migrate` → generate Filament
resources. The JDL file is parsed once and reused across every
generator. Use `--skip-migrate` to generate all the files without
touching the database, and `--models-path=`, `--dtos-path=`,
`--services-path=`, `--migrations-path=`, `--filament-path=` to override
any output location for that run.

**Or run each step yourself, if you want to inspect/adjust in between:**
```bash
php artisan jdl:generate schema.jdl              # inspect parsed/mapped entities (no files written)
php artisan jdl:migrations schema.jdl [--dry]    # generate migrations
php artisan jdl:models schema.jdl [--dry]        # generate Eloquent models
php artisan jdl:dtos schema.jdl [--dry]          # generate API Resources for entities with a "dto" option
php artisan jdl:services schema.jdl [--dry]      # generate Service classes for entities with a "service" option
php artisan jdl:filament schema.jdl [--dry]      # generate Filament v5 resources
```

`jdl:dtos` and `jdl:services` only produce output for entities that
explicitly opted in via JDL, e.g.:
```jdl
paginate Product with pagination
dto Product with mapstruct
service Product with serviceClass
filter Product
```
An entity with none of these options set gets a plain model, migration,
and Filament resource, same as before - no DTO or service class, and no
table filters.

`--dry` prints without writing, for every command except `jdl:generate`
(which never writes files).

Default output locations (override with `--path=...` per command, or
change the defaults via the published config):

| Command | Default path |
|---|---|
| `jdl:migrations` | `database/migrations` |
| `jdl:models` | `app/Models` |
| `jdl:dtos` | `app/Http/Resources` |
| `jdl:services` | `app/Services` |
| `jdl:filament` | `app/Filament/Resources` |

## Package structure

```
jdl-to-filament/
├── composer.json
├── config/
│   └── jdl-to-filament.php          # publishable: output paths, node script override
├── node/
│   ├── package.json
│   └── parse.js                     # shells out to jhipster-core, prints JSON
├── src/
│   ├── JdlToFilamentServiceProvider.php
│   ├── JdlParser.php                # PHP wrapper around node/parse.js
│   ├── EntityMapper.php             # raw JDL JSON -> Entity[] objects (incl. pagination/dto/service/filter options)
│   ├── Naming.php                   # shared table/column/FK/pivot naming rules
│   ├── TypeMapper.php               # JDL field type -> migration column + Eloquent cast + Filament component
│   ├── MigrationGenerator.php
│   ├── ModelGenerator.php
│   ├── DtoGenerator.php             # Laravel API Resource classes (JDL's "dto" option)
│   ├── ServiceGenerator.php         # CRUD Service classes (JDL's "service" option)
│   ├── FilamentResourceGenerator.php
│   ├── Models/
│   │   ├── Entity.php
│   │   ├── Field.php
│   │   └── Relationship.php
│   └── Console/Commands/
│       ├── GenerateCommand.php               # jdl:generate
│       ├── GenerateMigrationsCommand.php     # jdl:migrations
│       ├── GenerateModelsCommand.php         # jdl:models
│       ├── GenerateDtosCommand.php           # jdl:dtos
│       ├── GenerateServicesCommand.php       # jdl:services
│       ├── GenerateFilamentResourcesCommand.php  # jdl:filament
│       ├── InstallNodeCommand.php            # jdl:install-node
│       └── ScaffoldCommand.php               # jdl:scaffold - runs all six in sequence
└── samples/
    ├── sample.jdl          # Product/Category, ManyToOne (one-directional)
    ├── sample_enum.jdl     # Order with an enum field
    ├── sample_blog.jdl     # Author/Post, bidirectional OneToMany <-> ManyToOne
    ├── sample_tags.jdl     # Post/Tag, ManyToMany
    └── sample_options.jdl  # Product/Category/Warehouse - pagination, dto, service, filter options
```

## What's implemented

- All scalar JDL field types (String, Integer, Long, Float, Double,
  BigDecimal, LocalDate, Instant, ZonedDateTime, Boolean, UUID,
  TextBlob/Blob/ImageBlob/AnyBlob) mapped to migration columns, Eloquent
  casts, and Filament form/table components
- Enum fields → `$table->enum(...)`, a Filament `Select` with inline
  options, `->badge()` in tables
- `ManyToOne`/`OneToOne` relationships → foreign key migration columns,
  `belongsTo()` on the model, a searchable/preloaded Filament `Select`
  that auto-picks the target entity's first `String` field as the label
- `OneToMany` relationships → `hasMany()` on the model, correctly
  resolving the foreign key from the *other* side's relationship name
  (not a naming guess) - verified against a real bidirectional JDL file
- **`ManyToMany` relationships** → a single deduped pivot table migration
  (JDL exports the relationship on *both* sides; generating one per side
  would produce two colliding migrations, so it's deduped by the sorted
  table-name pair), `belongsToMany()` on both models with explicit,
  correctly-flipped foreign key arguments on each side, and a Filament
  multi-select (`->multiple()`) form field plus a `->badge()` table
  column - verified against a real bidirectional `ManyToMany` JDL file
- **Filament table filters** - only for entities with JDL's `filter`
  option set (exported as `jpaMetamodelFiltering`): `TernaryFilter` for
  Boolean fields, `SelectFilter` with inline options for enum fields,
  and `SelectFilter` with `->relationship()` for `ManyToOne`/`OneToOne`
  relationships. String/numeric/date fields don't get a generated filter
  yet (would need a custom `Filter::make()` with its own form + query
  closure, not just a one-line `make()`/`options()` call)
- **Pagination page-size options** (`->paginationPageOptions([10, 25, 50,
  100])`) on every generated table, regardless of JDL's `paginate` option
  - Filament tables paginate by default either way, so there's no
  on/off switch to wire up; this just gives a nicer page-size picker
  everywhere rather than gating it behind a flag that wouldn't actually
  change whether pagination happens
- **DTOs** (JDL's `dto` option) → a Laravel API Resource class
  (`App\Http\Resources\{Entity}Resource`) per opted-in entity, exposing
  all scalar fields. A `ManyToOne`/`OneToOne` relationship nests the
  related entity's own Resource via `whenLoaded()` *only if that target
  entity also has a `dto` option* (referencing a Resource class that
  doesn't exist would be a runtime error) - otherwise it falls back to
  exposing the plain foreign key id. `OneToMany`/`ManyToMany` relationships
  are included as a nested collection under the same condition, or
  omitted if the target has no DTO.
- **Services** (JDL's `service` option) → a `App\Services\{Entity}Service`
  class per opted-in entity with `all()`/`find()`/`create()`/`update()`/
  `delete()`, a thin wrapper around the Eloquent model. Laravel has no
  single enforced "service layer" convention the way JHipster's Java side
  does, so treat this as a starting point to extend/restyle for your own
  app's conventions, not a fixed shape.
- Dependency-ordered migrations (a referenced table always migrates
  before the table referencing it; pivot tables always migrate last,
  after every entity table)
- JDL's camelCase → Laravel's snake_case for every column/field name
- Full Filament v5 syntax: `Schema`/`->components()`,
  `recordActions()`/`toolbarActions()`, the unified `Filament\Actions`
  namespace, `\BackedEnum|string|null $navigationIcon`
- `jdl:scaffold` - one command running the full models → DTOs → services
  → migrations → migrate → Filament pipeline, parsing the JDL file only
  once

## Known limitations - not yet handled

These are open issues, good places to contribute:

- **No RelationManagers** for `OneToMany` sides - e.g. an Author's edit
  page has no "Posts" tab. The `hasMany()` method exists on the model,
  but nothing in the Filament resource surfaces it yet. `ManyToMany` gets
  a multi-select field instead, which covers the common case without
  needing a RelationManager.
- **Filters only cover Boolean, enum, and `ManyToOne`/`OneToOne`
  fields.** String/numeric/date fields on a filterable entity get no
  generated filter yet - contains/range filters need a custom
  `Filter::make()` with a form and query closure, not a one-line call.
- **Enum fields have no PHP-level representation** - no cast, no
  generated PHP backed enum class, no humanized Select labels (`'PAID'
  => 'PAID'` instead of `'PAID' => 'Paid'`)
- **No automated test suite ships with the package.** Every generator
  has been manually verified against the sample `.jdl` files during
  development (see "How this was built" below for what was actually
  checked), but none of that is committed as a runnable test suite yet -
  needed before this is trustworthy for outside contributors
- **No `LICENSE` or `CONTRIBUTING.md` yet** - planned before this goes
  public on GitHub
- **Decimal precision is hardcoded** to `(10, 2)` everywhere
- **Foreign keys are always nullable** - JDL's relationship-level
  `required` flag isn't captured yet
- **Self-referencing relationships** (an entity related to itself, e.g.
  a category with subcategories) haven't been tested with a real sample
  file - the pivot-naming logic in particular likely needs adjustment
  for a self-referencing `ManyToMany`

## How this was built

Built in incremental steps, each one actually tested against real JDL
input before moving to the next - not just written and assumed correct:

1. **Parser bridge** - a Node script wrapping `jhipster-core`, called from
   PHP via `Symfony\Process`. Two real bugs were found and fixed here by
   testing repeated runs and an edge-case entity name: `jhipster-core`
   logs warnings straight to stdout (corrupting JSON output) and caches
   `.jhipster/*.json` files that silently empty out a second run's
   result. Both fixed by isolating stdout and running each parse in a
   fresh scratch directory.
2. **Internal model** - `Entity`/`Field`/`Relationship` objects, decoupling
   every later generator from JDL's raw JSON shape.
3. **Migration generator** - dependency-ordered `Schema::create()` files.
   Testing a `TextBlob` field here surfaced a bug in the original type
   map: JDL doesn't export `TextBlob` as a distinct type string at all,
   it's `fieldType: "byte[]"` with a separate discriminator. Fixed by
   capturing that discriminator on `Field`.
4. **Model generator** - `$fillable`, `$casts`, `belongsTo()`/`hasMany()`.
   The trickiest part: resolving the correct foreign key for `hasMany()`
   from the *other* side's relationship name, verified against a real
   bidirectional JDL file (`sample_blog.jdl`) rather than assumed correct
   from a one-directional example.
5. **Filament resource generator** - originally built for Filament v3,
   then updated to v5's actual API (`Schema`, `recordActions()`,
   `toolbarActions()`, the unified `Actions` namespace) after confirming
   the exact shape against Filament's own `make:filament-resource
   --generate` output, not guessed from memory.
6. **Package restructure** - moved from "copy these files
   into your app" to a real installable Composer package: PSR-4 autoload,
   a `ServiceProvider` with command auto-registration, a publishable
   config file, and a `JdlParser` that locates its own bundled parser
   script relative to the package rather than assuming a consuming app's
   file layout.
7. **`ManyToMany` + orchestrator command** - checked JDL's
   actual `ManyToMany` export shape first (a real `sample_tags.jdl` test
   file) rather than assuming it mirrored `OneToMany`. It's exported
   symmetrically on both sides, which is exactly the kind of thing that
   causes a naive "generate one migration per relationship" approach to
   emit two colliding pivot migrations - deduped by the sorted table-name
   pair instead. Also added `jdl:scaffold`, which parses the JDL file
   once and reuses the mapped entities across the model, migration, and
   Filament generators, then runs `php artisan migrate` in between.
8. **Pagination, filters, DTOs, services** (this step) - checked how JDL
   actually exports the `paginate`/`dto`/`service`/`filter` entity options
   before writing anything (a real `sample_options.jdl` test file), which
   surfaced a real JHipster convention worth knowing: setting `dto`
   without `service` auto-enables `service` too. That mattered for
   `DtoGenerator`, which only nests a related entity's Resource class via
   `whenLoaded()` when that target *also* has a `dto` option - otherwise
   referencing a Resource class that was never generated would be a
   runtime error, so it falls back to exposing the plain foreign key id
   instead. Verified with a three-entity file where one relationship
   target has a DTO and the other doesn't, to exercise both branches
   rather than just the happy path.
