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
}
