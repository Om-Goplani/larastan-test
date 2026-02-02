<?php

namespace App\Rules\PHPStan\Layers;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<Node\Expr>
 */
class EncapsulatePersistenceRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Node\Expr\MethodCall && ! $node instanceof Node\Expr\StaticCall) {
            return [];
        }

        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        $forbidden = [
            // Standard Eloquent & Builder
            'create' => true, 'insert' => true, 'firstorcreate' => true,
            'updateorcreate' => true, 'forcecreate' => true, 'insertgetid' => true,
            'save' => true, 'update' => true, 'increment' => true, 'decrement' => true,
            'touch' => true, 'delete' => true, 'destroy' => true, 'forcedelete' => true,
            'restore' => true, 'truncate' => true,

            // Raw SQL & Connection Methods
            'statement' => true, 'unprepared' => true, 'affectingstatement' => true,
            'selectstatement' => true,

            // Relationships
            'attach' => true, 'detach' => true, 'sync' => true,
            'syncwithoutdetaching' => true, 'associate' => true, 'dissociate' => true,

            // Mass Assignment & Factories
            'fill' => true, 'forcefill' => true, 'replicate' => true,
            'push' => true, 'make' => true,
        ];

        if (! isset($forbidden[strtolower($methodName)])) {
            return [];
        }

        $callerType = $this->getCallerType($node, $scope);

        $isDatabaseLayer = $callerType->isInstanceOf('Illuminate\Database\Eloquent\Model')->yes()
            || $callerType->isInstanceOf('Illuminate\Database\Eloquent\Builder')->yes()
            || $callerType->isInstanceOf('Illuminate\Database\Eloquent\Relations\Relation')->yes()
            || $callerType->isInstanceOf('Illuminate\Database\Query\Builder')->yes()
            || $callerType->isInstanceOf('Illuminate\Support\Facades\DB')->yes()
            || $callerType->isInstanceOf('Illuminate\Database\Eloquent\Factories\Factory')->yes()
            || $callerType->isInstanceOf('Illuminate\Database\ConnectionInterface')->yes()
            || $callerType->isInstanceOf('Illuminate\Support\HigherOrderCollectionProxy')->yes()
            || $callerType->isInstanceOf('Illuminate\Support\HigherOrderTapProxy')->yes();

        if (! $isDatabaseLayer) {
            return [];
        }

        $namespace = $scope->getNamespace();

        if (! $namespace) {
             return $this->buildError($methodName);
        }

        if (str_starts_with($namespace, 'App\\Services')) {
            return [];
        }

        if (str_starts_with($namespace, 'App\\Observers')) {
            return [];
        }

        if (str_contains($namespace, 'Tests')) {
            return [];
        }

        return $this->buildError($methodName);
    }

    private function getCallerType(Node $node, Scope $scope): \PHPStan\Type\Type
    {
        if ($node instanceof Node\Expr\MethodCall) {
            return $scope->getType($node->var);
        }

        if ($node instanceof Node\Expr\StaticCall) {
            if ($node->class instanceof Node\Name) {
                return new ObjectType($scope->resolveName($node->class));
            }
        }

        return new \PHPStan\Type\MixedType();
    }

    private function buildError(string $method): array
    {
        return [
            RuleErrorBuilder::message(sprintf(
                'Strict Architecture: Persistence method "%s()" is forbidden here. Move logic to "App\Services" or "App\Observers".',
                $method
            ))
            ->identifier('layer.persistence')
            ->build(),
        ];
    }
}
