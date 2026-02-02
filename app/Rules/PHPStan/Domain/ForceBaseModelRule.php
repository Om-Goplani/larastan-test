<?php

namespace App\Rules\PHPStan\Domain;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Class_>
 */
class ForceBaseModelRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 1. Skip anonymous classes
        if (! $node->name) {
            return [];
        }

        // 2. Skip the BaseModel itself (case-insensitive check)
        if (strtolower($node->name->toString()) === 'basemodel') {
            return [];
        }

        // 3. Skip if it doesn't extend anything
        if (! $node->extends) {
            return [];
        }

        // 4. Resolve the parent name (Handles Aliases & Imports)
        $parentClassName = $scope->resolveName($node->extends);


        // We convert both to lowercase to prevent "Model" vs "MODEL" bypasses
        if (strtolower($parentClassName) === 'illuminate\database\eloquent\model') {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'Architecture Violation: Class "%s" extends Laravel Model directly. Please extend "BaseModel".',
                        $node->name->toString()
                    )
                )
                ->identifier('domain.baseModel')
                ->build(),
            ];
        }

        return [];
    }
}


// @phpstan-ignore-line <--- IF YOU DO THIS
// Loophole
