<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\JdlParser;
use Nacer\JdlToFilament\ServiceGenerator;

class GenerateServicesCommand extends Command
{
    protected $signature = 'jdl:services
        {file : Path to the .jdl file}
        {--dry : Print the generated services instead of writing files}
        {--path= : Directory (relative to the project root) to write services into. Defaults to config(\'jdl-to-filament.services_path\')}';

    protected $description = 'Generate Service class files for entities with a JDL "service" option set';

    public function handle(JdlParser $parser, EntityMapper $mapper, ServiceGenerator $generator): int
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
        $services = $generator->generate($entities);

        if (empty($services)) {
            $this->warn('No entities have a "service" option set in this JDL file (e.g. "service Product with serviceClass") - nothing to generate.');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            foreach ($services as $service) {
                $this->line("=== {$service['filename']} ===");
                $this->line($service['content']);
                $this->newLine();
            }

            return self::SUCCESS;
        }

        $outputDir = base_path($this->option('path') ?? config('jdl-to-filament.services_path', 'app/Services'));

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($services as $service) {
            file_put_contents($outputDir.DIRECTORY_SEPARATOR.$service['filename'], $service['content']);
            $this->info("Created: {$service['filename']}");
        }

        return self::SUCCESS;
    }
}
