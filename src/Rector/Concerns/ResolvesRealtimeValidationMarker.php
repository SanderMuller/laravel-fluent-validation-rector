<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use Rector\Rector\AbstractRector;

/**
 * Decides whether a converted `#[Validate]` attribute needs an empty
 * `#[Validate]` marker preserved on the property after stripping.
 *
 * Livewire v3 fires real-time validation on property updates only when a
 * `#[Validate]` attribute is present; `rules()` alone fires on explicit
 * `$this->validate()` calls. Without the marker, stripping `#[Validate]`
 * silently regresses `wire:model.live` form validation. The deprecated
 * `#[Rule]` alias is intentionally excluded — the rector's scope is
 * FluentRule migration, not the `#[Rule]` → `#[Validate]` upgrade.
 *
 * Host rector must expose a `preserveRealtimeValidation` bool property that
 * this trait reads via the `shouldPreserveRealtimeValidation()` accessor.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ResolvesRealtimeValidationMarker
{
    /**
     * Host rector writes this flag from its `configure()`. Default `true`
     * matches the spec — opt-out only, with `preserve_realtime_validation`
     * config key set to `false`.
     */
    private bool $preserveRealtimeValidation = true;

    private function shouldPreserveRealtimeMarker(Attribute $attr): bool
    {
        if (! $this->preserveRealtimeValidation) {
            return false;
        }

        return $this->isValidateAttribute($attr) && ! $this->hasOnUpdateFalse($attr);
    }

    /**
     * True when ANY `#[Validate]` attribute on the property explicitly opts
     * out of real-time validation via `onUpdate: false`. Used to aggregate
     * across a property with multiple `#[Validate]` attributes: if any of
     * them disables real-time, the marker must not be emitted — otherwise
     * conversion would silently re-enable real-time validation that the
     * user explicitly turned off. Must run BEFORE the source attributes are
     * stripped, so the veto check has the original input to inspect.
     */
    private function anyValidateOptsOutOfRealtime(Property $property): bool
    {
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isValidateAttribute($attr) && $this->hasOnUpdateFalse($attr)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isValidateAttribute(Attribute $attr): bool
    {
        $name = $this->getName($attr->name);

        return $name === 'Livewire\Attributes\Validate' || $name === 'Validate';
    }

    private function hasOnUpdateFalse(Attribute $attr): bool
    {
        foreach ($attr->args as $arg) {
            if (! $arg->name instanceof Identifier) {
                continue;
            }

            if ($arg->name->toString() !== 'onUpdate') {
                continue;
            }

            if ($arg->value instanceof ConstFetch && $this->isName($arg->value, 'false')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Append an empty `#[Validate]` marker to the property's attribute
     * groups. The marker's Name is cloned from the original converted
     * attribute so it matches the form the consumer wrote — short alias vs
     * fully-qualified. No-op when `$markerName` is null.
     *
     * The caller is responsible for gating this behind
     * `anyValidateOptsOutOfRealtime($property)` BEFORE the property's source
     * attributes are stripped — this helper only performs the emission.
     */
    private function appendRealtimeValidationMarker(Property $property, ?Name $markerName): void
    {
        if (! $markerName instanceof Name) {
            return;
        }

        $property->attrGroups[] = new AttributeGroup([new Attribute(clone $markerName)]);
    }
}
