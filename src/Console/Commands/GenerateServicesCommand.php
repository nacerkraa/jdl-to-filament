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
        {--dry : Print generated services instead of writing files}
        {--path= : Output directory, relative to the project root}';

    protected $description = 'Generate Service classes for entities with a JDL service option';

    public function handle(JdlParser $parser, EntityMapper $mapper, ServiceGenerator $generator): int
    {
        $result = $parser->parse($this->argument('file'));
        if (! $result['success']) {
            $this->error('JDL parsing failed:');
            $this->line($result['error']);
            return self::FAILURE;
        }

        $entities = $mapper->map($result['data']['entities'] ?? []);
        $services = $generator->generate($entities);
        if ($this->option('dry')) {
            foreach ($services as $service) {
                $this->line("=== {$service['filename']} ===");
                $this->line($service['content']);
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
