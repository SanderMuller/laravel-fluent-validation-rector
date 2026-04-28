<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns\Support;

use SanderMuller\FluentValidationRector\Rector\Concerns\NormalizesRulesDocblock;

/**
 * Exposes `annotationBodyMatchesStandardUnionExactlyOrProse` as public so
 * unit tests can exercise the accept/reject matrix without routing through
 * a full rector pass.
 */
final class NormalizesRulesDocblockHarness
{
    use NormalizesRulesDocblock {
        annotationBodyMatchesStandardUnionExactlyOrProse as public;
    }

    /**
     * No-op stub for the trait's abstract `queueValidationRuleUseImport`
     * hook (added 0.20.2). The harness only exercises the
     * `annotationBodyMatchesStandardUnionExactlyOrProse` predicate; it
     * doesn't drive the full normalize+emit flow that would invoke this
     * hook, so a no-op satisfies the abstract contract without needing
     * a real `UseNodesToAddCollector` injection.
     */
    protected function queueValidationRuleUseImport(): void
    {
        // intentionally empty
    }
}
