<?php

namespace Nacer\JdlToFilament;

use Illuminate\Support\Str;
use Nacer\JdlToFilament\Models\Entity;
use Nacer\JdlToFilament\Models\Relationship;

class FilamentRelationManagerGenerator
{
    public function __construct(protected TypeMapper $typeMapper = new TypeMapper) {}

    /** @param Entity[] $entities */
    public function generate(array $entities): array
    {
        $byName = [];
        foreach ($entities as $entity) {
            $byName[strtolower($entity->name)] = $entity;
        }

        $files = [];
        foreach ($entities as $owner) {
            foreach ($owner->relationships as $relationship) {
                if (! in_array($relationship->type, [Relationship::ONE_TO_MANY, Relationship::MANY_TO_MANY], true)) {
                    continue;
                }

                $target = $byName[strtolower($relationship->targetEntity)] ?? null;
                if ($target === null) {
                    continue;
                }

                $className = $this->className($relationship->relationshipName);
                $files[] = [
                    'owner' => $owner->name,
                    'relationship' => $relationship->relationshipName,
                    'class' => $className,
                    'relativePath' => "{$owner->name}Resource/RelationManagers/{$className}.php",
                    'content' => $this->buildContent($owner, $target, $relationship, $byName),
                ];
            }
        }

        return $files;
    }

    public function className(string $relationshipName): string
    {
        return Str::studly(Str::plural($relationshipName)).'RelationManager';
    }

    protected function buildContent(Entity $owner, Entity $target, Relationship $relationship, array $byName): string
    {
        $isManyToMany = $relationship->type === Relationship::MANY_TO_MANY;
        $className = $this->className($relationship->relationshipName);
        $formLines = [];
        $formImports = [];
        $tableLines = [];
        $tableImports = [];

        foreach ($target->fields as $field) {
            $column = Naming::columnName($field->name);
            $formLines[] = $this->typeMapper->formComponentLine($field, $column);
            $formImports[$this->typeMapper->formComponentClass($field)] = true;
            $tableLine = $this->typeMapper->tableColumnLine($field, $column);
            if ($tableLine !== null) {
                $tableLines[] = $tableLine;
                $tableImports[$this->typeMapper->tableColumnClass($field)] = true;
            }
        }

        foreach ($target->relationships as $targetRelationship) {
            if ($relationship->inverseName !== null && $targetRelationship->relationshipName === $relationship->inverseName) {
                continue;
            }

            $related = $byName[strtolower($targetRelationship->targetEntity)] ?? null;
            $labelColumn = $related !== null ? $this->labelColumnFor($related) : 'id';

            if (in_array($targetRelationship->type, [Relationship::MANY_TO_ONE, Relationship::ONE_TO_ONE], true)) {
                $column = Naming::foreignKeyColumn($targetRelationship->relationshipName);
                $formLines[] = "Select::make('{$column}')->relationship('{$targetRelationship->relationshipName}', '{$labelColumn}')->searchable()->preload()";
                $formImports['Select'] = true;
                $tableLines[] = "TextColumn::make('{$targetRelationship->relationshipName}.{$labelColumn}')->searchable()";
                $tableImports['TextColumn'] = true;
            } elseif ($targetRelationship->type === Relationship::MANY_TO_MANY) {
                $formLines[] = "Select::make('{$targetRelationship->relationshipName}')->relationship('{$targetRelationship->relationshipName}', '{$labelColumn}')->multiple()->searchable()->preload()";
                $formImports['Select'] = true;
            }
        }

        $formSchema = empty($formLines)
            ? '            // No editable fields were generated.'
            : implode(",\n", array_map(fn ($line) => $this->indent($line, 12), $formLines));
        $tableColumns = empty($tableLines)
            ? "            TextColumn::make('id')->sortable()"
            : implode(",\n", array_map(fn ($line) => $this->indent($line, 12), $tableLines));

        $formImportLines = implode("\n", array_map(
            fn ($class) => "use Filament\\Schemas\\Components\\{$class};",
            array_keys($formImports)
        ));
        $tableImportLines = implode("\n", array_map(
            fn ($class) => "use Filament\\Tables\\Columns\\{$class};",
            array_filter(array_keys($tableImports))
        ));

        $actions = $isManyToMany
            ? "            DetachAction::make(),"
            : "            EditAction::make(),\n            DeleteAction::make(),";
        $headerActions = $isManyToMany
            ? "            AttachAction::make()->preloadRecordSelect(),"
            : "            CreateAction::make(),";
        $bulkActions = $isManyToMany
            ? "                DetachBulkAction::make(),"
            : "                DeleteBulkAction::make(),";
        $actionImports = $isManyToMany
            ? "use Filament\\Actions\\AttachAction;\nuse Filament\\Actions\\DetachAction;\nuse Filament\\Actions\\DetachBulkAction;"
            : "use Filament\\Actions\\CreateAction;\nuse Filament\\Actions\\DeleteAction;\nuse Filament\\Actions\\DeleteBulkAction;\nuse Filament\\Actions\\EditAction;";

        return <<<PHP
<?php

namespace App\Filament\Resources\\{$owner->name}Resource\RelationManagers;

use Filament\Actions\BulkActionGroup;
{$actionImports}
{$formImportLines}
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
{$tableImportLines}

class {$className} extends RelationManager
{
    protected static string \$relationship = '{$relationship->relationshipName}';

    public function form(Schema \$schema): Schema
    {
        return \$schema->components([
{$formSchema}
        ]);
    }

    public function table(Table \$table): Table
    {
        return \$table
            ->recordTitleAttribute('{$this->labelColumnFor($target)}')
            ->columns([
{$tableColumns}
            ])
            ->headerActions([
{$headerActions}
            ])
            ->recordActions([
{$actions}
            ])
            ->toolbarActions([
                BulkActionGroup::make([
{$bulkActions}
                ]),
            ]);
    }
}

PHP;
    }

    protected function labelColumnFor(Entity $target): string
    {
        foreach ($target->fields as $field) {
            if ($field->type === 'String') {
                return Naming::columnName($field->name);
            }
        }

        return 'id';
    }

    protected function indent(string $line, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);
        return $pad.str_replace("\n", "\n".$pad, $line);
    }
}
