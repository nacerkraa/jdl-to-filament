# jdl-to-filament

Generate Laravel migrations, Eloquent models, and Filament v5 resources directly from a [JHipster JDL](https://www.jhipster.tech/jdl/intro) file.

```text
JDL file → migrations + models + Filament resources
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
php artisan jdl:filament schema.jdl [--dry]
```

For the complete pipeline:

```bash
php artisan jdl:scaffold schema.jdl
```

The scaffold command parses and maps the JDL once, then generates models, migrations, and Filament resources. **It does not change the database by default.** Use `--migrate` explicitly when you want it to run `php artisan migrate` after generating the migrations.

You can override output directories for a run with `--models-path=`, `--migrations-path=`, and `--filament-path=`.

## What's implemented

- Scalar JDL field types mapped to Laravel migrations, Eloquent casts, and Filament components
- Enum fields mapped to migration enums and Filament selects
- ManyToOne and OneToOne relationships with foreign keys and `belongsTo()`
- Bidirectional OneToMany relationships with correctly resolved `hasMany()` foreign keys
- **ManyToMany relationships** with a deterministic, deduplicated pivot migration, cascade-delete foreign keys, composite primary key, and correctly flipped `belongsToMany()` keys on both models
- Filament ManyToMany multi-select fields using `->multiple()->searchable()->preload()`
- Dependency-ordered entity migrations and pivot migrations generated after all entity tables
- Shared snake_case naming rules for tables, columns, foreign keys, and pivots
- Configurable output paths
- `jdl:scaffold` for a single end-to-end generation workflow
- Automated unit tests for naming, ManyToMany generation, and generated PHP syntax

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

The Filament form gets a relationship-aware multiple select. ManyToMany relationships are intentionally not emitted as a `TextColumn` relationship path because a collection-valued relationship is not a scalar table column; a RelationManager is a better future extension for richer table/edit-page management.

A runnable input is available at `samples/sample_tags.jdl`.

## Testing

Install development dependencies and run:

```bash
composer install
composer test
```

The CI workflow runs the unit suite on supported PHP versions. Generated model and migration source is also checked with `php -l` in the tests.

For full integration validation, run the scaffold command in a real Laravel + Filament application and use `php artisan migrate` (or `jdl:scaffold --migrate`) against a disposable test database.

## Known limitations

- No Filament RelationManagers yet for OneToMany or richer ManyToMany editing
- Enum fields do not generate PHP backed enum classes yet
- Foreign keys are currently nullable because relationship-level `required` is not yet captured
- Decimal precision is currently fixed at `(10, 2)`
- Self-referencing relationships need dedicated coverage
- The package is not yet published to Packagist

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
│   ├── FilamentResourceGenerator.php
│   ├── Models/
│   └── Console/Commands/
│       ├── GenerateCommand.php
│       ├── GenerateMigrationsCommand.php
│       ├── GenerateModelsCommand.php
│       ├── GenerateFilamentResourcesCommand.php
│       ├── InstallNodeCommand.php
│       └── ScaffoldCommand.php
├── samples/
└── tests/Unit/
```

## Contributing

See `CONTRIBUTING.md` for the development and testing workflow.
