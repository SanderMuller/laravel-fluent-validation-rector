<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns\Support;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;

/**
 * Trait `DetectsInheritedTraits` is declared `@phpstan-require-extends
 * AbstractRector` because it calls `$this->getName()`. The phpstan hint is
 * a static-analysis contract — at runtime only the method needs to exist.
 * This harness provides a minimal `getName()` that mirrors Rector's
 * `NodeNameResolver::getName()` resolution semantics for the node shapes
 * the helpers under test touch: `FullyQualified` → FQN, `Name` → literal
 * token when the token is not `UnresolvableAlias` (test sentinel for
 * "resolver returned null" scenarios).
 *
 * Lets the helpers be unit-tested without spinning up the Rector container.
 */
final class DetectsInheritedTraitsHarness
{
    use DetectsInheritedTraits {
        reflectedAncestryExtends as public;
        anyAncestorExtends as public;
        currentOrAncestorUsesTrait as public;
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
}
