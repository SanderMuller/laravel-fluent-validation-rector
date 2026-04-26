<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Parity;

/**
 * Result of a single before/after parity comparison.
 *
 * @internal
 */
final readonly class ParityOutcome
{
    /**
     * @param  array<string, list<string>>  $beforeErrors
     * @param  array<string, list<string>>  $afterErrors
     */
    public function __construct(
        public ParityType $type,
        public array $beforeErrors = [],
        public array $afterErrors = [],
        public ?string $skipReason = null,
    ) {}
}
