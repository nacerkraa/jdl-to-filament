<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Field;
use Nacer\JdlToFilament\Models\Relationship;

class EntityMapper
{
    /** @param array $rawEntities The entities array from JdlParser::parse() */
    public function map(array $rawEntities): array
    {
        $globals = $this->globalOptions($rawEntities);

        return array_map(fn (array $rawEntity) => $this->mapEntity($rawEntity, $globals), $rawEntities);
    }

    protected function mapEntity(array $raw, array $globals): Entity
    {
        $fields = array_map(fn (array $rawField) => $this->mapField($rawField), $raw['fields'] ?? []);
        $relationships = array_map(fn (array $rawRelationship) => $this->mapRelationship($rawRelationship), $raw['relationships'] ?? []);

        $dto = $raw['dto'] ?? $globals['dto'];
        $service = $raw['service'] ?? $globals['service'];
        $pagination = $raw['pagination'] ?? $globals['pagination'];
        $filterable = array_key_exists('jpaMetamodelFiltering', $raw)
            ? (bool) $raw['jpaMetamodelFiltering']
            : $globals['filterable'];

        return new Entity(
            name: $raw['name'],
            fields: $fields,
            relationships: $relationships,
            paginated: $pagination !== 'no',
            dtoType: $dto === 'no' ? null : $dto,
            serviceType: $service === 'no' ? null : $service,
            filterable: $filterable,
        );
    }

    protected function globalOptions(array $rawEntities): array
    {
        $globals = [
            'pagination' => 'no',
            'dto' => 'no',
            'service' => 'no',
            'filterable' => false,
        ];

        foreach ($rawEntities as $raw) {
            foreach (['pagination', 'dto', 'service'] as $option) {
                if (array_key_exists($option, $raw) && $raw[$option] !== 'no') {
                    $globals[$option] = $raw[$option];
                }
            }

            if (array_key_exists('jpaMetamodelFiltering', $raw) && $raw['jpaMetamodelFiltering']) {
                $globals['filterable'] = true;
            }
        }

        return $globals;
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
            required: (bool) ($raw['required'] ?? false),
            builtInEntity: (bool) ($raw['relationshipWithBuiltInEntity'] ?? $raw['builtInEntity'] ?? false),
        );
    }
}
