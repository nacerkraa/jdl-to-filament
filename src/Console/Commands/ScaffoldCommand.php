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
        {--migrate : Run php artisan migrate after generating migrations}
        {--models-path= : Override models output path}
        {--migrations-path= : Override migrations output path}
        {--filament-path= : Override Filament resources output path}';

    protected $description = 'Generate models, migrations, and Filament resources from a JDL file';

    public function handle(
        JdlParser $parser,
        EntityMapper $mapper,
        ModelGenerator $modelGenerator,
        MigrationGenerator $migrationGenerator,
        FilamentResourceGenerator $filamentGenerator,
    ): int {
        $file = $this->argument('file');
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

        $entities = $mapper->map($rawEntities);

        $this->info('[1/3] Generating models...');
        $modelsDir = base_path($this->option('models-path') ?? config('jdl-to-filament.models_path', 'app/Models'));
        $this->writeFlatFiles($modelGenerator->generate($entities), $modelsDir);

        $this->info('[2/3] Generating migrations...');
        $migrationsDir = base_path($this->option('migrations-path') ?? config('jdl-to-filament.migrations_path', 'database/migrations'));
        $this->writeFlatFiles($migrationGenerator->generate($entities), $migrationsDir);

        if ($this->option('migrate')) {
            $this->info('Running migrate...');
            if ($this->call('migrate') !== self::SUCCESS) {
                $this->error('Migration command failed. Filament resources were not generated.');
                return self::FAILURE;
            }
        } else {
            $this->comment('Migration execution skipped. Use --migrate to apply generated migrations.');
        }

        $this->info('[3/3] Generating Filament resources...');
        $filamentDir = base_path($this->option('filament-path') ?? config('jdl-to-filament.filament_resources_path', 'app/Filament/Resources'));
        $this->writeNestedFiles($filamentGenerator->generate($entities), $filamentDir);

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function writeFlatFiles(array $files, string $outputDir): void
    {
        if (! is_dir($outputDir)) mkdir($outputDir, 0755, true);
        foreach ($files as $file) {
            file_put_contents($outputDir.DIRECTORY_SEPARATOR.$file['filename'], $file['content']);
            $this->line("  Created: {$file['filename']}");
        }
    }

    protected function writeNestedFiles(array $files, string $baseDir): void
    {
        foreach ($files as $file) {
            $path = $baseDir.DIRECTORY_SEPARATOR.$file['relativePath'];
            if (! is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
            file_put_contents($path, $file['content']);
            $this->line("  Created: {$file['relativePath']}");
        }
    }
}
