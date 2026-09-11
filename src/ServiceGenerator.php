<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;

class ServiceGenerator
{
    /** @param Entity[] $entities */
    public function generate(array $entities): array
    {
        $files = [];
        foreach ($entities as $entity) {
            if ($entity->serviceType === null) {
                continue;
            }

            $files[] = [
                'filename' => "{$entity->name}Service.php",
                'className' => $entity->name,
                'content' => $this->buildContent($entity),
            ];
        }

        return $files;
    }

    protected function buildContent(Entity $entity): string
    {
        $name = $entity->name;
        $varName = lcfirst($name);

        return <<<PHP
<?php

namespace App\Services;

use App\Models\{$name};
use Illuminate\Database\Eloquent\Collection;

class {$name}Service
{
    public function all(): Collection
    {
        return {$name}::all();
    }

    public function find(int \$id): ?{$name}
    {
        return {$name}::find(\$id);
    }

    public function create(array \$data): {$name}
    {
        return {$name}::create(\$data);
    }

    public function update({$name} \${$varName}, array \$data): {$name}
    {
        \${$varName}->update(\$data);

        return \${$varName};
    }

    public function delete({$name} \${$varName}): bool
    {
        return (bool) \${$varName}->delete();
    }
}

PHP;
    }
}
