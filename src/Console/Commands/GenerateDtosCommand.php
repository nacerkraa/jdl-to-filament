<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Illuminate\Console\Command;
use Nacer\JdlToFilament\DtoGenerator;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\JdlParser;

class GenerateDtosCommand extends Command
{
    protected $signature = 'jdl:dtos
        {file : Path to the .jdl file}
        {--dry : Print generated DTOs instead of writing files}
        {--path= : Output directory, relative to the project root}';

    protected $description = 'Generate Laravel API Resource files for entities with a JDL dto option';

    public function handle(JdlParser $parser, EntityMapper $mapper, DtoGenerator $generator): int
    {
        $result = $parser->parse($this->argument('file'));
        if (! $result['success']) {
            $this->error('JDL parsing failed:');
            $this->line($result['error']);
            return self::FAILURE;
        }

        $entities = $mapper->map($result['data']['entities'] ?? []);
        $dtos = $generator->generate($entities);
        if ($this->option('dry')) {
            foreach ($dtos as $dto) {
                $this->line("=== {$dto['filename']} ===");
                $this->line($dto['content']);
            }
            return self::SUCCESS;
        }

        $outputDir = base_path($this->option('path') ?? config('jdl-to-filament.dtos_path', 'app/Http/Resources'));
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        foreach ($dtos as $dto) {
            file_put_contents($outputDir.DIRECTORY_SEPARATOR.$dto['filename'], $dto['content']);
            $this->info("Created: {$dto['filename']}");
        }
        return self::SUCCESS;
    }
}
