<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\DtoGenerator;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\FilamentResourceGenerator;
use Nacer\JdlToFilament\JdlParser;
use Nacer\JdlToFilament\MigrationGenerator;
use Nacer\JdlToFilament\ModelGenerator;
use Nacer\JdlToFilament\ServiceGenerator;

class ScaffoldCommand extends Command
{
    protected $signature = 'jdl:scaffold
        {file : Path to the .jdl file}
        {--skip-migrate : Generate all files, but do not run php artisan migrate}
        {--models-path= : Override models output path (defaults to config(\'jdl-to-filament.models_path\'))}
        {--migrations-path= : Override migrations output path (defaults to config(\'jdl-to-filament.migrations_path\'))}
        {--filament-path= : Override Filament resources output path (defaults to config(\'jdl-to-filament.filament_resources_path\'))}
        {--dtos-path= : Override DTOs output path (defaults to config(\'jdl-to-filament.dtos_path\'))}
        {--services-path= : Override services output path (defaults to config(\'jdl-to-filament.services_path\'))}';

    protected $description = 'Run the full pipeline on a JDL file: generate models, DTOs, services, migrations, run migrate, then generate Filament resources';

    public function handle(
        JdlParser $parser,
        EntityMapper $mapper,
        ModelGenerator $modelGenerator,
        DtoGenerator $dtoGenerator,
        ServiceGenerator $serviceGenerator,
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

        // Parsed and mapped once, reused by every generator - not five
        // separate parses like running the commands individually.
        $entities = $mapper->map($rawEntities);

        $this->newLine();
        $this->info('[1/6] Generating models...');
        $modelsDir = base_path($this->option('models-path') ?? config('jdl-to-filament.models_path', 'app/Models'));
        $this->writeFlatFiles($modelGenerator->generate($entities), $modelsDir, fn ($f) => $f['filename']);

        $this->newLine();
        $this->info('[2/6] Generating DTOs (entities with a JDL "dto" option)...');
        $dtos = $dtoGenerator->generate($entities);
        if (empty($dtos)) {
            $this->line('  None of these entities have a "dto" option set - skipped.');
        } else {
            $dtosDir = base_path($this->option('dtos-path') ?? config('jdl-to-filament.dtos_path', 'app/Http/Resources'));
            $this->writeFlatFiles($dtos, $dtosDir, fn ($f) => $f['filename']);
        }

        $this->newLine();
        $this->info('[3/6] Generating services (entities with a JDL "service" option)...');
        $services = $serviceGenerator->generate($entities);
        if (empty($services)) {
            $this->line('  None of these entities have a "service" option set - skipped.');
        } else {
            $servicesDir = base_path($this->option('services-path') ?? config('jdl-to-filament.services_path', 'app/Services'));
            $this->writeFlatFiles($services, $servicesDir, fn ($f) => $f['filename']);
        }

        $this->newLine();
        $this->info('[4/6] Generating migrations...');
        $migrationsDir = base_path($this->option('migrations-path') ?? config('jdl-to-filament.migrations_path', 'database/migrations'));
        $this->writeFlatFiles($migrationGenerator->generate($entities), $migrationsDir, fn ($f) => $f['filename']);

        $this->newLine();
        if ($this->option('skip-migrate')) {
            $this->warn('[5/6] Skipping migrate (--skip-migrate given).');
        } else {
            $this->info('[5/6] Running migrate...');
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('[6/6] Generating Filament resources...');
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
