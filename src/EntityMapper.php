<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Field;
use Nacer\JdlToFilament\Models\Relationship;

class EntityMapper
{
    /**
     * @param  array  $rawEntities  The "entities" array from JdlParser::parse()'s data
     * @return Entity[]
     */
    public function map(array $rawEntities): array
    {
        return array_map(
            fn (array $rawEntity) => $this->mapEntity($rawEntity),
            $rawEntities
        );
    }

    protected function mapEntity(array $raw): Entity
    {
        $fields = array_map(
            fn (array $rawField) => $this->mapField($rawField),
            $raw['fields'] ?? []
        );

        $relationships = array_map(
            fn (array $rawRelationship) => $this->mapRelationship($rawRelationship),
            $raw['relationships'] ?? []
        );

        return new Entity(
            name: $raw['name'],
            fields: $fields,
            relationships: $relationships,
        );
    }

    protected function mapField(array $raw): Field
    {
        $validations = $raw['fieldValidateRules'] ?? [];

        return new Field(
            name: $raw['fieldName'],
            type: $raw['fieldType'],
            required: in_array('required', $validations, true),
            validations: $validations,
            enumValues: $raw['fieldValues'] ?? null,
            blobContentType: $raw['fieldTypeBlobContent'] ?? null,
        );
    }

    protected function mapRelationship(array $raw): Relationship
    {
        return new Relationship(
            type: Relationship::normalizeType($raw['relationshipType']),
            targetEntity: $raw['otherEntityName'],
            relationshipName: $raw['relationshipName'],
            inverseName: $raw['otherEntityRelationshipName'] ?? null,
        );
    }
}
