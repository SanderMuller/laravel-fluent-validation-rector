<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\PrettyPrinter\Standard;
use Rector\Rector\AbstractRector;

/**
 * Walk a Livewire `#[Rule]` / `#[Validate]` attribute's named args and emit
 * skip-log entries for each shape the rector can't migrate. Three
 * categories, each getting its own skip-log line so users can distinguish:
 *
 * - **Dropped (known Livewire arg, no FluentRule equivalent):** `message:`
 *   (deferred to Phase 4 — `messages(): array` generation), explicit
 *   `onUpdate:` values other than `false` (default is already true
 *   post-strip; Phase 2's marker-veto path handles `onUpdate: false`), and
 *   `translate: false` (no FluentRule equivalent for disabling `trans()`).
 * - **Array-form `message:` deferred:** keyed `message: [rule => text]`
 *   maps migrate to a generated `messages(): array` in Phase 4. Phase 3
 *   scope only skip-logs with a pointer.
 * - **Unrecognized (not Livewire-documented):** surfaces likely typos —
 *   most commonly `messages:` (plural) intended as `message:`. Livewire
 *   itself would throw on an unknown arg at attribute parse time; the
 *   rector flags it so the user knows which attribute to fix.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ReportsLivewireAttributeArgs
{
    use LogsSkipReasons;

    private function logUnsupportedAttributeArgs(Attribute $attr, Property $property, Class_ $class): void
    {
        $classification = $this->classifyAttributeArgs($attr);
        $propertyName = $this->firstPropertyName($property);

        // When `migrate_messages` is on AND this specific attribute's
        // message: arg was successfully migrated by MigratesAttributeMessages,
        // suppress the legacy "dropped" / "deferred" log lines so users
        // don't see misleading "manual migration needed" hints. Per-attribute
        // granularity matters: a non-literal/mixed message: shape doesn't
        // migrate, and the legacy log MUST still fire for those so the
        // user has a trail to debug. The host rector composes both this
        // concern and MigratesAttributeMessages.
        $messageMigrated = $this->migrateMessages && $this->attributeMessageWasMigrated($attr);

        $droppedFiltered = $messageMigrated
            ? array_values(array_filter(
                $classification['dropped'],
                static fn (string $entry): bool => ! str_starts_with($entry, 'message:'),
            ))
            : $classification['dropped'];

        if ($droppedFiltered !== []) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s dropped unsupported args (%s); migrate to messages() / hooks manually',
                $propertyName,
                implode(', ', $droppedFiltered),
            ));
        }

        if ($classification['hasArrayMessage'] && ! $messageMigrated) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s uses array-form message: — deferred or unsupported shape (mixed/non-literal items); migrate manually',
                $propertyName,
            ));
        }

        if ($classification['unrecognized'] !== []) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s uses unrecognized arg(s) (%s); not a Livewire-documented attribute arg — possible typo for `message:`?',
                $propertyName,
                implode(', ', $classification['unrecognized']),
            ));
        }
    }

    /**
     * @return array{dropped: list<string>, unrecognized: list<string>, hasArrayMessage: bool}
     */
    private function classifyAttributeArgs(Attribute $attr): array
    {
        $dropped = [];
        $unrecognized = [];
        $hasArrayMessage = false;

        foreach ($attr->args as $arg) {
            if (! $arg->name instanceof Identifier) {
                continue;
            }

            $name = $arg->name->toString();

            if ($name === 'message') {
                if ($arg->value instanceof Array_) {
                    $hasArrayMessage = true;
                } else {
                    $dropped[] = $name . ': ' . $this->printArgValue($arg->value);
                }
            } elseif ($name === 'onUpdate' && ! $this->isFalseConst($arg->value)) {
                $dropped[] = $name . ': ' . $this->printArgValue($arg->value);
            } elseif ($name === 'translate' && $this->isFalseConst($arg->value)) {
                $dropped[] = 'translate: false';
            } elseif ($name === 'messages') {
                $unrecognized[] = $name;
            }
        }

        return [
            'dropped' => $dropped,
            'unrecognized' => $unrecognized,
            'hasArrayMessage' => $hasArrayMessage,
        ];
    }

    private function isFalseConst(Expr $value): bool
    {
        return $value instanceof ConstFetch && $this->isName($value, 'false');
    }

    private function printArgValue(Expr $value): string
    {
        return (new Standard())->prettyPrint([$value]);
    }

    abstract private function firstPropertyName(Property $property): string;
}
