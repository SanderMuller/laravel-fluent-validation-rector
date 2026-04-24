<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\AllowlistedRuleFactories;

use SanderMuller\FluentValidationRector\Rector\Concerns\AllowlistedRuleFactories;

/**
 * Test stub exposing the pattern-matcher's private helpers as public. The
 * trait's `@phpstan-require-extends AbstractRector` is a static-analysis
 * hint, not a runtime check; the stub satisfies the trait's `getName()`
 * dependency only for methods that don't invoke it (exactly the pattern
 * helpers we test here).
 */
final class PatternMatcherHost
{
    use AllowlistedRuleFactories {
        classNameMatchesPattern as public;
    }

    /**
     * Feeds patterns through the production configure path so leading
     * backslashes are normalized consistently with live rector use.
     *
     * @param  list<string|array{0: string, 1: string}>  $patterns
     */
    public function setPatterns(array $patterns): void
    {
        $this->configureAllowlistedRuleFactoriesFrom([
            self::TREAT_AS_FLUENT_COMPATIBLE => $patterns,
        ]);
    }

    public function matches(string $className): bool
    {
        foreach ($this->allowlistedRuleFactories as $pattern) {
            if (is_string($pattern) && $this->classNameMatchesPattern($className, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
