<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;

/**
 * Consumer-declared allowlist of rule factories — static-method calls or
 * constructor invocations that produce rule-compatible values the rectors
 * would otherwise skip-log as "unparseable payload" or "not a FluentRule
 * chain". The allowlist suppresses that noise without enabling new
 * conversions: allowlisted values stay as escape-hatch calls; the
 * surrounding array / docblock is left alone.
 *
 * Config format:
 *
 *   - `TREAT_AS_FLUENT_COMPATIBLE` (list): entries are either
 *       - constructor-class FQN / glob string (matches `new <FQN>(...)`);
 *       - `[<class-FQN-or-glob>, '<methodName>']` tuple (matches `<Class>::<method>(...)`).
 *   - `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` (bool, default false): when true,
 *       `MethodCall` chains rooted in an allowlisted `New_` / `StaticCall`
 *       are also treated as allowlisted — e.g. `Model::existsRule()->where(...)`.
 *       Default off because allowlisted factories can expose methods that
 *       return non-rule values (per Codex review 2026-04-24).
 *
 * Pattern syntax (NOT fnmatch — backslashes in class names conflict with
 * fnmatch's escape semantics):
 *
 *   - Exact FQN: `App\Models\Question`
 *   - Single-segment wildcard `*`: matches exactly one namespace segment
 *     (at least one char, no `\`). `App\Models\*` matches `App\Models\Foo`,
 *     NOT `App\Models\Sub\Bar` (too deep) NOR `App\Models\` (empty segment).
 *   - Recursive wildcard `**`: matches one or more characters across any
 *     namespace depth. `App\Models\**` matches `App\Models\Foo` AND
 *     `App\Models\Sub\Bar`. Does NOT collapse adjacent `\` separators, so
 *     `App\**\Rule` matches `App\Sub\Rule` but not `App\Rule` — use
 *     `App\*\Rule` or a second exact entry for that. Zero-depth collapse is
 *     deferred; open a ROADMAP entry if needed.
 *
 * @phpstan-require-extends AbstractRector
 */
trait AllowlistedRuleFactories
{
    public const string TREAT_AS_FLUENT_COMPATIBLE = 'treat_as_fluent_compatible';

    public const string ALLOW_CHAIN_TAIL_ON_ALLOWLISTED = 'allow_chain_tail_on_allowlisted';

    /**
     * Raw allowlist entries as configured by the consumer.
     *
     * @var list<string|array{0: string, 1: string}>
     */
    private array $allowlistedRuleFactories = [];

    private bool $allowChainTailOnAllowlisted = false;

    /**
     * Per-process regex cache for compiled class-name patterns. Same pattern
     * compiles to the same regex across rector fires within one process.
     *
     * @var array<string, string>
     */
    private static array $allowlistPatternRegexCache = [];

    /**
     * Consume the shared keys from a rector's `$configuration` array. Safe to
     * call alongside other config consumers in the same `configure()` method.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function configureAllowlistedRuleFactoriesFrom(array $configuration): void
    {
        $entries = $configuration[self::TREAT_AS_FLUENT_COMPATIBLE] ?? [];

        if (is_array($entries)) {
            $normalized = [];

            foreach ($entries as $entry) {
                if (is_string($entry)) {
                    $normalized[] = $this->normalizeFqn($entry);

                    continue;
                }

                if (is_array($entry) && count($entry) === 2 && isset($entry[0], $entry[1])
                    && is_string($entry[0]) && is_string($entry[1])) {
                    $normalized[] = [$this->normalizeFqn($entry[0]), $entry[1]];
                }
            }

            $this->allowlistedRuleFactories = $normalized;
        }

        $chainTail = $configuration[self::ALLOW_CHAIN_TAIL_ON_ALLOWLISTED] ?? false;

        $this->allowChainTailOnAllowlisted = (bool) $chainTail;
    }

    /**
     * Whether `$value` matches any allowlist entry.
     *
     * Exact shape matching — `New_($FQN, ...)` against constructor entries,
     * `StaticCall($FQN, '$method', ...)` against tuple entries. `MethodCall`
     * chains follow their receiver back to the root; accepted iff the root
     * matches AND `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED` is enabled. See trait
     * docblock for the chain-tail rationale.
     */
    private function isAllowlistedRuleFactory(Expr $value): bool
    {
        if ($value instanceof New_) {
            return $this->matchesConstructorAllowlist($value);
        }

        if ($value instanceof StaticCall) {
            return $this->matchesStaticCallAllowlist($value);
        }

        if ($value instanceof MethodCall && $this->allowChainTailOnAllowlisted) {
            return $this->isAllowlistedRuleFactory($this->innermostReceiverOf($value));
        }

        return false;
    }

    private function matchesConstructorAllowlist(New_ $new): bool
    {
        if (! $new->class instanceof Name) {
            return false;
        }

        $className = $this->normalizeFqn($this->getName($new->class) ?? '');

        foreach ($this->allowlistedRuleFactories as $entry) {
            if (is_string($entry) && $this->classNameMatchesPattern($className, $entry)) {
                return true;
            }
        }

        return false;
    }

    private function matchesStaticCallAllowlist(StaticCall $call): bool
    {
        if (! $call->class instanceof Name || ! $call->name instanceof Identifier) {
            return false;
        }

        $className = $this->normalizeFqn($this->getName($call->class) ?? '');
        $methodName = $call->name->toString();

        foreach ($this->allowlistedRuleFactories as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ($entry[1] !== $methodName) {
                continue;
            }

            if ($this->classNameMatchesPattern($className, $entry[0])) {
                return true;
            }
        }

        return false;
    }

    private function innermostReceiverOf(MethodCall $methodCall): Expr
    {
        $current = $methodCall->var;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        return $current;
    }

    private function classNameMatchesPattern(string $className, string $pattern): bool
    {
        $className = $this->normalizeFqn($className);
        $pattern = $this->normalizeFqn($pattern);

        // Exact match short-circuit — the common case, avoids regex compile.
        if (! str_contains($pattern, '*')) {
            return $className === $pattern;
        }

        $regex = self::$allowlistPatternRegexCache[$pattern] ??= $this->compilePatternToRegex($pattern);

        return preg_match($regex, $className) === 1;
    }

    private function normalizeFqn(string $fqn): string
    {
        return ltrim($fqn, '\\');
    }

    /**
     * Compile a restricted-glob pattern to a PCRE regex via a tokenizer — split
     * the input on `**` / `*` and preg_quote each literal span independently.
     * Avoids the collision risk of textual sentinels: an earlier substitute-
     * then-preg_quote-then-restore approach would mis-expand any FQN that
     * happened to contain the chosen sentinel string (Codex review
     * 2026-04-24).
     *
     * `**` is matched greedily before `*` by `PREG_SPLIT_DELIM_CAPTURE` on an
     * alternation with the longer pattern first.
     */
    private function compilePatternToRegex(string $pattern): string
    {
        $parts = preg_split('/(\*\*|\*)/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return '/^' . preg_quote($pattern, '/') . '$/';
        }

        $regex = '';

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $regex .= preg_quote($part, '/');

                continue;
            }

            $regex .= $part === '**' ? '.*' : '[^\\\\]+';
        }

        return '/^' . $regex . '$/';
    }
}
