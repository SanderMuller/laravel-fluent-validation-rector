<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use Rector\Rector\AbstractRector;

/**
 * Detects Livewire's `#[Rule]` / `#[Validate]` attributes on class properties.
 *
 * Matches both fully-qualified names (`Livewire\Attributes\Rule`,
 * `Livewire\Attributes\Validate`) and their short aliases (`Rule`, `Validate`).
 * Livewire aliases `#[Rule]` historically but deprecated it in favour of
 * `#[Validate]` — the rector supports both.
 *
 * @phpstan-require-extends AbstractRector
 */
trait DetectsLivewireRuleAttributes
{
    /** @var list<string> */
    private const array LIVEWIRE_RULE_ATTRIBUTE_NAMES = [
        'Livewire\Attributes\Rule',
        'Livewire\Attributes\Validate',
        'Rule',
        'Validate',
    ];

    private function hasAnyLivewireRuleAttribute(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isLivewireRuleAttribute($attr)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function isLivewireRuleAttribute(Attribute $attr): bool
    {
        $name = $this->getName($attr->name);

        if ($name === null) {
            return false;
        }

        return in_array($name, self::LIVEWIRE_RULE_ATTRIBUTE_NAMES, true);
    }
}
