<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

final class ScrambleOpenApiTransformer
{
    public function __invoke(OpenApi $openApi): void
    {
        foreach ($openApi->components->schemas as $name => $schema) {
            if (! $schema->type instanceof ObjectType) {
                continue;
            }

            if (str_ends_with($name, 'StoreTaskRequest') || str_ends_with($name, 'UpdateTaskRequest')) {
                $this->constrainTaskRequest($schema->type);
            }

            if (str_ends_with($name, 'StoreCategoryRequest') || str_ends_with($name, 'UpdateCategoryRequest')) {
                $this->stringProperty($schema->type, 'name')->setMin(1)->setMax(100);
            }
        }

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->parameters as $parameter) {
                    if (! $parameter instanceof Parameter || $parameter->schema === null) {
                        continue;
                    }

                    $type = $parameter->schema->type;
                    if ($parameter->in === 'path' && $type instanceof NumberType) {
                        $type->setMin(1);
                    }

                    if ($parameter->name === 'statuses[]' && $type instanceof ArrayType) {
                        $type->enum([])->setMin(1)->setMax(3)->setUniqueItems(true);
                    }

                    if ($parameter->name === 'category_ids[]' && $type instanceof ArrayType) {
                        $type->setMin(1)->setUniqueItems(true);
                        if ($type->items instanceof NumberType) {
                            $type->items->setMin(1);
                        }
                    }
                }
            }
        }
    }

    private function constrainTaskRequest(ObjectType $request): void
    {
        $this->stringProperty($request, 'title')->setMin(1)->setMax(200);
        $this->stringProperty($request, 'description')->setMax(2000);

        $categoryId = $request->getProperty('category_id');
        if ($categoryId instanceof NumberType) {
            $categoryId->setMin(1);
        }
    }

    private function stringProperty(ObjectType $object, string $name): StringType
    {
        $property = $object->getProperty($name);

        if (! $property instanceof StringType) {
            throw new \LogicException("OpenAPI property {$name} must be a string.");
        }

        return $property;
    }
}
