<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Relationship;

class ModelGenerator
{
    public function __construct(protected TypeMapper $typeMapper = new TypeMapper)
    {
    }

    /**
     * @param  Entity[]  $entities
     * @return array<int, array{filename: string, className: string, content: string}>
     */
    public function generate(array $entities): array
    {
        $files = [];

        foreach ($entities as $entity) {
            $files[] = [
                'filename' => "{$entity->name}.php",
                'className' => $entity->name,
                'content' => $this->buildModelContent($entity),
            ];
        }

        return $files;
    }

    protected function buildModelContent(Entity $entity): string
    {
        $fillable = [];
        $casts = [];

        foreach ($entity->fields as $field) {
            $column = Naming::columnName($field->name);
            $fillable[] = $column;

            $cast = $this->typeMapper->castFor($field);
            if ($cast !== null) {
                $casts[$column] = $cast;
            }
        }

        $relationMethods = [];
        $targetImports = [];
        $relationImportNames = [];

        foreach ($entity->relationships as $relationship) {
            $built = $this->buildRelationshipMethod($entity->name, $relationship);

            if ($built === null) {
                // Defensive only - all four JDL relationship types are
                // handled below, this should be unreachable.
                continue;
            }

            $relationMethods[] = $built['method'];
            $targetImports[$relationship->targetEntity] = true;
            $relationImportNames[$built['returnType']] = true;

            if (in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                $fillable[] = Naming::foreignKeyColumn($relationship->relationshipName);
            }
            // ManyToOne/OneToOne add a fillable FK column above. OneToMany
            // and ManyToMany add no column to THIS entity's own table -
            // OneToMany's FK lives on the other entity, ManyToMany's pair
            // of FKs live on a separate pivot table.
        }

        return $this->render(
            className: $entity->name,
            fillable: $fillable,
            casts: $casts,
            relationMethods: $relationMethods,
            targetClasses: array_keys($targetImports),
            relationReturnTypes: array_keys($relationImportNames),
        );
    }

    /**
     * @return array{method: string, returnType: string}|null
     */
    protected function buildRelationshipMethod(string $ownEntityName, Relationship $relationship): ?array
    {
        // JDL lowercases otherEntityName; Laravel model class names are
        // studly case. JDL entity names are typically already valid
        // class names once the first letter is capitalized.
        $targetClass = ucfirst($relationship->targetEntity);

        return match ($relationship->type) {
            Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE => [
                'returnType' => 'BelongsTo',
                'method' => $this->belongsToMethod($relationship, $targetClass),
            ],
            Relationship::ONE_TO_MANY => [
                'returnType' => 'HasMany',
                'method' => $this->hasManyMethod($relationship, $targetClass),
            ],
            Relationship::MANY_TO_MANY => [
                'returnType' => 'BelongsToMany',
                'method' => $this->belongsToManyMethod($ownEntityName, $relationship, $targetClass),
            ],
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

        // The foreign key lives on the OTHER entity's table (the "many"
        // side). Its base name is whatever that side called this
        // relationship - JDL gives us that as otherEntityRelationshipName
        // (mapped to inverseName). Falling back to this entity's own
        // name matches Eloquent's default convention, for the rare case
        // where JDL didn't provide an inverse name.
        $fkBase = $relationship->inverseName ?? $relationship->targetEntity;
        $fkColumn = Naming::foreignKeyColumn($fkBase);

        return <<<PHP
    public function {$methodName}(): HasMany
    {
        return \$this->hasMany({$targetClass}::class, '{$fkColumn}');
    }
PHP;
    }

    protected function belongsToManyMethod(string $ownEntityName, Relationship $relationship, string $targetClass): string
    {
        $methodName = $relationship->relationshipName;
        $pivotTable = Naming::pivotTableName($ownEntityName, $relationship->targetEntity);
        $ownColumn = Naming::pivotForeignKey($ownEntityName);
        $targetColumn = Naming::pivotForeignKey($relationship->targetEntity);

        return <<<PHP
    public function {$methodName}(): BelongsToMany
    {
        return \$this->belongsToMany({$targetClass}::class, '{$pivotTable}', '{$ownColumn}', '{$targetColumn}');
    }
PHP;
    }

    /**
     * @param  string[]  $fillable
     * @param  array<string, string>  $casts
     * @param  string[]  $relationMethods
     * @param  string[]  $targetClasses
     * @param  string[]  $relationReturnTypes
     */
    protected function render(
        string $className,
        array $fillable,
        array $casts,
        array $relationMethods,
        array $targetClasses,
        array $relationReturnTypes,
    ): string {
        $fillable = array_values(array_unique($fillable));

        $fillableLines = implode("\n", array_map(
            fn ($c) => "        '{$c}',",
            $fillable
        ));

        $castsBlock = '';
        if (! empty($casts)) {
            $castsLines = implode("\n", array_map(
                fn ($col, $cast) => "        '{$col}' => '{$cast}',",
                array_keys($casts),
                array_values($casts)
            ));
            $castsBlock = "\n\n    protected \$casts = [\n{$castsLines}\n    ];";
        }

        $relationImportLines = implode("\n", array_map(
            fn ($t) => "use Illuminate\\Database\\Eloquent\\Relations\\{$t};",
            $relationReturnTypes
        ));
        if ($relationImportLines !== '') {
            $relationImportLines = "\n".$relationImportLines;
        }

        $targetImportLines = implode("\n", array_map(
            fn ($t) => 'use App\\Models\\'.ucfirst($t).';',
            array_filter($targetClasses, fn ($t) => ucfirst($t) !== $className) // avoid self-import
        ));
        if ($targetImportLines !== '') {
            $targetImportLines = "\n".$targetImportLines;
        }

        $methodsBlock = '';
        if (! empty($relationMethods)) {
            $methodsBlock = "\n\n".implode("\n\n", $relationMethods);
        }

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
