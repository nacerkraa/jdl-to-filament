<?php

namespace Nacer\JdlToFilament\Models;

class Relationship
{
    public const MANY_TO_ONE = 'ManyToOne';
    public const ONE_TO_MANY = 'OneToMany';
    public const ONE_TO_ONE = 'OneToOne';
    public const MANY_TO_MANY = 'ManyToMany';

    public function __construct(
        public readonly string $type,
        public readonly string $targetEntity,
        public readonly string $relationshipName,
        public readonly ?string $inverseName = null,
        public readonly bool $required = false,
        public readonly bool $builtInEntity = false,
    ) {
    }

    public static function normalizeType(string $jdlRelationshipType): string
    {
        return match (strtolower($jdlRelationshipType)) {
            'many-to-one' => self::MANY_TO_ONE,
            'one-to-many' => self::ONE_TO_MANY,
            'one-to-one' => self::ONE_TO_ONE,
            'many-to-many' => self::MANY_TO_MANY,
            default => throw new \InvalidArgumentException("Unknown JDL relationship type: {$jdlRelationshipType}"),
        };
    }
}
