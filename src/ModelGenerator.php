<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Relationship;

class ModelGenerator
{
    public function __construct(protected TypeMapper $typeMapper = new TypeMapper)
    {
    }

    /** @param Entity[] $entities */
    public function generate(array $entities): array
    {
        $files = [];
        foreach ($entities as $entity) {
            $files[] = ['filename' => "{$entity->name}.php", 'className' => $entity->name, 'content' => $this->buildModelContent($entity)];
        }
        return $files;
    }

    protected function buildModelContent(Entity $entity): string
    {
        $fillable = [];
        $casts = [];
        $relationMethods = [];
        $targetImports = [];
        $relationImportNames = [];

        foreach ($entity->fields as $field) {
            $column = Naming::columnName($field->name);
            $fillable[] = $column;
            $cast = $this->typeMapper->castFor($field);
            if ($cast !== null) {
                $casts[$column] = $cast;
            }
        }

        foreach ($entity->relationships as $relationship) {
            $built = $this->buildRelationshipMethod($entity, $relationship);
            if ($built === null) {
                continue;
            }
            $relationMethods[] = $built['method'];
            $targetImports[$relationship->targetEntity] = true;
            $relationImportNames[$built['returnType']] = true;
            if (in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                $fillable[] = Naming::foreignKeyColumn($relationship->relationshipName);
            }
        }

        return $this->render($entity->name, $fillable, $casts, $relationMethods, array_keys($targetImports), array_keys($relationImportNames));
    }

    /** @return array{method: string, returnType: string}|null */
    protected function buildRelationshipMethod(Entity $owner, Relationship $relationship): ?array
    {
        $targetClass = ucfirst($relationship->targetEntity);
        return match ($relationship->type) {
            Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE => ['returnType' => 'BelongsTo', 'method' => $this->belongsToMethod($relationship, $targetClass)],
            Relationship::ONE_TO_MANY => ['returnType' => 'HasMany', 'method' => $this->hasManyMethod($relationship, $targetClass)],
            Relationship::MANY_TO_MANY => ['returnType' => 'BelongsToMany', 'method' => $this->belongsToManyMethod($owner, $relationship, $targetClass)],
            default => null,
        };
    }

    protected function belongsToMethod(Relationship $relationship, string $targetClass): string
    {
        $methodName = $relationship->relationshipName;
        $fkColumn = Naming::foreignKeyColumn($relationship->relationshipName);
        return <<<PHP
    public function {$methodName}(): BelongsTo
    {
        return \$this->belongsTo({$targetClass}::class, '{$fkColumn}');
    }
PHP;
    }

    protected function hasManyMethod(Relationship $relationship, string $targetClass): string
    {
        $methodName = $relationship->relationshipName;
        $fkBase = $relationship->inverseName ?? $relationship->targetEntity;
        $fkColumn = Naming::foreignKeyColumn($fkBase);
        return <<<PHP
    public function {$methodName}(): HasMany
    {
        return \$this->hasMany({$targetClass}::class, '{$fkColumn}');
    }
PHP;
    }

    protected function belongsToManyMethod(Entity $owner, Relationship $relationship, string $targetClass): string
    {
        $methodName = $relationship->relationshipName;
        $pivotTable = Naming::pivotTableName($owner->name, $relationship->targetEntity);
        $ownColumn = Naming::pivotForeignKey($owner->name);
        $targetColumn = Naming::pivotForeignKey($relationship->targetEntity);
        return <<<PHP
    public function {$methodName}(): BelongsToMany
    {
        return \$this->belongsToMany({$targetClass}::class, '{$pivotTable}', '{$ownColumn}', '{$targetColumn}');
    }
PHP;
    }

    protected function render(string $className, array $fillable, array $casts, array $relationMethods, array $targetClasses, array $relationReturnTypes): string
    {
        $fillable = array_values(array_unique($fillable));
        $fillableLines = implode("\n", array_map(fn ($c) => "        '{$c}',", $fillable));
        $castsBlock = '';
        if (! empty($casts)) {
            $castsLines = implode("\n", array_map(fn ($col, $cast) => "        '{$col}' => '{$cast}',", array_keys($casts), array_values($casts)));
            $castsBlock = "\n\n    protected \$casts = [\n{$castsLines}\n    ];";
        }
        $relationImportLines = implode("\n", array_map(fn ($t) => "use Illuminate\\Database\\Eloquent\\Relations\\{$t};", $relationReturnTypes));
        if ($relationImportLines !== '') $relationImportLines = "\n".$relationImportLines;
        $targetImportLines = implode("\n", array_map(fn ($t) => 'use App\\Models\\'.ucfirst($t).';', array_filter($targetClasses, fn ($t) => ucfirst($t) !== $className)));
        if ($targetImportLines !== '') $targetImportLines = "\n".$targetImportLines;
        $methodsBlock = empty($relationMethods) ? '' : "\n\n".implode("\n\n", $relationMethods);
        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;{$relationImportLines}{$targetImportLines}

class {$className} extends Model
{
    use HasFactory;

    protected \$fillable = [
{$fillableLines}
    ];{$castsBlock}{$methodsBlock}
}

PHP;
    }
}
