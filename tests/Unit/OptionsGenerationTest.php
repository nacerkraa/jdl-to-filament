<?php

namespace Nacer\JdlToFilament\Tests\Unit;

use Nacer\JdlToFilament\DtoGenerator;
use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\FilamentResourceGenerator;
use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Field;
use Nacer\JdlToFilament\ServiceGenerator;
use PHPUnit\Framework\TestCase;

class OptionsGenerationTest extends TestCase
{
    public function test_mapper_captures_jdl_options(): void
    {
        $entities = (new EntityMapper)->map([
            [
                'name' => 'Product',
                'fields' => [
                    ['fieldName' => 'name', 'fieldType' => 'String', 'fieldValidateRules' => []],
                ],
                'relationships' => [],
                'pagination' => 'pagination',
                'dto' => 'mapstruct',
                'service' => 'serviceClass',
                'jpaMetamodelFiltering' => true,
            ],
        ]);

        self::assertTrue($entities[0]->paginated);
        self::assertSame('mapstruct', $entities[0]->dtoType);
        self::assertSame('serviceClass', $entities[0]->serviceType);
        self::assertTrue($entities[0]->filterable);
    }

    public function test_dto_and_service_generators_only_generate_opted_in_entities(): void
    {
        $enabled = new Entity('Product', [new Field('name', 'String')], [], false, 'mapstruct', 'serviceClass');
        $plain = new Entity('Category', [new Field('name', 'String')]);

        self::assertCount(1, (new DtoGenerator)->generate([$enabled, $plain]));
        self::assertCount(1, (new ServiceGenerator)->generate([$enabled, $plain]));
    }

    public function test_filament_filter_and_pagination_are_generated_for_filterable_entities(): void
    {
        $entity = new Entity(
            'Product',
            [new Field('name', 'String'), new Field('active', 'Boolean')],
            [],
            true,
            null,
            null,
            true,
        );

        $resource = (new FilamentResourceGenerator)->generate([$entity])[0]['content'];

        self::assertStringContainsString("TernaryFilter::make('active')", $resource);
        self::assertStringContainsString('paginationPageOptions([10, 25, 50, 100])', $resource);
    }
}
