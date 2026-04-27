<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config;

use SanderMuller\FluentValidationRector\Config\Shared\AllowlistedFactories;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;

/**
 * Typed builder for `UpdateRulesReturnTypeDocblockRector`'s configuration.
 * Consumes the shared `AllowlistedFactories` DTO; same wire keys as
 * `RuleWrapperSimplifyOptions` because the two rectors share the
 * fluent-compatibility allowlist concept.
 */
final readonly class DocblockNarrowOptions
{
    private function __construct(public AllowlistedFactories $allowlistedFactories) {}

    public static function default(): self
    {
        return new self(AllowlistedFactories::none());
    }

    /**
     * Named constructor — see `RuleWrapperSimplifyOptions::with()`. Both
     * DTOs accept the same shared `AllowlistedFactories` so the
     * cross-rector consolidation pattern reads identically:
     *
     *     $allowlist = AllowlistedFactories::none()->withFactories([…]);
     *     RuleWrapperSimplifyOptions::with($allowlist)->toArray();
     *     DocblockNarrowOptions::with($allowlist)->toArray();
     */
    public static function with(AllowlistedFactories $factories): self
    {
        return new self($factories);
    }

    public function withAllowlistedFactories(AllowlistedFactories $factories): self
    {
        return new self($factories);
    }

    /**
     * @return array{treat_as_fluent_compatible: list<string|array{0: string, 1: string}>, allow_chain_tail_on_allowlisted: bool}
     */
    public function toArray(): array
    {
        return [
            UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE => $this->allowlistedFactories->factories,
            UpdateRulesReturnTypeDocblockRector::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED => $this->allowlistedFactories->allowChainTail,
        ];
    }
}
