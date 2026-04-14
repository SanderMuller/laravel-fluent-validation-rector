<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport;

/**
 * Helper class for `skip_parent_has_final_rules.php.inc`. The concrete
 * subclass in that fixture has `#[Rule]` attributes — the rector MUST
 * skip generation rather than emit a `rules()` method that would fatal
 * at class-load time ("Cannot override final method").
 */
class BaseWithFinalPublicRules
{
    /**
     * @return array<string, array<int, string>>
     */
    final public function rules(): array
    {
        return [
            'parentOwnedField' => ['required', 'string'],
        ];
    }
}
