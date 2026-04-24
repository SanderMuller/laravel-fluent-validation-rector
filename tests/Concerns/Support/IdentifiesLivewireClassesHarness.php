<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns\Support;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TraitUse;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;

/**
 * Test harness for {@see IdentifiesLivewireClasses}. Mirrors the minimal
 * surface from {@see DetectsInheritedTraitsHarness}: exposes the private
 * `isLivewireClass` as public and provides a token-level `getName()` that
 * matches Rector's `NodeNameResolver` for the node shapes exercised in
 * tests.
 */
final class IdentifiesLivewireClassesHarness
{
    use IdentifiesLivewireClasses {
        isLivewireClass as public;
    }

    public function getName(Node $node): ?string
    {
        if ($node instanceof FullyQualified) {
            return $node->toString();
        }

        if ($node instanceof Name) {
            if ($node->toString() === 'UnresolvableAlias') {
                return null;
            }

            return $node->toString();
        }

        return null;
    }

    public function buildClass(
        string $shortName,
        ?string $extends = null,
        array $traitFqns = [],
        array $methodNames = [],
    ): Class_ {
        $class = new Class_($shortName);

        if ($extends !== null) {
            $class->extends = new FullyQualified($extends);
        }

        foreach ($traitFqns as $trait) {
            $class->stmts[] = new TraitUse([new FullyQualified($trait)]);
        }

        foreach ($methodNames as $methodName) {
            $class->stmts[] = new ClassMethod($methodName);
        }

        return $class;
    }
}
