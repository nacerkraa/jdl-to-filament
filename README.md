# jdl-to-filament

Generate Laravel migrations, Eloquent models, and Filament v5 resources directly from a [JHipster JDL](https://www.jhipster.tech/jdl/intro) file.

```text
JDL file → migrations + models + DTOs + services + Filament resources
```

## Requirements

- PHP 8.3+
- Node.js
- A Laravel application when using the Artisan commands
- Filament 5 in the consuming application when using generated resources

## Installation

The package is currently intended for development as a local Composer path repository:

```json
{
    "repositories": [{"type": "path", "url": "../jdl-to-filament"}],
    "require": {"nacer/jdl-to-filament": "*"}
}
```

```bash
composer require nacer/jdl-to-filament
php artisan jdl:install-node
```

## Usage

For individual steps:

```bash
php artisan jdl:generate schema.jdl
php artisan jdl:migrations schema.jdl [--dry]
php artisan jdl:models schema.jdl [--dry]
php artisan jdl:dtos schema.jdl [--dry]
php artisan jdl:services schema.jdl [--dry]
php artisan jdl:filament schema.jdl [--dry]
```

For the complete pipeline:

```bash
php artisan jdl:scaffold schema.jdl
```

The scaffold command parses and maps the JDL once, then generates models, DTOs, services, migrations, optionally runs migrations, and generates Filament resources. **It does not change the database by default.** Use `--skip-migrate` to explicitly keep the database untouched; otherwise the scaffold runs `php artisan migrate` before generating Filament resources.

You can override output directories with `--models-path=`, `--dtos-path=`, `--services-path=`, `--migrations-path=`, and `--filament-path=`.

## JDL options

The generator understands these JDL entity options:

```jdl
paginate Product with pagination
dto Product with mapstruct
service Product with serviceClass
filter Product
```

- **Pagination:** generated Filament tables expose page-size options `[10, 25, 50, 100]`.
- **DTO:** entities with `dto` generate `App\\Http\\Resources\\ProductResource`. Related resources are nested with `whenLoaded()` only when the target entity also has a DTO; otherwise the foreign-key ID is exposed for to-one relationships.
- **Service:** entities with `service` generate `App\\Services\\ProductService` with basic `all`, `find`, `create`, `update`, and `delete` methods.
- **Filter:** entities with JDL `filter` get generated Filament filters for Boolean, enum, and to-one relationships. More advanced string/numeric/date filters are still a future extension.

A runnable example is available at `samples/sample_options.jdl`.

## What's implemented

- Scalar JDL field types mapped to Laravel migrations, Eloquent casts, and Filament components
- Enum fields mapped to migration enums and Filament selects
- ManyToOne and OneToOne relationships with foreign keys and `belongsTo()`
- Bidirectional OneToMany relationships with correctly resolved `hasMany()` foreign keys
- **ManyToMany relationships** with a deterministic, deduplicated pivot migration, cascade-delete foreign keys, composite primary key, and correctly flipped `belongsToMany()` keys on both models
- Filament ManyToMany multi-select fields using `->multiple()->searchable()->preload()`
- Dependency-ordered entity migrations and pivot migrations generated after all entity tables
- Shared snake_case naming rules for tables, columns, foreign keys, and pivots
- JDL pagination, DTO, service, and filter options
- Configurable output paths
- `jdl:scaffold` for a single end-to-end generation workflow
- Automated unit tests for naming, ManyToMany generation, JDL options, and generated PHP syntax

## ManyToMany example

```jdl
entity Post {
  title String required
}

entity Tag {
  name String required
}

relationship ManyToMany {
  Post{tags(name)} to Tag{posts(title)}
}
```

This produces one `post_tag` pivot migration even though JDL exposes the relationship from both sides. The generated models use the same pivot table with reversed foreign-key arguments:

```php
// Post
return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');

// Tag
return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
```

ManyToMany relationships get a relationship-aware multiple select in Filament. They are intentionally not emitted as a scalar `TextColumn`; a RelationManager is a better future extension for richer table/edit-page management.

## Testing

```bash
composer install
composer test
```

The CI workflow runs the unit suite on PHP 8.3 and 8.4. Generated model and migration source is also checked with `php -l` in the tests.

Full integration validation should be run in a real Laravel + Filament application against a disposable database.

## Known limitations / next work

- **Filament RelationManagers:** not generated yet for OneToMany or richer ManyToMany management.
- **Advanced filters:** string contains, numeric ranges, date ranges, and multi-value relationship filters need custom Filament query filters.
- **PHP enums:** enum fields do not generate PHP backed enum classes or casts; labels are not humanized.
- **Required relationships:** relationship-level `required` is not yet captured, so generated foreign keys are nullable.
- **Decimal precision:** currently fixed at `(10, 2)`.
- **Self-referencing relationships:** need dedicated handling/tests, especially self-referencing ManyToMany pivots.
- **Multiple M2M relations between the same entity pair:** the current deterministic pair-based pivot name can collide; a relation-aware naming strategy is needed.
- **Generated-code customization:** no templates/hooks yet for projects that need conventions different from the built-in output.
- **Integration test application:** the package tests generation, but a disposable Laravel + Filament fixture app is still needed to validate generated code at runtime.
- **Packagist/release automation:** package is not yet published as a stable release with a versioning/release workflow.

## Project structure

```text
jdl-to-filament/
├── composer.json
├── config/
├── node/
├── src/
│   ├── JdlParser.php
│   ├── EntityMapper.php
│   ├── Naming.php
│   ├── TypeMapper.php
│   ├── MigrationGenerator.php
│   ├── ModelGenerator.php
│   ├── DtoGenerator.php
│   ├── ServiceGenerator.php
│   ├── FilamentResourceGenerator.php
│   ├── Models/
│   └── Console/Commands/
│       ├── GenerateCommand.php
│       ├── GenerateMigrationsCommand.php
│       ├── GenerateModelsCommand.php
│       ├── GenerateDtosCommand.php
│       ├── GenerateServicesCommand.php
│       ├── GenerateFilamentResourcesCommand.php
│       ├── InstallNodeCommand.php
│       └── ScaffoldCommand.php
├── samples/
└── tests/Unit/
```

## Contributing

See `CONTRIBUTING.md` for the development and testing workflow.
