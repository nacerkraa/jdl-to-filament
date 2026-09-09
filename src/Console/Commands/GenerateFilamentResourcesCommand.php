<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\FilamentResourceGenerator;
use Nacer\JdlToFilament\JdlParser;

class GenerateFilamentResourcesCommand extends Command
{
    protected $signature = 'jdl:filament
        {file : Path to the .jdl file}
        {--dry : Print the generated resources instead of writing files}
        {--path= : Directory (relative to the project root) to write resources into. Defaults to config(\'jdl-to-filament.filament_resources_path\')}';

    protected $description = 'Generate Filament v5 Resource + Page files from a JDL file';

    public function handle(JdlParser $parser, EntityMapper $mapper, FilamentResourceGenerator $generator): int
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
        $files = $generator->generate($entities);

        if ($this->option('dry')) {
            foreach ($files as $f) {
                $this->line("=== {$f['relativePath']} ===");
                $this->line($f['content']);
                $this->newLine();
            }

            return self::SUCCESS;
        }

        $relativePath = $this->option('path') ?? config('jdl-to-filament.filament_resources_path', 'app/Filament/Resources');
        $baseDir = base_path($relativePath);

        foreach ($files as $f) {
            $path = $baseDir.DIRECTORY_SEPARATOR.$f['relativePath'];
            $dir = dirname($path);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, $f['content']);
            $this->info("Created: {$f['relativePath']}");
        }

        return self::SUCCESS;
    }
}
