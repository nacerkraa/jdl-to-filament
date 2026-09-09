<?php

namespace Nacer\JdlToFilament\Console\Commands;

use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\JdlParser;
use Nacer\JdlToFilament\Models\Entity;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    protected $signature = 'jdl:generate {file : Path to the .jdl file} {--raw : Show raw JDL JSON instead of the mapped Entity objects}';

    protected $description = 'Parse a JDL file and show the mapped entities (step 2: internal model, no code generation yet)';

    public function handle(JdlParser $parser, EntityMapper $mapper): int
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

        if ($this->option('raw')) {
            $this->line(json_encode($result['data'], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $entities = $mapper->map($rawEntities);

        $this->info('Mapped entities:');
        $this->newLine();

        foreach ($entities as $entity) {
            $this->displayEntity($entity);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function displayEntity(Entity $entity): void
    {
        $this->line("<fg=cyan;options=bold>{$entity->name}</>");

        foreach ($entity->fields as $field) {
            $flags = [];
            if ($field->required) {
                $flags[] = 'required';
            }
            if ($field->isEnum()) {
                $flags[] = "enum({$field->enumValues})";
            }
            $flagsStr = $flags ? ' ['.implode(', ', $flags).']' : '';

            $this->line("  - {$field->name}: {$field->type}{$flagsStr}");
        }

        foreach ($entity->relationships as $relationship) {
            $this->line(
                "  -> {$relationship->type} {$relationship->targetEntity} (as \"{$relationship->relationshipName}\")"
            );
        }
    }
}

