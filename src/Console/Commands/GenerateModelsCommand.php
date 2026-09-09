<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\JdlParser;
use Nacer\JdlToFilament\ModelGenerator;

class GenerateModelsCommand extends Command
{
    protected $signature = 'jdl:models
        {file : Path to the .jdl file}
        {--dry : Print the generated models instead of writing files}
        {--path= : Directory (relative to the project root) to write models into. Defaults to config(\'jdl-to-filament.models_path\')}';

    protected $description = 'Generate Eloquent model files from a JDL file';

    public function handle(JdlParser $parser, EntityMapper $mapper, ModelGenerator $generator): int
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
        $models = $generator->generate($entities);

        if ($this->option('dry')) {
            foreach ($models as $model) {
                $this->line("=== {$model['filename']} ===");
                $this->line($model['content']);
                $this->newLine();
            }

            return self::SUCCESS;
        }

        $relativePath = $this->option('path') ?? config('jdl-to-filament.models_path', 'app/Models');
        $outputDir = base_path($relativePath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($models as $model) {
            $path = $outputDir.DIRECTORY_SEPARATOR.$model['filename'];
            file_put_contents($path, $model['content']);
            $this->info("Created: {$model['filename']}");
        }

        return self::SUCCESS;
    }
}
