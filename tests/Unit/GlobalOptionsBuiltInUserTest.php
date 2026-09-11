<?php

namespace Nacer\JdlToFilament\Tests\Unit;

use Nacer\JdlToFilament\EntityMapper;
use Nacer\JdlToFilament\MigrationGenerator;
use Nacer\JdlToFilament\ModelGenerator;
use PHPUnit\Framework\TestCase;

class GlobalOptionsBuiltInUserTest extends TestCase
{
    public function test_global_entity_options_are_applied_to_all_entities(): void
    {
        $entities = (new EntityMapper)->map([
            [
                'name' => 'Post',
                'fields' => [],
                'relationships' => [],
                'pagination' => 'pagination',
                'jpaMetamodelFiltering' => true,
            ],
            [
                'name' => 'Comment',
                'fields' => [],
                'relationships' => [],
                'pagination' => 'no',
                'jpaMetamodelFiltering' => false,
            ],
        ]);

        self::assertTrue($entities[0]->paginated);
        self::assertTrue($entities[1]->paginated);
        self::assertTrue($entities[0]->filterable);
        self::assertTrue($entities[1]->filterable);
    }

    public function test_built_in_user_relationship_is_required_and_marked(): void
    {
        $entities = (new EntityMapper)->map([
            [
                'name' => 'Post',
                'fields' => [],
                'relationships' => [[
                    'relationshipType' => 'many-to-one',
                    'otherEntityName' => 'user',
                    'relationshipName' => 'author',
                    'relationshipValidateRules' => ['required'],
                    'relationshipWithBuiltInEntity' => true,
                ]],
            ],
        ]);

        $relationship = $entities[0]->relationships[0];
        self::assertTrue($relationship->required);
        self::assertTrue($relationship->builtInEntity);
        self::assertSame('user', $relationship->targetEntity);
    }

    public function test_built_in_user_generates_fk_and_model_relationship_without_user_model(): void
    {
        $entities = (new EntityMapper)->map([
            [
                'name' => 'Post',
                'fields' => [],
                'relationships' => [[
                    'relationshipType' => 'many-to-one',
                    'otherEntityName' => 'User',
                    'relationshipName' => 'author',
                    'relationshipValidateRules' => ['required'],
                    'relationshipWithBuiltInEntity' => true,
                ]],
            ],
        ]);

        $migration = (new MigrationGenerator)->generate($entities)[0]['content'];
        self::assertStringContainsString("foreignId('author_id')->constrained('users')", $migration);
        self::assertStringNotContainsString("author_id')->nullable()", $migration);

        $modelFiles = (new ModelGenerator)->generate($entities);
        self::assertCount(1, $modelFiles);
        self::assertStringContainsString('use App\\Models\\User;', $modelFiles[0]['content']);
        self::assertStringContainsString("belongsTo(User::class, 'author_id')", $modelFiles[0]['content']);
    }
}
