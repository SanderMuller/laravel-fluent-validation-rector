<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config;

use SanderMuller\FluentValidationRector\Config\Shared\AllowlistedFactories;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

/**
 * Typed builder for `SimplifyRuleWrappersRector`'s configuration. Wraps
 * an `AllowlistedFactories` DTO that is shared with `DocblockNarrowOptions`
 * so both rectors stay in lockstep on what counts as "fluent-compatible".
 *
 *     $rectorConfig->ruleWithConfiguration(
 *         SimplifyRuleWrappersRector::class,
 *         RuleWrapperSimplifyOptions::default()
 *             ->withAllowlistedFactories(
 *                 AllowlistedFactories::none()
 *                     ->withFactories(['App\\Rules\\Custom'])
 *                     ->allowingChainTail(),
 *             )
 *             ->toArray(),
 *     );
 */
final readonly class RuleWrapperSimplifyOptions
{
    private function __construct(public AllowlistedFactories $allowlistedFactories) {}

    public static function default(): self
    {
        return new self(AllowlistedFactories::none());
    }

    /**
     * Named constructor for the common "build with explicit allowlist"
     * shape. Reads more naturally than `default()->withAllowlistedFactories(...)`
     * — `default()` implies "give me defaults" but the immediate
     * `with*()` chain says we're not really after defaults.
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
            SimplifyRuleWrappersRector::TREAT_AS_FLUENT_COMPATIBLE => $this->allowlistedFactories->factories,
            SimplifyRuleWrappersRector::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED => $this->allowlistedFactories->allowChainTail,
        ];
    }
}
