<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\JdlParser;
use Nacer\JdlToFilament\MigrationGenerator;

class GenerateMigrationsCommand extends Command
{
    protected $signature = 'jdl:migrations
        {file : Path to the .jdl file}
        {--dry : Print the generated migrations instead of writing files}
        {--path= : Directory (relative to the project root) to write migrations into. Defaults to config(\'jdl-to-filament.migrations_path\')}';

    protected $description = 'Generate Laravel migration files from a JDL file';

    public function handle(JdlParser $parser, EntityMapper $mapper, MigrationGenerator $generator): int
    {
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

        $entities = $mapper->map($rawEntities);
        $migrations = $generator->generate($entities);

        if ($this->option('dry')) {
            foreach ($migrations as $migration) {
                $this->line("=== {$migration['filename']} ===");
                $this->line($migration['content']);
                $this->newLine();
            }

            return self::SUCCESS;
        }

        $relativePath = $this->option('path') ?? config('jdl-to-filament.migrations_path', 'database/migrations');
        $outputDir = base_path($relativePath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($migrations as $migration) {
            $path = $outputDir.DIRECTORY_SEPARATOR.$migration['filename'];
            file_put_contents($path, $migration['content']);
            $this->info("Created: {$migration['filename']}");
        }

        return self::SUCCESS;
    }
}
