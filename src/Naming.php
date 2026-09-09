<?php

namespace Nacer\JdlToFilament;

use Illuminate\Support\Str;

class Naming
{
    /**
     * Entity name -> Laravel table name convention (snake_case, plural).
     * "Product" -> "products", "OrderItem" -> "order_items"
     */
    public static function tableName(string $entityName): string
    {
        return Str::plural(Str::snake($entityName));
    }

    /**
     * JDL field name -> Laravel column name convention (snake_case).
     * "inStock" -> "in_stock"
     */
    public static function columnName(string $fieldName): string
    {
        return Str::snake($fieldName);
    }

    /**
     * A relationship name (JDL's relationshipName, or the inverse side's
     * otherEntityRelationshipName) -> the foreign key column it implies.
     * "category" -> "category_id"
     */
    public static function foreignKeyColumn(string $relationshipName): string
    {
        return Str::snake($relationshipName).'_id';
    }

    /**
     * Deterministic pivot table name for a ManyToMany relationship.
     * Sorting the two table names ensures both sides of a bidirectional
     * relationship resolve to exactly the same pivot table.
     * "Student" + "Course" -> "course_student"
     */
    public static function pivotTableName(string $firstEntity, string $secondEntity): string
    {
        $tables = [self::tableName($firstEntity), self::tableName($secondEntity)];
        sort($tables, SORT_STRING);

        return implode('_', array_map(fn (string $table) => Str::singular($table), $tables));
    }

    /**
     * Foreign key column used by a pivot table.
     * "OrderItem" -> "order_item_id"
     */
    public static function pivotForeignKey(string $entityName): string
    {
        return self::foreignKeyColumn(Str::singular(Str::snake($entityName)));
    }
}
