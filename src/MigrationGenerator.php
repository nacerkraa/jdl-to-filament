<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Relationship;

class MigrationGenerator
{
    public function __construct(protected TypeMapper $typeMapper = new TypeMapper)
    {
    }

    /**
     * @param  Entity[]  $entities
     * @return array<int, array{filename: string, table: string, content: string}>
     */
    public function generate(array $entities): array
    {
        $ordered = $this->orderByDependency($entities);
        $files = [];
        $baseTimestamp = new \DateTime;

        foreach ($ordered as $index => $entity) {
            $timestamp = (clone $baseTimestamp)->modify("+{$index} seconds")->format('Y_m_d_His');
            $table = $this->tableName($entity->name);
            $files[] = [
                'filename' => "{$timestamp}_create_{$table}_table.php",
                'table' => $table,
                'content' => $this->buildMigrationContent($entity, $table),
            ];
        }

        // Pivot migrations come after all entity tables so both foreign keys
        // can safely reference their target tables.
        $pivotIndex = count($files);
        $generatedPivots = [];

        foreach ($entities as $entity) {
            foreach ($entity->relationships as $relationship) {
                if ($relationship->type !== Relationship::MANY_TO_MANY) {
                    continue;
                }

                $targetEntity = $this->findEntity($entities, $relationship->targetEntity);
                if ($targetEntity === null) {
                    throw new \InvalidArgumentException(
                        "ManyToMany relationship '{$entity->name}.{$relationship->relationshipName}' " .
                        "references unknown entity '{$relationship->targetEntity}'."
                    );
                }

                $pivotTable = Naming::pivotTableName($entity->name, $targetEntity->name);
                if (isset($generatedPivots[$pivotTable])) {
                    continue;
                }

                $timestamp = (clone $baseTimestamp)->modify("+{$pivotIndex} seconds")->format('Y_m_d_His');
                $files[] = [
                    'filename' => "{$timestamp}_create_{$pivotTable}_table.php",
                    'table' => $pivotTable,
                    'content' => $this->buildPivotMigrationContent($entity, $targetEntity, $pivotTable),
                ];
                $generatedPivots[$pivotTable] = true;
                $pivotIndex++;
            }
        }

        return $files;
    }

    protected function tableName(string $entityName): string
    {
        return Naming::tableName($entityName);
    }

    protected function columnName(string $fieldName): string
    {
        return Naming::columnName($fieldName);
    }

    /** @param Entity[] $entities */
    protected function orderByDependency(array $entities): array
    {
        $byName = [];
        foreach ($entities as $entity) {
            $byName[strtolower($entity->name)] = $entity;
        }

        $visited = [];
        $ordered = [];

        $visit = function (Entity $entity) use (&$visit, &$visited, &$ordered, $byName) {
            $key = strtolower($entity->name);
            if (isset($visited[$key])) {
                return;
            }
            $visited[$key] = true;

            foreach ($entity->relationships as $relationship) {
                if (in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                    $targetKey = strtolower($relationship->targetEntity);
                    if (isset($byName[$targetKey]) && $targetKey !== $key) {
                        $visit($byName[$targetKey]);
                    }
                }
            }

            $ordered[] = $entity;
        };

        foreach ($entities as $entity) {
            $visit($entity);
        }

        return $ordered;
    }

    /** @param Entity[] $entities */
    protected function findEntity(array $entities, string $name): ?Entity
    {
        foreach ($entities as $entity) {
            if (strcasecmp($entity->name, $name) === 0) {
                return $entity;
            }
        }

        return null;
    }

    protected function buildMigrationContent(Entity $entity, string $table): string
    {
        $lines = ["\$table->id();"];

        foreach ($entity->fields as $field) {
            $lines[] = $this->typeMapper->columnLine($field, $this->columnName($field->name));
        }

        foreach ($entity->relationships as $relationship) {
            if (in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                $fkColumn = Naming::foreignKeyColumn($relationship->relationshipName);
                $targetTable = $this->tableName($relationship->targetEntity);
                $unique = $relationship->type === Relationship::ONE_TO_ONE ? '->unique()' : '';
                $lines[] = "\$table->foreignId('{$fkColumn}')->nullable()->constrained('{$targetTable}'){$unique};";
            }
        }

        $lines[] = '$table->timestamps();';
        $indented = implode("\n", array_map(fn ($l) => "            {$l}", $lines));

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
{$indented}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;
    }

    protected function buildPivotMigrationContent(Entity $source, Entity $target, string $pivotTable): string
    {
        $sourceTable = $this->tableName($source->name);
        $targetTable = $this->tableName($target->name);
        $sourceKey = Naming::pivotForeignKey($source->name);
        $targetKey = Naming::pivotForeignKey($target->name);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$pivotTable}', function (Blueprint \$table) {
            \$table->foreignId('{$sourceKey}')->constrained('{$sourceTable}')->cascadeOnDelete();
            \$table->foreignId('{$targetKey}')->constrained('{$targetTable}')->cascadeOnDelete();
            \$table->primary(['{$sourceKey}', '{$targetKey}']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$pivotTable}');
    }
};

PHP;
    }
}
