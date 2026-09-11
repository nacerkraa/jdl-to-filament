<?php

namespace Nacer\JdlToFilament\Models;

class Entity
{
    /**
     * @param  string  $name  Entity name, e.g. "Product"
     * @param  Field[]  $fields
     * @param  Relationship[]  $relationships
     * @param  bool  $paginated  Whether JDL pagination was enabled
     * @param  string|null  $dtoType  JDL dto strategy, if enabled
     * @param  string|null  $serviceType  JDL service strategy, if enabled
     * @param  bool  $filterable  Whether JDL filtering was enabled
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields = [],
        public readonly array $relationships = [],
        public readonly bool $paginated = false,
        public readonly ?string $dtoType = null,
        public readonly ?string $serviceType = null,
        public readonly bool $filterable = false,
    ) {
    }
}
