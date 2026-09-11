<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Relationship;

class DtoGenerator
{
    /**
     * Generate Laravel API Resources only for entities with a JDL dto option.
     *
     * @param  Entity[]  $entities
     * @return array<int, array{filename: string, className: string, content: string}>
     */
    public function generate(array $entities): array
    {
        $byName = [];
        foreach ($entities as $entity) {
            $byName[strtolower($entity->name)] = $entity;
        }

        $files = [];
        foreach ($entities as $entity) {
            if ($entity->dtoType === null) {
                continue;
            }

            $files[] = [
                'filename' => "{$entity->name}Resource.php",
                'className' => $entity->name,
                'content' => $this->buildContent($entity, $byName),
            ];
        }

        return $files;
    }

    /** @param array<string, Entity> $byName */
    protected function buildContent(Entity $entity, array $byName): string
    {
        $lines = ["'id' => \$this->id,"];

        foreach ($entity->fields as $field) {
            $column = Naming::columnName($field->name);
            $lines[] = "'{$column}' => \$this->{$column},";
        }

        $targetImports = [];

        foreach ($entity->relationships as $relationship) {
            $target = $byName[strtolower($relationship->targetEntity)] ?? null;
            $targetClass = ucfirst($relationship->targetEntity);
            $hasTargetDto = $target !== null && $target->dtoType !== null;

            if (in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                $name = $relationship->relationshipName;

                if ($hasTargetDto) {
                    $lines[] = "'{$name}' => new {$targetClass}Resource(\$this->whenLoaded('{$name}')),";
                    $targetImports[$targetClass] = true;
                } else {
                    $fkColumn = Naming::foreignKeyColumn($relationship->relationshipName);
                    $lines[] = "'{$fkColumn}' => \$this->{$fkColumn},";
                }

                continue;
            }

            if (in_array($relationship->type, [Relationship::ONE_TO_MANY, Relationship::MANY_TO_MANY], true) && $hasTargetDto) {
                $name = $relationship->relationshipName;
                $lines[] = "'{$name}' => {$targetClass}Resource::collection(\$this->whenLoaded('{$name}')),";
                $targetImports[$targetClass] = true;
            }
        }

        $lines[] = "'created_at' => \$this->created_at,";
        $lines[] = "'updated_at' => \$this->updated_at,";

        $body = implode("\n", array_map(fn ($line) => "            {$line}", $lines));
        $importLines = implode("\n", array_map(
            fn ($class) => "use App\\Http\\Resources\\{$class}Resource;",
            array_keys($targetImports)
        ));
        if ($importLines !== '') {
            $importLines = "\n{$importLines}";
        }

        $name = $entity->name;

        return <<<PHP
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;{$importLines}

class {$name}Resource extends JsonResource
{
    public function toArray(Request \$request): array
    {
        return [
{$body}
        ];
    }
}

PHP;
    }
}
