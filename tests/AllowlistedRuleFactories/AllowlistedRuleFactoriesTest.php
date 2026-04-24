<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\AllowlistedRuleFactories;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pattern-to-regex compiler. Integration (rector-level)
 * tests live under `tests/UpdateRulesReturnTypeDocblock/Fixture/` and
 * `tests/SimplifyRuleWrappers/Fixture/` — this test file only covers the
 * compiled-regex behavior since fixture coverage can't exercise the pattern
 * matcher in isolation.
 */
final class AllowlistedRuleFactoriesTest extends TestCase
{
    public function testExactFqnMatch(): void
    {
        $host = new PatternMatcherHost();
        $host->setPatterns(['App\\Models\\Question']);

        $this->assertTrue($host->matches('App\\Models\\Question'));
        $this->assertFalse($host->matches('App\\Models\\Other'));
        $this->assertFalse($host->matches('App\\Models\\Question\\Sub'));
    }

    public function testSingleSegmentWildcard(): void
    {
        $host = new PatternMatcherHost();
        $host->setPatterns(['App\\Models\\*']);

        $this->assertTrue($host->matches('App\\Models\\Question'));
        $this->assertTrue($host->matches('App\\Models\\Theme'));
        $this->assertFalse($host->matches('App\\Models\\Sub\\Bar'));
        $this->assertFalse($host->matches('App\\Http\\Foo'));
    }

    public function testRecursiveWildcardMatchesAnyDepth(): void
    {
        $host = new PatternMatcherHost();
        $host->setPatterns(['App\\Models\\**']);

        $this->assertTrue($host->matches('App\\Models\\Question'));
        $this->assertTrue($host->matches('App\\Models\\Sub\\Bar'));
        $this->assertTrue($host->matches('App\\Models\\A\\B\\C\\D'));
        $this->assertFalse($host->matches('App\\Http\\Foo'));
    }

    public function testLeadingWildcard(): void
    {
        $host = new PatternMatcherHost();
        $host->setPatterns(['*\\Requests\\BaseRequest']);

        $this->assertTrue($host->matches('App\\Requests\\BaseRequest'));
        $this->assertTrue($host->matches('Vendor\\Requests\\BaseRequest'));
        $this->assertFalse($host->matches('App\\Sub\\Requests\\BaseRequest'));
    }

    public function testLeadingBackslashNormalization(): void
    {
        $host = new PatternMatcherHost();
        $host->setPatterns(['\\App\\Models\\Question']);

        // Pattern and input are both normalized via ltrim('\\').
        $this->assertTrue($host->matches('App\\Models\\Question'));
        $this->assertTrue($host->matches('\\App\\Models\\Question'));
    }

    /**
     * Codex review regression test: an earlier compiler used literal textual
     * sentinels (`DOUBLESTARPLACEHOLDERXYZ` / `SINGLESTARPLACEHOLDERXYZ`) for
     * star substitution, which would mis-expand any FQN that happened to
     * contain the sentinel string. The tokenizer-based compiler splits on the
     * star characters themselves, so no literal-input collision is possible.
     */
    public function testLiteralInputCannotCollideWithStarPlaceholders(): void
    {
        $host = new PatternMatcherHost();
        $host->setPatterns(['App\\DOUBLESTARPLACEHOLDERXYZ\\Rule']);

        $this->assertTrue($host->matches('App\\DOUBLESTARPLACEHOLDERXYZ\\Rule'));
        $this->assertFalse($host->matches('App\\Anything\\Rule'));
        $this->assertFalse($host->matches('App\\Deep\\Nested\\Rule'));
    }
}
