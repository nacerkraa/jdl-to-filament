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
        $index = 0;

        foreach ($ordered as $entity) {
            $timestamp = (clone $baseTimestamp)->modify("+{$index} seconds")->format('Y_m_d_His');
            $index++;
            $table = $this->tableName($entity->name);
            $filename = "{$timestamp}_create_{$table}_table.php";

            $files[] = [
                'filename' => $filename,
                'table' => $table,
                'content' => $this->buildMigrationContent($entity, $table),
            ];
        }

        // Pivot tables reference two entity tables via foreign keys, so
        // they must migrate after ALL entity tables exist. ManyToMany is
        // exported symmetrically by JDL (both sides get a relationship
        // entry), so dedupe by the pivot table name itself rather than
        // by which side "owns" it.
        $seenPivots = [];

        foreach ($ordered as $entity) {
            foreach ($entity->relationships as $relationship) {
                if ($relationship->type !== Relationship::MANY_TO_MANY) {
                    continue;
                }

                $pivotTable = Naming::pivotTableName($entity->name, $relationship->targetEntity);

                if (isset($seenPivots[$pivotTable])) {
                    continue;
                }
                $seenPivots[$pivotTable] = true;

                $timestamp = (clone $baseTimestamp)->modify("+{$index} seconds")->format('Y_m_d_His');
                $index++;

                $files[] = [
                    'filename' => "{$timestamp}_create_{$pivotTable}_table.php",
                    'table' => $pivotTable,
                    'content' => $this->buildPivotMigrationContent($entity->name, $relationship->targetEntity, $pivotTable),
                ];
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

    /**
     * Orders entities so a ManyToOne/OneToOne target is migrated before
     * the entity that references it (simple depth-first topological
     * sort). Falls back gracefully on a cycle instead of throwing -
     * a JDL model with a relationship cycle is still valid; the
     * resulting migration order just won't be perfectly clean, which
     * is a refinement for later rather than a reason to fail here.
     *
     * @param  Entity[]  $entities
     * @return Entity[]
     */
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
            // OneToMany is the inverse side - no column belongs on this table.
            // ManyToMany gets its own separate pivot-table migration,
            // built in generate() / buildPivotMigrationContent() below -
            // no column belongs on this entity's own table for it either.
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

    /**
     * Builds a pivot table migration for a ManyToMany relationship
     * between two entities - just the two foreign keys and a composite
     * primary key. No id() column and no timestamps() by default, matching
     * a typical hand-written Laravel pivot table; add ->timestamps() and/or
     * extra withPivot() columns yourself if the relationship needs them.
     */
    protected function buildPivotMigrationContent(string $entityA, string $entityB, string $pivotTable): string
    {
        $colA = Naming::pivotForeignKey($entityA);
        $colB = Naming::pivotForeignKey($entityB);
        $tableA = $this->tableName($entityA);
        $tableB = $this->tableName($entityB);

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
            \$table->foreignId('{$colA}')->constrained('{$tableA}')->cascadeOnDelete();
            \$table->foreignId('{$colB}')->constrained('{$tableB}')->cascadeOnDelete();
            \$table->primary(['{$colA}', '{$colB}']);
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
