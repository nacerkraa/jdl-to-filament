<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Field;

class TypeMapper
{
    /**
     * JDL scalar type -> Laravel migration Blueprint method name.
     * Anything not listed here falls back to 'string'.
     *
     * Note: TextBlob/Blob/AnyBlob/ImageBlob do NOT appear here - JDL
     * exports all of them as fieldType "byte[]" with a separate
     * "fieldTypeBlobContent" discriminator ("text"/"image"/"any"),
     * not as distinct type strings. See methodFor() below.
     */
    private const MAP = [
        'String' => 'string',
        'Integer' => 'integer',
        'Long' => 'bigInteger',
        'Float' => 'float',
        'Double' => 'double',
        'BigDecimal' => 'decimal',
        'LocalDate' => 'date',
        'Instant' => 'timestamp',
        'ZonedDateTime' => 'timestamp',
        'Duration' => 'string',
        'Boolean' => 'boolean',
        'UUID' => 'uuid',
    ];

    /**
     * JDL scalar type -> Eloquent $casts value. Types absent from this
     * map (String, UUID, blobs, enums) get no cast entry at all -
     * Eloquent's default string handling is correct for them as-is.
     */
    private const CAST_MAP = [
        'Integer' => 'integer',
        'Long' => 'integer',
        'Float' => 'float',
        'Double' => 'float',
        'BigDecimal' => 'decimal:2',
        'LocalDate' => 'date',
        'Instant' => 'datetime',
        'ZonedDateTime' => 'datetime',
        'Boolean' => 'boolean',
    ];

    /**
     * Build a full "$table->..." migration line for a field, given the
     * (already snake_cased) column name to use.
     */
    public function columnLine(Field $field, string $columnName): string
    {
        $method = $this->methodFor($field);

        $line = match ($method) {
            'enum' => sprintf("\$table->enum('%s', [%s])", $columnName, $this->enumValuesPhpArray($field)),
            'decimal' => sprintf("\$table->decimal('%s', 10, 2)", $columnName),
            default => sprintf("\$table->%s('%s')", $method, $columnName),
        };

        if (! $field->required) {
            $line .= '->nullable()';
        }

        return $line.';';
    }

    /**
     * Eloquent $casts value for a field, or null if it needs none.
     */
    public function castFor(Field $field): ?string
    {
        if ($field->isBlob() || $field->isEnum()) {
            return null;
        }

        return self::CAST_MAP[$field->type] ?? null;
    }

    protected function methodFor(Field $field): string
    {
        if ($field->isBlob()) {
            return $field->blobContentType === 'text' ? 'text' : 'binary';
        }

        if ($field->isEnum()) {
            return 'enum';
        }

        return self::MAP[$field->type] ?? 'string';
    }

    protected function enumValuesPhpArray(Field $field): string
    {
        $values = array_map('trim', explode(',', (string) $field->enumValues));
        $quoted = array_map(fn ($v) => "'{$v}'", $values);

        return implode(', ', $quoted);
    }

    /**
     * Build a full Filament form component line for a field (e.g.
     * "TextInput::make('price')->numeric()->required()"), given the
     * already snake_cased column name.
     */
    public function formComponentLine(Field $field, string $columnName): string
    {
        $required = $field->required ? '->required()' : '';

        if ($field->isEnum()) {
            $values = array_map('trim', explode(',', (string) $field->enumValues));
            $options = implode(', ', array_map(fn ($v) => "'{$v}' => '{$v}'", $values));

            return "Select::make('{$columnName}')->options([{$options}]){$required}";
        }

        if ($field->isBlob()) {
            if ($field->blobContentType === 'text') {
                return "Textarea::make('{$columnName}'){$required}";
            }
            $image = $field->blobContentType === 'image' ? '->image()' : '';

            return "FileUpload::make('{$columnName}'){$image}{$required}";
        }

        return match (self::MAP[$field->type] ?? 'string') {
            'integer', 'bigInteger', 'float', 'double', 'decimal' => "TextInput::make('{$columnName}')->numeric(){$required}",
            'boolean' => "Toggle::make('{$columnName}')",
            'date' => "DatePicker::make('{$columnName}'){$required}",
            'timestamp' => "DateTimePicker::make('{$columnName}'){$required}",
            default => "TextInput::make('{$columnName}'){$required}",
        };
    }

    /**
     * Short Filament\Forms\Components class name needed for a field's
     * form component (for building the "use" import list).
     */
    public function formComponentClass(Field $field): string
    {
        if ($field->isEnum()) {
            return 'Select';
        }

        if ($field->isBlob()) {
            return $field->blobContentType === 'text' ? 'Textarea' : 'FileUpload';
        }

        return match (self::MAP[$field->type] ?? 'string') {
            'integer', 'bigInteger', 'float', 'double', 'decimal' => 'TextInput',
            'boolean' => 'Toggle',
            'date' => 'DatePicker',
            'timestamp' => 'DateTimePicker',
            default => 'TextInput',
        };
    }

    /**
     * Build a full Filament table column line for a field, or null to
     * skip it entirely (blobs are too large to list in a table).
     */
    public function tableColumnLine(Field $field, string $columnName): ?string
    {
        if ($field->isBlob()) {
            return null;
        }

        if ($field->isEnum()) {
            return "TextColumn::make('{$columnName}')->badge()";
        }

        return match (self::MAP[$field->type] ?? 'string') {
            'boolean' => "IconColumn::make('{$columnName}')->boolean()",
            'date' => "TextColumn::make('{$columnName}')->date()->sortable()",
            'timestamp' => "TextColumn::make('{$columnName}')->dateTime()->sortable()",
            'decimal', 'integer', 'bigInteger', 'float', 'double' => "TextColumn::make('{$columnName}')->numeric()->sortable()",
            default => "TextColumn::make('{$columnName}')->searchable()",
        };
    }

    /**
     * Short Filament\Tables\Columns class name needed for a field's
     * table column, or null if the field has no column (blobs).
     */
    public function tableColumnClass(Field $field): ?string
    {
        if ($field->isBlob()) {
            return null;
        }

        if (! $field->isEnum() && (self::MAP[$field->type] ?? 'string') === 'boolean') {
            return 'IconColumn';
        }

        return 'TextColumn';
    }
}
