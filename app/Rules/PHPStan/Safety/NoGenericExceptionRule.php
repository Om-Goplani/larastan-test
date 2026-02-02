<?php

namespace App\Rules\PHPStan\Safety;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Expr\New_>
 */
class NoGenericExceptionRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 1. If it's a dynamic class instantiation (e.g., new $className()), skip it.
        if (! $node->class instanceof Node\Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);


        $forbiddenExceptions = [
            'exception' => true,
            'runtimeexception' => true,
            'error' => true,
        ];

        if (isset($forbiddenExceptions[strtolower($className)])) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'Error Handling: Do not throw generic "%s". Create a custom Domain Exception (e.g., OrderNotFoundException).',
                        $className
                    )
                )
                ->identifier('safety.noGenericException')
                ->build(),
            ];
        }

        return [];
    }
}
