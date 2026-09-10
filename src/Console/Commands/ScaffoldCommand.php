<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\FilamentResourceGenerator;
use Nacer\JdlToFilament\JdlParser;
use Nacer\JdlToFilament\MigrationGenerator;
use Nacer\JdlToFilament\ModelGenerator;

class ScaffoldCommand extends Command
{
    protected $signature = 'jdl:scaffold
        {file : Path to the .jdl file}
        {--skip-migrate : Generate model, migration, and Filament files, but do not run php artisan migrate}
        {--models-path= : Override models output path (defaults to config(\'jdl-to-filament.models_path\'))}
        {--migrations-path= : Override migrations output path (defaults to config(\'jdl-to-filament.migrations_path\'))}
        {--filament-path= : Override Filament resources output path (defaults to config(\'jdl-to-filament.filament_resources_path\'))}';

    protected $description = 'Run the full pipeline on a JDL file: generate models, generate migrations, run migrate, then generate Filament resources';

    public function handle(
        JdlParser $parser,
        EntityMapper $mapper,
        ModelGenerator $modelGenerator,
        MigrationGenerator $migrationGenerator,
        FilamentResourceGenerator $filamentGenerator,
    ): int {
        $file = $this->argument('file');

        $this->info("Parsing {$file} ...");
        $result = $parser->parse($file);

        if (! $result['success']) {
            $this->error('JDL parsing failed:');
            $this->line($result['error']);

            return self::FAILURE;
        }

        $rawEntities = $result['data']['entities'] ?? [];

        if (empty($rawEntities)) {
            $this->warn('Parsed successfully, but no entities were found in the file.');

            return self::SUCCESS;
        }

        // Parsed and mapped once, reused by all three generators - not
        // three separate parses like running the commands individually.
        $entities = $mapper->map($rawEntities);

        $this->newLine();
        $this->info('[1/4] Generating models...');
        $modelsDir = base_path($this->option('models-path') ?? config('jdl-to-filament.models_path', 'app/Models'));
        $this->writeFlatFiles($modelGenerator->generate($entities), $modelsDir, fn ($f) => $f['filename']);

        $this->newLine();
        $this->info('[2/4] Generating migrations...');
        $migrationsDir = base_path($this->option('migrations-path') ?? config('jdl-to-filament.migrations_path', 'database/migrations'));
        $this->writeFlatFiles($migrationGenerator->generate($entities), $migrationsDir, fn ($f) => $f['filename']);

        $this->newLine();
        if ($this->option('skip-migrate')) {
            $this->warn('[3/4] Skipping migrate (--skip-migrate given).');
        } else {
            $this->info('[3/4] Running migrate...');
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('[4/4] Generating Filament resources...');
        $filamentDir = base_path($this->option('filament-path') ?? config('jdl-to-filament.filament_resources_path', 'app/Filament/Resources'));
        $this->writeNestedFiles($filamentGenerator->generate($entities), $filamentDir);

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{filename: string, content: string}>  $files
     */
    protected function writeFlatFiles(array $files, string $outputDir, callable $filenameFor): void
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($files as $f) {
            $filename = $filenameFor($f);
            file_put_contents($outputDir.DIRECTORY_SEPARATOR.$filename, $f['content']);
            $this->line("  Created: {$filename}");
        }
    }

    /**
     * @param  array<int, array{relativePath: string, content: string}>  $files
     */
    protected function writeNestedFiles(array $files, string $baseDir): void
    {
        foreach ($files as $f) {
            $path = $baseDir.DIRECTORY_SEPARATOR.$f['relativePath'];
            $dir = dirname($path);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, $f['content']);
            $this->line("  Created: {$f['relativePath']}");
        }
    }
}
