<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport;

use SanderMuller\FluentValidation\HasFluentValidation;

/**
 * Helper for the 1.2.0 layer-2 compose-conflict-warning fixtures. The
 * concrete subclass in `skip_livewire_attribute_with_fluent_trait_inherited.php.inc`
 * has `#[Rule]` property attributes; the trait inherited from this
 * base means the attribute pathway is silently ignored at runtime
 * (`HasFluentValidation::getRules()` reads only the trait's own
 * `rules()` method, not Livewire attribute metadata).
 *
 * The rector MUST bail conversion in this composition AND emit a
 * skip-log entry naming the offending property + remediation
 * options. Mirrors the existing `BaseWithFinalPublicRules` shape
 * (parent-final-rules safety check); same skip-fixture format.
 */
abstract class BaseWithFluentValidationTrait
{
    use HasFluentValidation;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'parentOwnedField' => ['required', 'string'],
        ];
    }
}
