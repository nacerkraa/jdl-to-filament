<?php

namespace Nacer\JdlToFilament;

use Illuminate\Support\Str;

class Naming
{
    public static function tableName(string $entityName): string
    {
        return Str::plural(Str::snake($entityName));
    }

    public static function columnName(string $fieldName): string
    {
        return Str::snake($fieldName);
    }

    public static function foreignKeyColumn(string $relationshipName): string
    {
        return Str::snake($relationshipName).'_id';
    }

    /**
     * Deterministic pivot table name for a ManyToMany relationship.
     * Sorting the two singular table names makes both directions agree.
     */
    public static function pivotTableName(string $firstEntity, string $secondEntity): string
    {
        $tables = [self::tableName($firstEntity), self::tableName($secondEntity)];
        sort($tables, SORT_STRING);

        return implode('_', array_map(
            fn (string $table) => Str::singular($table),
            $tables
        ));
    }

    public static function pivotForeignKey(string $entityName): string
    {
        return self::foreignKeyColumn(Str::singular(Str::snake($entityName)));
    }
}
