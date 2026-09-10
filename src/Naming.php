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
     * Laravel's own default ManyToMany pivot table naming convention:
     * the two entities' singular snake_case names, sorted alphabetically,
     * joined with an underscore. "Post" + "Tag" -> "post_tag". Matches
     * Eloquent's own internal default guess, so belongsToMany() calls
     * built from this need no explicit table override to work - we still
     * pass it explicitly in generated code for clarity, not because it's
     * required.
     */
    public static function pivotTableName(string $entityA, string $entityB): string
    {
        $names = [Str::snake($entityA), Str::snake($entityB)];
        sort($names);

        return implode('_', $names);
    }

    /**
     * The pivot column referring to one entity's own table.
     * "Post" -> "post_id"
     */
    public static function pivotForeignKey(string $entityName): string
    {
        return Str::snake($entityName).'_id';
    }
}
