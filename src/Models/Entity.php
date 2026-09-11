<?php

namespace Nacer\JdlToFilament\Models;

class Entity
{
    /**
     * @param  string  $name  Entity name, e.g. "Product"
     * @param  Field[]  $fields
     * @param  Relationship[]  $relationships
     * @param  bool  $paginated  From JDL's "paginate" option - true unless explicitly "no"
     * @param  string|null  $dtoType  From JDL's "dto" option, e.g. "mapstruct", or null if "no"/unset
     * @param  string|null  $serviceType  From JDL's "service" option, e.g. "serviceClass"/"serviceImpl", or null if "no"/unset
     * @param  bool  $filterable  From JDL's "filter" option (exported as jpaMetamodelFiltering)
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
