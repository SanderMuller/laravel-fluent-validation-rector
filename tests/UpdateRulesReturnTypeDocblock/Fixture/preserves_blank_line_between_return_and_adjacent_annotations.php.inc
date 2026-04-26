<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Pre-0.14.1: the RETURN_TAG_PATTERN's continuation regex consumed
// the blank `*` separator between `@return` and adjacent PHPDoc tags
// (`@phpstan-ignore`, `@param`, etc.). The body rewrite replaced the
// match with `@return <new-body>`, dropping the trailing separator.
// Collectiq dogfood (2026-04-26) flagged it on AppealPage /
// ReportContentButton. Fix: add `\s*$` to the continuation lookahead
// so blank `*` lines stop the body capture.
class PreservesBlankLineBetweenReturnAndAdjacentAnnotations extends FormRequest
{
    /**
     * @return array<string, mixed>
     *
     * @phpstan-ignore method.unused
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Pre-0.14.1: the RETURN_TAG_PATTERN's continuation regex consumed
// the blank `*` separator between `@return` and adjacent PHPDoc tags
// (`@phpstan-ignore`, `@param`, etc.). The body rewrite replaced the
// match with `@return <new-body>`, dropping the trailing separator.
// Collectiq dogfood (2026-04-26) flagged it on AppealPage /
// ReportContentButton. Fix: add `\s*$` to the continuation lookahead
// so blank `*` lines stop the body capture.
class PreservesBlankLineBetweenReturnAndAdjacentAnnotations extends FormRequest
{
    /**
     * @return array<string, FluentRuleContract>
     *
     * @phpstan-ignore method.unused
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
