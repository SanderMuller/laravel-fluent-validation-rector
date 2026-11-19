<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\Rules\ArrayRule;
use SanderMuller\FluentValidation\Rules\BooleanRule;
use SanderMuller\FluentValidation\Rules\NumericRule;
use SanderMuller\FluentValidation\Rules\StringRule;

/**
 * Type-token trigger of `PromoteFieldFactoryRector`:
 * `FluentRule::field()->nullable()->rule('array')` → `FluentRule::array()->nullable()`.
 * The token hop is removed, because the typed factory seeds the same constraint.
 *
 * The compiled rule list only stays the same when the token hop comes before
 * every constraint that is not a presence constraint: the package moves
 * presence constraints (`required`, `nullable`, `exclude_if`, …) to the front,
 * and puts the seeded type before everything else. So the hops before the
 * token may only add presence constraints, set a label or bind a message.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait PromotesFieldTypeToken
{
    /**
     * Type-token sequences that a typed factory seeds exactly, in the same
     * order: `FluentRule::integer()` compiles to `numeric|integer`, so the
     * pair maps to it, but `integer` alone does not.
     *
     * @var array<string, array{class-string, string}>
     */
    private const array TYPE_TOKEN_FACTORIES = [
        'string' => [StringRule::class, 'string'],
        'array' => [ArrayRule::class, 'array'],
        'boolean' => [BooleanRule::class, 'boolean'],
        'numeric' => [NumericRule::class, 'numeric'],
        'numeric+integer' => [NumericRule::class, 'integer'],
    ];

    /** @var list<string> */
    private const array TYPE_TOKENS = ['string', 'array', 'boolean', 'numeric', 'integer'];

    /**
     * Hops that only add a presence constraint, which the package compiles
     * before the type, or that add no constraint at all.
     *
     * @var list<string>
     */
    private const array PRESENCE_OR_LABEL_HOPS = [
        'required', 'nullable', 'sometimes', 'filled', 'present', 'missing',
        'bail', 'exclude', 'prohibited', 'label', 'message',
    ];

    /** @var list<string> */
    private const array PRESENCE_HOP_PREFIXES = ['required', 'missing', 'exclude', 'prohibited'];

    /**
     * Bails on a second type token, on a `message()` right after the token (it
     * binds to the token's constraint), on a hop the target class does not
     * have, and on a labelled `field('…')` whose target reads its first
     * argument as something other than a label.
     *
     * @param  list<class-string>  $labelFirstTargets
     * @param  list<string>  $conditionableHops
     */
    private function applyTypeTokenPromotion(StaticCall|MethodCall $root, MethodCall $node, array $labelFirstTargets, array $conditionableHops): ?Node
    {
        $hops = $this->hopsInSourceOrder($root, $node);
        $run = $hops === null ? null : $this->leadingTypeTokenRun($hops);

        if ($hops === null || $run === null) {
            return null;
        }

        [$afterIndex, $tokenHops, $targetClass, $factoryName] = $run;

        if ($root->args !== [] && ! in_array($targetClass, $labelFirstTargets, true)) {
            return null;
        }

        if (isset($hops[$afterIndex]) && $this->hopNameIs($hops[$afterIndex], 'message')) {
            return null;
        }

        if (! $this->otherHopsFitTarget($hops, $afterIndex, $tokenHops, $targetClass, $conditionableHops)) {
            return null;
        }

        $replacement = $node;

        foreach ($tokenHops as $tokenHop) {
            $replacement = $this->removeHop($replacement, $tokenHop);
        }

        $root->name = new Identifier($factoryName);

        $this->preserveFluentNewlinesInChain($replacement);

        return $replacement;
    }

    /**
     * Skips the leading presence/label hops, then reads the run of type-token
     * hops after them. Returns the index after the run, the token hops, and the
     * matching target class and factory name; null when the chain does not
     * start that way or the run matches no factory.
     *
     * @param  list<MethodCall>  $hops
     * @return array{int, list<MethodCall>, class-string, string}|null
     */
    private function leadingTypeTokenRun(array $hops): ?array
    {
        $index = 0;

        while (isset($hops[$index]) && ! $this->hopNameIs($hops[$index], 'rule')) {
            if (! $this->isPresenceOrLabelHop($hops[$index])) {
                return null;
            }

            ++$index;
        }

        $tokenHops = [];
        $tokens = [];

        while (isset($hops[$index]) && ($token = $this->typeTokenOf($hops[$index])) !== null) {
            $tokenHops[] = $hops[$index];
            $tokens[] = $token;
            ++$index;
        }

        $target = self::TYPE_TOKEN_FACTORIES[implode('+', $tokens)] ?? null;

        return $target === null ? null : [$index, $tokenHops, $target[0], $target[1]];
    }

    /**
     * Every hop outside the token run must be a `->rule()` hop with a payload
     * other than a type token, or a method the target class has. Conditionable
     * hops fail: the proxy they return hides the receiver type.
     *
     * @param  list<MethodCall>  $hops
     * @param  list<MethodCall>  $tokenHops
     * @param  class-string  $targetClass
     * @param  list<string>  $conditionableHops
     */
    private function otherHopsFitTarget(array $hops, int $afterIndex, array $tokenHops, string $targetClass, array $conditionableHops): bool
    {
        foreach ($hops as $position => $hop) {
            if (in_array($hop, $tokenHops, true)) {
                continue;
            }

            if (! $hop->name instanceof Identifier || in_array($hop->name->toString(), $conditionableHops, true)) {
                return false;
            }

            if ($this->hopNameIs($hop, 'rule')) {
                if ($position >= $afterIndex && $this->typeTokenOf($hop) !== null) {
                    return false;
                }

                continue;
            }

            if (! $this->methodExistsOnPasswordEmailTarget($targetClass, $hop->name->toString())) {
                return false;
            }
        }

        return true;
    }

    /**
     * The hops between `$root` and `$node`, first hop first. Null when the
     * walk does not reach `$root`.
     *
     * @return list<MethodCall>|null
     */
    private function hopsInSourceOrder(StaticCall|MethodCall $root, MethodCall $node): ?array
    {
        $hops = [];
        $current = $node;

        while ($current instanceof MethodCall && $current !== $root) {
            $hops[] = $current;
            $current = $current->var;
        }

        return $current === $root ? array_reverse($hops) : null;
    }

    private function hopNameIs(MethodCall $hop, string $name): bool
    {
        return $hop->name instanceof Identifier && strcasecmp($hop->name->toString(), $name) === 0;
    }

    private function isPresenceOrLabelHop(MethodCall $hop): bool
    {
        if (! $hop->name instanceof Identifier) {
            return false;
        }

        $name = $hop->name->toLowerString();

        if (in_array($name, self::PRESENCE_OR_LABEL_HOPS, true)) {
            return true;
        }

        foreach (self::PRESENCE_HOP_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The type token of a `->rule('<token>')` hop, or null for any other hop
     * or payload.
     */
    private function typeTokenOf(MethodCall $hop): ?string
    {
        if (! $this->hopNameIs($hop, 'rule') || count($hop->args) !== 1 || ! $hop->args[0] instanceof Arg || ! $hop->args[0]->value instanceof String_) {
            return null;
        }

        $token = $hop->args[0]->value->value;

        return in_array($token, self::TYPE_TOKENS, true) ? $token : null;
    }

    private function removeHop(MethodCall|StaticCall $top, MethodCall $hop): MethodCall|StaticCall
    {
        if ($top === $hop) {
            return $hop->var instanceof MethodCall || $hop->var instanceof StaticCall ? $hop->var : $top;
        }

        $current = $top;

        while ($current instanceof MethodCall) {
            if ($current->var === $hop) {
                $current->var = $hop->var;

                break;
            }

            $current = $current->var;
        }

        return $top;
    }
}
