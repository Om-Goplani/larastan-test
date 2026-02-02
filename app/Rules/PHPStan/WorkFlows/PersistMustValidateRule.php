<?php

namespace App\Rules\PHPStan\WorkFlows;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\ClassMethod>
 */
class PersistMustValidateRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return Node\Stmt\ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 1. LOCATION CHECK: Service Layer Only
        $namespace = $scope->getNamespace();
        if (! $namespace || ! str_starts_with($namespace, 'App\\Services')) {
            return [];
        }

        // 2. NAME CHECK: Methods starting with "persist" (Case-insensitive)
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }
        $methodName = $node->name->toString();
        if (! str_starts_with(strtolower($methodName), 'persist')) {
            return [];
        }

        if ($node->stmts === null) {
            return [];
        }

        // 3. SMART PARAMETER CHECK (The "Reflection" Upgrade)
        // We don't just check the string name. We check the Hierarchy.
        foreach ($node->params as $param) {
            if (! $param->type) {
                continue;
            }

            // Resolve the real class name
            $typeString = $scope->resolveName($param->type);

            // Check if this class actually exists and extends FormRequest
            if ($this->reflectionProvider->hasClass($typeString)) {
                $classReflection = $this->reflectionProvider->getClass($typeString);

                if ($classReflection->isSubclassOf('Illuminate\Foundation\Http\FormRequest')) {
                    return []; // ✅ PASS: A real FormRequest is present.
                }
            }
        }

        // 4. EXECUTION CHECK (The "Lazy Validator" Fix)
        // We only accept methods that *execute* validation, not just *create* it.
        $nodeFinder = new NodeFinder();

        $hasValidationExecution = $nodeFinder->findFirst($node->stmts, function (Node $subNode) {
            if ($subNode instanceof Node\Expr\MethodCall && $subNode->name instanceof Node\Identifier) {
                $method = strtolower($subNode->name->toString());

                // We accept these methods because they trigger the check or retrieve validated data:
                // ->validate()       (Runs and throws exception)
                // ->validated()      (Returns safe data)
                // ->fails()          (Runs and returns boolean)
                // ->passes()         (Runs and returns boolean)
                if (in_array($method, ['validate', 'validated', 'fails', 'passes'], true)) {
                    return true;
                }

                // Also allow "validateWithBag", etc.
                if (str_starts_with($method, 'validate')) {
                    return true;
                }
            }
            return false;
        });

        if (! $hasValidationExecution) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Unsafe Persistence: Method "%s" saves data but does not execute validation. Inject a FormRequest, or call ->validate() / ->fails().',
                    $methodName
                ))
                ->identifier('workflow.persistValidation')
                ->build(),
            ];
        }

        return [];
    }
}
