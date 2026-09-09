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
}
