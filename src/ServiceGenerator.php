<?php

namespace Nacer\JdlToFilament;

use Nacer\JdlToFilament\Models\Entity;

class ServiceGenerator
{
    /**
     * Only entities with a JDL "service" option set (e.g. "service
     * Product with serviceClass") get a generated Service class -
     * everything else is skipped. This is a thin CRUD wrapper around
     * the Eloquent model, the closest Laravel equivalent to JHipster's
     * Java service layer - Laravel itself has no single standard
     * "service class" convention the way it does for models/resources,
     * so treat this as a reasonable starting point to extend, not a
     * framework-enforced shape.
     *
     * @param  Entity[]  $entities
     * @return array<int, array{filename: string, className: string, content: string}>
     */
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

use App\Models\\{$name};
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
