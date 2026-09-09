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

```bash
php artisan jdl:generate schema.jdl              # inspect parsed/mapped entities (no files written)
php artisan jdl:migrations schema.jdl [--dry]    # generate migrations
php artisan jdl:models schema.jdl [--dry]        # generate Eloquent models
php artisan jdl:filament schema.jdl [--dry]      # generate Filament v5 resources
```

Run them in that order (migrations before models before Filament) since
each generator assumes the previous ones' conventions. `--dry` prints
without writing, for every command except `jdl:generate` (which never
writes files).

Default output locations (override with `--path=...` per command, or
change the defaults via the published config):

| Command | Default path |
|---|---|
| `jdl:migrations` | `database/migrations` |
| `jdl:models` | `app/Models` |
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
│   ├── EntityMapper.php             # raw JDL JSON -> Entity[] objects
│   ├── Naming.php                   # shared table/column/FK/pivot naming rules
│   ├── TypeMapper.php               # JDL field type -> migration column + Eloquent cast + Filament component
│   ├── MigrationGenerator.php
│   ├── ModelGenerator.php
│   ├── FilamentResourceGenerator.php
│   ├── Models/
│   │   ├── Entity.php
│   │   ├── Field.php
│   │   └── Relationship.php
│   └── Console/Commands/
│       ├── GenerateCommand.php               # jdl:generate
│       ├── GenerateMigrationsCommand.php     # jdl:migrations
│       ├── GenerateModelsCommand.php         # jdl:models
│       ├── GenerateFilamentResourcesCommand.php  # jdl:filament
│       └── InstallNodeCommand.php            # jdl:install-node
└── samples/
    ├── sample.jdl
    ├── sample_enum.jdl
    ├── sample_blog.jdl
    └── sample_many_to_many.jdl # Student/Course ManyToMany example
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
- **`ManyToMany` relationships** → deterministic pivot-table migration,
  foreign keys with cascade delete, composite primary key,
  `belongsToMany()` on Eloquent models, and a Filament multiple searchable
  relationship `Select`
- Dependency-ordered migrations (a referenced table always migrates
  before the table referencing it; ManyToMany pivots are generated after
  all entity tables)
- JDL's camelCase → Laravel's snake_case for every column/field name
- Full Filament v5 syntax: `Schema`/`->components()`,
  `recordActions()`/`toolbarActions()`, the unified `Filament\Actions`
  namespace, `\BackedEnum|string|null $navigationIcon`

## ManyToMany example

Input:

```jdl
entity Student {
  name String required
}

entity Course {
  name String required
}

relationship ManyToMany {
  Student{courses(name)} to Course{students(name)}
}
```

This generates entity migrations plus a pivot migration similar to:

```php
Schema::create('course_student', function (Blueprint $table) {
    $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
    $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
    $table->primary(['student_id', 'course_id']);
});
```

The models get:

```php
public function courses(): BelongsToMany
{
    return $this->belongsToMany(Course::class, 'course_student');
}
```

and the Filament resource gets a multiple relationship-aware Select:

```php
Select::make('courses')
    ->relationship('courses', 'name')
    ->multiple()
    ->searchable()
    ->preload()
```

A sample input is available at `samples/sample_many_to_many.jdl`.

## Known limitations - not yet handled

These are open issues, good places to contribute:

- **No RelationManagers** for `OneToMany` sides - e.g. an Author's edit
  page has no "Posts" tab. The `hasMany()` method exists on the model,
  but nothing in the Filament resource surfaces it yet
- **Enum fields have no PHP-level representation** - no cast, no
  generated PHP backed enum class, no humanized Select labels (`'PAID'
  => 'PAID'` instead of `'PAID' => 'Paid'`)
- **No single orchestrator command** - you run `jdl:migrations`,
  `jdl:models`, and `jdl:filament` separately, in the right order, by
  hand. A `jdl:scaffold` command that runs all three in sequence would
  remove that footgun
- **No automated test suite ships with the package.** Every generator
  has been manually verified against the sample `.jdl` files during
  development, but none of that is committed as a runnable test suite yet
- **Decimal precision is hardcoded** to `(10, 2)` everywhere
- **Foreign keys are always nullable** - JDL's relationship-level
  `required` flag isn't captured yet

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
3. **Migration generator** - dependency-ordered `Schema::create()` files,
   with ManyToMany pivot migrations generated after entity tables.
4. **Model generator** - `$fillable`, `$casts`, `belongsTo()`/`hasMany()`/
   `belongsToMany()`.
5. **Filament resource generator** - originally built for Filament v3,
   then updated to v5's actual API (`Schema`, `recordActions()`,
   `toolbarActions()`, the unified `Actions` namespace) after confirming
   the exact shape against Filament's own `make:filament-resource
   --generate` output, not guessed from memory.
6. **Package restructure** (this step) - moved from "copy these files
   into your app" to a real installable Composer package: PSR-4 autoload,
   a `ServiceProvider` with command auto-registration, a publishable
   config file, and a `JdlParser` that locates its own bundled parser
   script relative to the package rather than assuming a consuming app's
   file layout.
