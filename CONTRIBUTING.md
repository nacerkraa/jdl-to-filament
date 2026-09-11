# Contributing

Thanks for contributing to jdl-to-filament.

## Development

1. Clone the repository and install dependencies:

```bash
composer install
```

2. Run the unit test suite:

```bash
composer test
```

3. If you are changing JDL parsing or generated application code, also verify the relevant sample files with the individual generator commands in a Laravel host application.

## Pull requests

Please keep changes focused, add or update tests for generator behavior, and document user-facing changes in the README when appropriate.

## Generated code

The package generates Laravel and Filament source files. Tests should verify the generated source text and PHP syntax where practical; integration changes should also be checked in a real Laravel + Filament application before release.
