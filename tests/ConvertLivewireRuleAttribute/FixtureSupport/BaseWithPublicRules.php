<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport;

/**
 * Helper class for `generates_public_rules_when_parent_public.php.inc`.
 * The concrete subclass has `#[Rule]` attributes and inherits a non-final
 * `public function rules()`; PHP's visibility-covariance rules require the
 * child's override to also be `public` (narrowing public → protected is a
 * fatal covariance violation).
 */
class BaseWithPublicRules
{
    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
