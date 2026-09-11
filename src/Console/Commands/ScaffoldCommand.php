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
        {--models-path= : Override models output path}
        {--dtos-path= : Override DTO output path}
        {--services-path= : Override service output path}
        {--migrations-path= : Override migrations output path}
        {--filament-path= : Override Filament resources output path}';

    protected $description = 'Run the full JDL generation pipeline';

    public function handle(
        JdlParser $parser,
        EntityMapper $mapper,
        ModelGenerator $modelGenerator,
        DtoGenerator $dtoGenerator,
        ServiceGenerator $serviceGenerator,
        MigrationGenerator $migrationGenerator,
        FilamentResourceGenerator $filamentGenerator,
    ): int {
        $result = $parser->parse($this->argument('file'));
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

        $entities = $mapper->map($rawEntities);

        $this->info('[1/6] Generating models...');
        $modelsDir = base_path($this->option('models-path') ?? config('jdl-to-filament.models_path', 'app/Models'));
        $this->writeFlatFiles($modelGenerator->generate($entities), $modelsDir);

        $this->info('[2/6] Generating DTOs...');
        $dtos = $dtoGenerator->generate($entities);
        if ($dtos !== []) {
            $dtosDir = base_path($this->option('dtos-path') ?? config('jdl-to-filament.dtos_path', 'app/Http/Resources'));
            $this->writeFlatFiles($dtos, $dtosDir);
        } else {
            $this->line('  No DTO-enabled entities.');
        }

        $this->info('[3/6] Generating services...');
        $services = $serviceGenerator->generate($entities);
        if ($services !== []) {
            $servicesDir = base_path($this->option('services-path') ?? config('jdl-to-filament.services_path', 'app/Services'));
            $this->writeFlatFiles($services, $servicesDir);
        } else {
            $this->line('  No service-enabled entities.');
        }

        $this->info('[4/6] Generating migrations...');
        $migrationsDir = base_path($this->option('migrations-path') ?? config('jdl-to-filament.migrations_path', 'database/migrations'));
        $this->writeFlatFiles($migrationGenerator->generate($entities), $migrationsDir);

        $this->info('[5/6] '.($this->option('skip-migrate') ? 'Skipping migrate (--skip-migrate).' : 'Running migrate...'));
        if (! $this->option('skip-migrate') && $this->call('migrate') !== self::SUCCESS) {
            $this->error('Migration command failed. Filament resources were not generated.');
            return self::FAILURE;
        }

        $this->info('[6/6] Generating Filament resources...');
        $filamentDir = base_path($this->option('filament-path') ?? config('jdl-to-filament.filament_resources_path', 'app/Filament/Resources'));
        $this->writeNestedFiles($filamentGenerator->generate($entities), $filamentDir);

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function writeFlatFiles(array $files, string $outputDir): void
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        foreach ($files as $file) {
            file_put_contents($outputDir.DIRECTORY_SEPARATOR.$file['filename'], $file['content']);
            $this->line("  Created: {$file['filename']}");
        }
    }

    protected function writeNestedFiles(array $files, string $baseDir): void
    {
        foreach ($files as $file) {
            $path = $baseDir.DIRECTORY_SEPARATOR.$file['relativePath'];
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $file['content']);
            $this->line("  Created: {$file['relativePath']}");
        }
    }
}
