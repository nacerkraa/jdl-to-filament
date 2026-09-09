<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Field;
use Nacer\JdlToFilament\Models\Relationship;
use Illuminate\Support\Str;

class FilamentResourceGenerator
{
    public function __construct(protected TypeMapper $typeMapper = new TypeMapper)
    {
    }

    /**
     * @param  Entity[]  $entities
     * @return array<int, array{relativePath: string, content: string}>
     *         relativePath is relative to app/Filament/Resources, e.g.
     *         "ProductResource.php" or "ProductResource/Pages/ListProducts.php"
     */
    public function generate(array $entities): array
    {
        $byName = [];
        foreach ($entities as $entity) {
            $byName[strtolower($entity->name)] = $entity;
        }

        $files = [];
        foreach ($entities as $entity) {
            $files = array_merge($files, $this->generateForEntity($entity, $byName));
        }

        return $files;
    }

    /**
     * @param  array<string, Entity>  $byName
     * @return array<int, array{relativePath: string, content: string}>
     */
    protected function generateForEntity(Entity $entity, array $byName): array
    {
        $name = $entity->name;
        $plural = Str::plural($name);

        $files = [];

        $files[] = [
            'relativePath' => "{$name}Resource.php",
            'content' => $this->buildResourceContent($entity, $byName, $plural),
        ];

        $files[] = [
            'relativePath' => "{$name}Resource/Pages/List{$plural}.php",
            'content' => $this->buildListPageContent($name, $plural),
        ];

        $files[] = [
            'relativePath' => "{$name}Resource/Pages/Create{$name}.php",
            'content' => $this->buildCreatePageContent($name),
        ];

        $files[] = [
            'relativePath' => "{$name}Resource/Pages/Edit{$name}.php",
            'content' => $this->buildEditPageContent($name),
        ];

        return $files;
    }

    /**
     * The column used to represent a related record in a Select/table
     * column - the target entity's first String field, or "id" if it
     * has none (e.g. an entity made only of numbers/dates).
     */
    protected function labelColumnFor(Entity $target): string
    {
        foreach ($target->fields as $field) {
            if ($field->type === 'String') {
                return Naming::columnName($field->name);
            }
        }

        return 'id';
    }

    /**
     * @param  array<string, Entity>  $byName
     */
    protected function buildResourceContent(Entity $entity, array $byName, string $plural): string
    {
        $formLines = [];
        $formImports = [];
        $tableLines = [];
        $tableImports = [];

        foreach ($entity->fields as $field) {
            $column = Naming::columnName($field->name);

            $formLines[] = $this->typeMapper->formComponentLine($field, $column);
            $formImports[$this->typeMapper->formComponentClass($field)] = true;

            $tableLine = $this->typeMapper->tableColumnLine($field, $column);
            if ($tableLine !== null) {
                $tableLines[] = $tableLine;
                $tableImports[$this->typeMapper->tableColumnClass($field)] = true;
            }
        }

        foreach ($entity->relationships as $relationship) {
            if (! in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                // OneToMany / ManyToMany relationships aren't shown on the
                // form or table yet - see README. They'd typically use a
                // RelationManager instead, which is a further step.
                continue;
            }

            $fkColumn = Naming::foreignKeyColumn($relationship->relationshipName);
            $targetKey = strtolower($relationship->targetEntity);
            $target = $byName[$targetKey] ?? null;
            $labelColumn = $target !== null ? $this->labelColumnFor($target) : 'id';

            $formLines[] = "Select::make('{$fkColumn}')->relationship('{$relationship->relationshipName}', '{$labelColumn}')->searchable()->preload()";
            $formImports['Select'] = true;

            $tableLines[] = "TextColumn::make('{$relationship->relationshipName}.{$labelColumn}')->sortable()->searchable()";
            $tableImports['TextColumn'] = true;
        }

        $formSchema = implode(",\n", array_map(fn ($l) => $this->indent($l, 16), $formLines));
        $tableColumns = implode(",\n", array_map(fn ($l) => $this->indent($l, 16), $tableLines));

        $formImportLines = implode("\n", array_map(
            fn ($c) => "use Filament\\Forms\\Components\\{$c};",
            array_keys($formImports)
        ));

        $tableImportLines = implode("\n", array_map(
            fn ($c) => "use Filament\\Tables\\Columns\\{$c};",
            array_keys($tableImports)
        ));

        $name = $entity->name;

        return <<<PHP
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\\{$name}Resource\Pages;
use App\Models\\{$name};
use Filament\Actions;
{$formImportLines}
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
{$tableImportLines}

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;

    protected static \BackedEnum|string|null \$navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema \$schema): Schema
    {
        return \$schema
            ->components([
{$formSchema}
            ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
{$tableColumns}
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\List{$plural}::route('/'),
            'create' => Pages\Create{$name}::route('/create'),
            'edit' => Pages\Edit{$name}::route('/{record}/edit'),
        ];
    }
}

PHP;
    }

    protected function buildListPageContent(string $name, string $plural): string
    {
        return <<<PHP
<?php

namespace App\Filament\Resources\\{$name}Resource\Pages;

use App\Filament\Resources\\{$name}Resource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class List{$plural} extends ListRecords
{
    protected static string \$resource = {$name}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

PHP;
    }

    protected function buildCreatePageContent(string $name): string
    {
        return <<<PHP
<?php

namespace App\Filament\Resources\\{$name}Resource\Pages;

use App\Filament\Resources\\{$name}Resource;
use Filament\Resources\Pages\CreateRecord;

class Create{$name} extends CreateRecord
{
    protected static string \$resource = {$name}Resource::class;
}

PHP;
    }

    protected function buildEditPageContent(string $name): string
    {
        return <<<PHP
<?php

namespace App\Filament\Resources\\{$name}Resource\Pages;

use App\Filament\Resources\\{$name}Resource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class Edit{$name} extends EditRecord
{
    protected static string \$resource = {$name}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

PHP;
    }

    protected function indent(string $line, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);

        return $pad.str_replace("\n", "\n".$pad, $line);
    }
}
