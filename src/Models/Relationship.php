<?php

namespace Nacer\JdlToFilament\Models;

class Relationship
{
    public const MANY_TO_ONE = 'ManyToOne';

    public const ONE_TO_MANY = 'OneToMany';

    public const ONE_TO_ONE = 'OneToOne';

    public const MANY_TO_MANY = 'ManyToMany';

    /**
     * @param  string  $type  Normalized type, one of the class constants above
     * @param  string  $targetEntity  The related entity's name, e.g. "Category"
     * @param  string  $relationshipName  The name used for the relationship/field, e.g. "category"
     * @param  string|null  $inverseName  The name JDL gave the OTHER side of a bidirectional
     *                                    relationship (its "otherEntityRelationshipName"). For a
     *                                    OneToMany relationship, this is what the ManyToOne side
     *                                    called it - and therefore the base of that side's foreign
     *                                    key column name (e.g. "author" -> "author_id" on posts).
     */
    public function __construct(
        public readonly string $type,
        public readonly string $targetEntity,
        public readonly string $relationshipName,
        public readonly ?string $inverseName = null,
    ) {
    }

    /**
     * Turns JDL's raw relationship type ("many-to-one", "one-to-many", ...)
     * into a clean, generator-friendly string ("ManyToOne", "OneToMany", ...).
     */
    public static function normalizeType(string $jdlRelationshipType): string
    {
        return match (strtolower($jdlRelationshipType)) {
            'many-to-one' => self::MANY_TO_ONE,
            'one-to-many' => self::ONE_TO_MANY,
            'one-to-one' => self::ONE_TO_ONE,
            'many-to-many' => self::MANY_TO_MANY,
            default => throw new \InvalidArgumentException(
                "Unknown JDL relationship type: {$jdlRelationshipType}"
            ),
        };
    }
}
