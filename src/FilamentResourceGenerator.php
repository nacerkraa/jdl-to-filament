<?php

namespace Nacer\JdlToFilament;

use Illuminate\Support\Str;
use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Field;
use Nacer\JdlToFilament\Models\Relationship;

class FilamentResourceGenerator
{
    protected FilamentRelationManagerGenerator $relationManagerGenerator;

    public function __construct(protected TypeMapper $typeMapper = new TypeMapper)
    {
        $this->relationManagerGenerator = new FilamentRelationManagerGenerator($typeMapper);
    }

    /** @param Entity[] $entities */
    public function generate(array $entities): array
    {
        $byName = [];
        foreach ($entities as $entity) {
            $byName[strtolower($entity->name)] = $entity;
        }

        $relationFiles = $this->relationManagerGenerator->generate($entities);
        $relationsByOwner = [];
        foreach ($relationFiles as $file) {
            $relationsByOwner[$file['owner']][] = $file['class'];
        }

        $files = [];
        foreach ($entities as $entity) {
            $files = array_merge($files, $this->generateForEntity(
                $entity,
                $byName,
                $relationsByOwner[$entity->name] ?? []
            ));
        }

        return array_merge($files, array_map(
            fn (array $file) => [
                'relativePath' => $file['relativePath'],
                'content' => $file['content'],
            ],
            $relationFiles
        ));
    }

    protected function generateForEntity(Entity $entity, array $byName, array $relationManagerClasses = []): array
    {
        $name = $entity->name;
        $plural = Str::plural($name);

        return [
            ['relativePath' => "{$name}Resource.php", 'content' => $this->buildResourceContent($entity, $byName, $plural, $relationManagerClasses)],
            ['relativePath' => "{$name}Resource/Pages/List{$plural}.php", 'content' => $this->buildListPageContent($name, $plural)],
            ['relativePath' => "{$name}Resource/Pages/Create{$name}.php", 'content' => $this->buildCreatePageContent($name)],
            ['relativePath' => "{$name}Resource/Pages/Edit{$name}.php", 'content' => $this->buildEditPageContent($name)],
        ];
    }

    protected function labelColumnFor(Entity $target): string
    {
        foreach ($target->fields as $field) {
            if ($field->type === 'String') return Naming::columnName($field->name);
        }
        return 'id';
    }

    protected function buildResourceContent(Entity $entity, array $byName, string $plural, array $relationManagerClasses = []): string
    {
        $formLines = [];
        $formImports = [];
        $tableLines = [];
        $tableImports = [];
        $filterLines = [];
        $filterImports = [];

        foreach ($entity->fields as $field) {
            $column = Naming::columnName($field->name);
            $formLines[] = $this->typeMapper->formComponentLine($field, $column);
            $formImports[$this->typeMapper->formComponentClass($field)] = true;
            $tableLine = $this->typeMapper->tableColumnLine($field, $column);
            if ($tableLine !== null) {
                $tableLines[] = $tableLine;
                $tableImports[$this->typeMapper->tableColumnClass($field)] = true;
            }

            if ($entity->filterable) {
                if ($field->type === 'Boolean') {
                    $filterLines[] = "TernaryFilter::make('{$column}')";
                    $filterImports['TernaryFilter'] = true;
                } elseif ($field->isEnum()) {
                    $filterLines[] = "SelectFilter::make('{$column}')->options([{$this->enumOptionsPhpArray($field)}])";
                    $filterImports['SelectFilter'] = true;
                }
            }
        }

        foreach ($entity->relationships as $relationship) {
            $target = $byName[strtolower($relationship->targetEntity)] ?? null;
            $labelColumn = $target !== null ? $this->labelColumnFor($target) : 'id';

            if (in_array($relationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                $fkColumn = Naming::foreignKeyColumn($relationship->relationshipName);
                $formLines[] = "Select::make('{$fkColumn}')->relationship('{$relationship->relationshipName}', '{$labelColumn}')->searchable()->preload()";
                $formImports['Select'] = true;
                $tableLines[] = "TextColumn::make('{$relationship->relationshipName}.{$labelColumn}')->sortable()->searchable()";
                $tableImports['TextColumn'] = true;

                if ($entity->filterable) {
                    $filterLines[] = "SelectFilter::make('{$relationship->relationshipName}')->relationship('{$relationship->relationshipName}', '{$labelColumn}')->searchable()->preload()";
                    $filterImports['SelectFilter'] = true;
                }
            } elseif ($relationship->type === Relationship::MANY_TO_MANY) {
                $formLines[] = "Select::make('{$relationship->relationshipName}')->relationship('{$relationship->relationshipName}', '{$labelColumn}')->multiple()->searchable()->preload()";
                $formImports['Select'] = true;
            }
        }

        $formSchema = implode(",\n", array_map(fn ($l) => $this->indent($l, 16), $formLines));
        $tableColumns = implode(",\n", array_map(fn ($l) => $this->indent($l, 16), $tableLines));
        $filtersBlock = empty($filterLines) ? '                //' : implode(",\n", array_map(fn ($l) => $this->indent($l, 16), $filterLines));
        $formImportLines = implode("\n", array_map(fn ($c) => "use Filament\\Forms\\Components\\{$c};", array_keys($formImports)));
        $tableImportLines = implode("\n", array_map(fn ($c) => "use Filament\\Tables\\Columns\\{$c};", array_keys($tableImports)));
        $filterImportLines = implode("\n", array_map(fn ($c) => "use Filament\\Tables\\Filters\\{$c};", array_keys($filterImports)));
        if ($filterImportLines !== '') $filterImportLines = "\n".$filterImportLines;

        $relationImports = '';
        $relationEntries = '        // No relation managers generated.';
        if (! empty($relationManagerClasses)) {
            $relationImports = "use App\\Filament\\Resources\\{$entity->name}Resource\\RelationManagers;\n";
            $relationEntries = implode(",\n", array_map(
                fn (string $class) => "        RelationManagers\\{$class}::class",
                $relationManagerClasses
            ));
        }

        $name = $entity->name;

        return <<<PHP
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\\{$name}Resource\Pages;
use App\Models\\{$name};
{$relationImports}use Filament\Actions;
{$formImportLines}
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
{$tableImportLines}{$filterImportLines}

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;
    protected static \BackedEnum|string|null \$navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema \$schema): Schema
    {
        return \$schema->components([
{$formSchema}
        ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table->columns([
{$tableColumns}
        ])->filters([
{$filtersBlock}
        ])->paginationPageOptions([10, 25, 50, 100])->recordActions([
            Actions\EditAction::make(),
        ])->toolbarActions([
            Actions\BulkActionGroup::make([
                Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
{$relationEntries}
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

    protected function enumOptionsPhpArray(Field $field): string
    {
        $values = array_map('trim', explode(',', (string) $field->enumValues));
        return implode(', ', array_map(fn ($v) => "'{$v}' => '{$v}'", $values));
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
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
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
class Create{$name} extends CreateRecord { protected static string \$resource = {$name}Resource::class; }
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
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
PHP;
    }

    protected function indent(string $line, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);
        return $pad.str_replace("\n", "\n".$pad, $line);
    }
}
