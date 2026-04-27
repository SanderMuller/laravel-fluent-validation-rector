<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Config\Shared;

/**
 * Shared allowlist for `SimplifyRuleWrappersRector` and
 * `UpdateRulesReturnTypeDocblockRector`. Both consume the same wire keys
 * (`treat_as_fluent_compatible`, `allow_chain_tail_on_allowlisted`); this
 * DTO centralizes the schema so the two rectors stay in lockstep.
 *
 * Each `factories` entry is either:
 *  - a class FQN or wildcard pattern matching `new <FQN>(...)` — e.g.
 *    `'App\\Rules\\CustomRule'` or `'App\\Rules\\**'`;
 *  - a `[<class-FQN-or-pattern>, '<methodName>']` tuple matching
 *    `<Class>::<method>(...)` — e.g. `['App\\Models\\Question', 'existsRule']`.
 *
 * Pattern syntax (`*` single-segment, `**` recursive) is documented on the
 * underlying `AllowlistedRuleFactories` trait.
 */
final readonly class AllowlistedFactories
{
    /**
     * @param  list<string|array{0: string, 1: string}>  $factories
     */
    private function __construct(
        public array $factories,
        public bool $allowChainTail,
    ) {}

    public static function none(): self
    {
        return new self([], false);
    }

    /**
     * @param  list<string|array{0: string, 1: string}>  $factories
     */
    public function withFactories(array $factories): self
    {
        return new self($factories, $this->allowChainTail);
    }

    public function allowingChainTail(): self
    {
        return new self($this->factories, true);
    }
}
