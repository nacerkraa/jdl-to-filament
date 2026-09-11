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
        {--dry : Print the generated DTOs instead of writing files}
        {--path= : Directory (relative to the project root) to write DTOs into. Defaults to config(\'jdl-to-filament.dtos_path\')}';

    protected $description = 'Generate Laravel API Resource (DTO) files for entities with a JDL "dto" option set';

    public function handle(JdlParser $parser, EntityMapper $mapper, DtoGenerator $generator): int
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
        $dtos = $generator->generate($entities);

        if (empty($dtos)) {
            $this->warn('No entities have a "dto" option set in this JDL file (e.g. "dto Product with mapstruct") - nothing to generate.');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            foreach ($dtos as $dto) {
                $this->line("=== {$dto['filename']} ===");
                $this->line($dto['content']);
                $this->newLine();
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
