<?php

namespace Nacer\JdlToFilament\Models;

class Field
{
    /**
     * @param  string  $name  Field name, e.g. "price"
     * @param  string  $type  Raw JDL type, e.g. "String", "BigDecimal", "Boolean", or "byte[]" for blobs
     * @param  bool  $required  Whether the "required" validation was set
     * @param  array  $validations  All JDL validation rule names, e.g. ["required", "minlength"]
     * @param  string|null  $enumValues  Raw comma-separated enum values, if this field is an Enum type
     * @param  string|null  $blobContentType  "text", "image", or "any" - only set when $type is "byte[]".
     *                                        JDL exports TextBlob/ImageBlob/Blob/AnyBlob this way rather
     *                                        than as a distinct fieldType string, discovered by testing.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $required = false,
        public readonly array $validations = [],
        public readonly ?string $enumValues = null,
        public readonly ?string $blobContentType = null,
    ) {
    }

    public function isEnum(): bool
    {
        return $this->enumValues !== null;
    }

    public function isBlob(): bool
    {
        return $this->blobContentType !== null;
    }
}
