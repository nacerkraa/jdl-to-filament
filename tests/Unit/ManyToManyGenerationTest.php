<?php

namespace Nacer\JdlToFilament\Tests\Unit;

use Nacer\JdlToFilament\FilamentResourceGenerator;
use Nacer\JdlToFilament\MigrationGenerator;
use Nacer\JdlToFilament\ModelGenerator;
use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Field;
use Nacer\JdlToFilament\Models\Relationship;
use PHPUnit\Framework\TestCase;

class ManyToManyGenerationTest extends TestCase
{
    private function entities(): array
    {
        $post = new Entity('Post', [new Field('title', 'String', true)], [
            new Relationship(Relationship::MANY_TO_MANY, 'Tag', 'tags', 'posts'),
        ]);
        $tag = new Entity('Tag', [new Field('name', 'String', true)], [
            new Relationship(Relationship::MANY_TO_MANY, 'Post', 'posts', 'tags'),
        ]);
        return [$post, $tag];
    }

    public function test_pivot_is_generated_once_after_entity_migrations(): void
    {
        $files = (new MigrationGenerator)->generate($this->entities());
        self::assertCount(3, $files);
        self::assertSame('post_tag', $files[2]['table']);
        self::assertStringContainsString("foreignId('post_id')->constrained('posts')->cascadeOnDelete()", $files[2]['content']);
        self::assertStringContainsString("foreignId('tag_id')->constrained('tags')->cascadeOnDelete()", $files[2]['content']);
        self::assertStringContainsString("primary(['post_id', 'tag_id'])", $files[2]['content']);
        self::assertStringNotContainsString("$table->id();", $files[2]['content']);
    }

    public function test_both_model_sides_use_flipped_pivot_keys(): void
    {
        $files = (new ModelGenerator)->generate($this->entities());
        $post = $files[0]['content'];
        $tag = $files[1]['content'];

        self::assertStringContainsString("belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id')", $post);
        self::assertStringContainsString("belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id')", $tag);
        self::assertStringContainsString('use Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany;', $post);
    }

    public function test_filament_generates_multi_select_without_collection_table_column(): void
    {
        $files = (new FilamentResourceGenerator)->generate($this->entities());
        $post = $files[0]['content'];

        self::assertStringContainsString("Select::make('tags')->relationship('tags', 'name')->multiple()->searchable()->preload()", $post);
        self::assertStringNotContainsString("TextColumn::make('tags.name')", $post);
    }

    public function test_filament_generates_many_to_many_relation_managers_and_registers_them(): void
    {
        $files = (new FilamentResourceGenerator)->generate($this->entities());
        $postResource = $files[0]['content'];
        $postManager = null;

        foreach ($files as $file) {
            if (str_ends_with($file['relativePath'], 'PostResource/RelationManagers/TagsRelationManager.php')) {
                $postManager = $file['content'];
                break;
            }
        }

        self::assertNotNull($postManager);
        self::assertStringContainsString('use App\\Filament\\Resources\\PostResource\\RelationManagers;', $postResource);
        self::assertStringContainsString('RelationManagers\\TagsRelationManager::class', $postResource);
        self::assertStringContainsString("protected static string \$relationship = 'tags';", $postManager);
        self::assertStringContainsString('AttachAction::make()', $postManager);
        self::assertStringContainsString('DetachAction::make()', $postManager);
        self::assertStringContainsString('DetachBulkAction::make()', $postManager);
        self::assertStringContainsString("TextColumn::make('name')->searchable()", $postManager);
    }

    public function test_generated_php_files_are_syntactically_valid(): void
    {
        $all = array_merge(
            (new MigrationGenerator)->generate($this->entities()),
            (new ModelGenerator)->generate($this->entities()),
        );

        foreach ($all as $file) {
            $path = tempnam(sys_get_temp_dir(), 'jdl-').'.php';
            file_put_contents($path, $file['content']);
            exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
            unlink($path);
            self::assertSame(0, $exitCode, implode("\n", $output));
        }
    }
}
