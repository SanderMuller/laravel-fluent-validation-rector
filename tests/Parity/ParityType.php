<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Parity;

/**
 * Outcome of a single before/after parity comparison.
 *
 * @internal
 */
enum ParityType: string
{
    case Match = 'MATCH';
    case BeforeRejectsAfterPasses = 'BEFORE_REJECTS_AFTER_PASSES';
    case AfterRejectsBeforePasses = 'AFTER_REJECTS_BEFORE_PASSES';
    case BothRejectDifferentMessages = 'BOTH_REJECT_DIFFERENT_MESSAGES';
    case BothRejectDifferentOrder = 'BOTH_REJECT_DIFFERENT_ORDER';
    case Skipped = 'SKIPPED';
}
