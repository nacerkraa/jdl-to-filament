<?php

namespace Nacer\JdlToFilament\Models;

class Entity
{
    /**
     * @param  string  $name  Entity name, e.g. "Product"
     * @param  Field[]  $fields
     * @param  Relationship[]  $relationships
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields = [],
        public readonly array $relationships = [],
    ) {
    }
}
